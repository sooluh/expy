<?php

namespace App\Services\Registrars;

use App\Models\Registrar;
use App\Services\ScrapingantService;
use App\Support\Concerns\RegistrarService;
use App\Support\Concerns\ScrapesHtmlWithFallback;
use Carbon\Carbon;
use DOMElement;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class IdcloudhostService
{
    use RegistrarService, ScrapesHtmlWithFallback;

    public const BASE_URL = 'https://idcloudhost.com';

    protected Registrar $registrar;

    protected Client $client;

    public function __construct(Registrar $registrar, ScrapingantService $scrapingantService)
    {
        $this->registrar = $registrar;
        $this->bootScrapingant($scrapingantService);
        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'timeout' => 30,
        ]);
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function validateCredentials(): bool
    {
        return $this->isConfigured();
    }

    public function getPrices(): Collection
    {
        try {
            $html = $this->fetchHtmlWithFallback('https://idcloudhost.com/domain/', proxyCountry: 'ID');

            return $this->parsePricesFromHtml($html);
        } catch (GuzzleException|Exception $e) {
            $statusCode = null;
            $responseBody = null;

            if ($e instanceof RequestException && $e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
            }

            Log::error('Failed to fetch IDCloudHost prices', [
                'registrar_id' => $this->registrar->id ?? null,
                'error' => $e->getMessage(),
                'status_code' => $statusCode,
                'response_body' => $responseBody,
            ]);

            throw $e;
        }
    }

    public function getDomains(): Collection
    {
        if (empty($this->getCookies())) {
            throw new Exception('IDCloudHost cookies are not configured');
        }

        try {
            $cookies = registrar_normalize_cookies($this->getCookies());

            Log::info('[IDCloudHost] getDomains starting', [
                'registrar_id' => $this->registrar->id ?? null,
                'cookies_present' => $cookies !== null,
                'cookies_length' => $cookies ? strlen($cookies) : 0,
            ]);

            $html = $this->fetchHtmlWithFallback(
                'https://my.idcloudhost.com/clientarea.php?action=domains',
                $cookies,
                'table#tableDomainsList',
                proxyCountry: 'ID'
            );

            if (! is_string($html) || trim($html) === '') {
                throw new Exception('Empty HTML response from IDCloudHost domains page.');
            }

            Log::info('[IDCloudHost] getDomains fetched HTML', [
                'registrar_id' => $this->registrar->id ?? null,
                'html_length' => strlen($html),
            ]);

            $domains = $this->parseDomainsFromHtml($html);

            Log::info('[IDCloudHost] getDomains parsed rows', [
                'registrar_id' => $this->registrar->id ?? null,
                'count' => $domains->count(),
            ]);

            return $domains;
        } catch (GuzzleException|Exception $e) {
            $statusCode = null;
            $responseBody = null;

            if ($e instanceof RequestException && $e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
            }

            Log::error('Failed to fetch IDCloudHost domains', [
                'registrar_id' => $this->registrar->id ?? null,
                'error' => $e->getMessage(),
                'status_code' => $statusCode,
                'response_body' => $responseBody,
            ]);

            throw $e;
        }
    }

    public function getDomain(string $domainName): array
    {
        return [];
    }

    protected function parsePricesFromHtml(string $html): Collection
    {
        $xpath = registrar_create_xpath($html);
        $items = $xpath->query("//ul[contains(concat(' ', normalize-space(@class), ' '), ' nonmobile ')]/li");

        if (! $items || $items->length === 0) {
            return collect();
        }

        $result = [];

        foreach ($items as $li) {
            /** @var DOMElement $li */
            $featureBlocks = $xpath->query(".//div[contains(@class,'price-feature')]/div", $li);
            $data = [];

            foreach ($featureBlocks as $block) {
                /** @var DOMElement $block */
                $titleNode = $xpath->query(".//div[contains(@class,'feature-title')]", $block)->item(0);
                $valueNode = $xpath->query(".//div[contains(@class,'feature-value')]", $block)->item(0);

                $title = $titleNode ? trim($titleNode->textContent ?? '') : null;
                $value = $valueNode ? trim($valueNode->textContent ?? '') : null;

                if ($title && $value) {
                    $data[strtolower($title)] = $value;
                }
            }

            $tldRaw = $data['domain'] ?? null;
            $tld = $tldRaw ? ltrim($tldRaw, '.') : null;
            $registerPrice = registrar_parse_idr_price($data['register'] ?? null);
            $renewPrice = registrar_parse_idr_price($data['renewal'] ?? null);
            $transferPrice = registrar_parse_idr_price($data['transfer'] ?? null);

            if (! $tld || $registerPrice === null) {
                continue;
            }

            $result[] = [
                'tld' => $tld,
                'register_price' => $registerPrice,
                'renew_price' => $renewPrice ?? $registerPrice,
                'transfer_price' => $transferPrice ?? $renewPrice ?? $registerPrice,
                'restore_price' => null,
                'privacy_price' => null,
                'misc_price' => null,
            ];
        }

        return collect($result);
    }

    protected function parseDomainsFromHtml(string $html): Collection
    {
        $extracted = registrar_extract_table_rows($html, 'tableDomainsList');
        $rows = $extracted['rows'];
        $rowCount = $extracted['row_count'];

        Log::info('[IDCloudHost] Domains table rows status', [
            'registrar_id' => $this->registrar->id ?? null,
            'table_found' => $extracted['table_found'],
            'table_count' => $extracted['table_count'],
            'row_count' => $rowCount,
        ]);

        if ($rowCount === 0) {
            return collect();
        }

        $domains = [];

        foreach ($rows as $tr) {
            /** @var DOMElement $tr */
            $tds = $tr->getElementsByTagName('td');

            if ($tds->length < 3) {
                continue;
            }

            $domainTd = $tds->item(1);
            $domainName = $this->extractDomainNameFromCell($domainTd);

            if ($domainName === null || $domainName === '') {
                continue;
            }

            $registrationDate = $this->extractDateFromCell($tds->item(2));

            $domains[] = [
                'domain_name' => $domainName,
                'registration_date' => $registrationDate,
                'expiration_date' => null,
                'nameservers' => [],
                'security_lock' => null,
                'whois_privacy' => null,
            ];
        }

        return collect($domains);
    }

    protected function fallbackParseTableRows(string $html, string $tableId): Collection
    {
        $pattern = '/<table[^>]*id=["\']'.preg_quote($tableId, '/').'["\'][\s\S]*?<\/table>/i';

        if (! preg_match($pattern, $html, $matches)) {
            return collect();
        }

        $tableHtml = '<html><body>'.$matches[0].'</body></html>';
        $xpath = registrar_create_xpath($tableHtml);
        $rows = $xpath->query('//table[@id="'.$tableId.'"]//tr');

        return $rows ? collect(iterator_to_array($rows)) : collect();
    }

    protected function extractDomainNameFromCell(?DOMElement $td): ?string
    {
        if ($td === null) {
            return null;
        }

        $links = $td->getElementsByTagName('a');

        if ($links->length === 0) {
            return trim($td->textContent ?? '');
        }

        return trim($links->item(0)->textContent ?? '');
    }

    protected function extractDateFromCell(?DOMElement $td): ?Carbon
    {
        if ($td === null) {
            return null;
        }

        $spans = $td->getElementsByTagName('span');

        foreach ($spans as $span) {
            /** @var DOMElement $span */
            $class = $span->getAttribute('class');

            if (str_contains($class, 'hidden')) {
                $raw = trim($span->textContent ?? '');

                if ($raw !== '') {
                    try {
                        return Carbon::parse($raw);
                    } catch (Exception) {
                    }
                }
            }
        }

        $visible = trim($td->textContent ?? '');

        if ($visible === '') {
            return null;
        }

        try {
            return Carbon::parse($visible);
        } catch (Exception) {
            return null;
        }
    }
}
