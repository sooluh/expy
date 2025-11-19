<?php

namespace App\Services\Registrars;

use App\Concerns\RegistrarService;
use App\Models\Registrar;
use App\Services\ScrapingantService;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class IdwebhostService
{
    use RegistrarService;

    public const BASE_URL = 'https://idwebhost.com';

    protected Registrar $registrar;

    protected Client $client;

    protected ?ScrapingantService $scrapingantService;

    protected bool $scrapingantEnabled = false;

    public function __construct(Registrar $registrar, ScrapingantService $scrapingantService)
    {
        $this->registrar = $registrar;
        $this->scrapingantService = $scrapingantService;

        try {
            $this->scrapingantService->getApiKey();
            $this->scrapingantEnabled = true;
        } catch (Throwable) {
            $this->scrapingantEnabled = false;
        }

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
        $url = 'https://member.idwebhost.com/clientarea.php';

        if (empty($this->getCookies())) {
            return false;
        }

        try {
            $html = $this->fetchHtml($url, waitForSelector: null, useCookies: true);

            return is_string($html) && str_contains($html, 'Selamat Datang');
        } catch (Exception|GuzzleException $e) {
            $statusCode = null;
            $responseBody = null;

            if ($e instanceof RequestException && $e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
            }

            Log::error('Failed to validate IDwebhost credentials', [
                'registrar_id' => $this->registrar->id ?? null,
                'error' => $e->getMessage(),
                'status_code' => $statusCode,
                'response_body' => $responseBody,
            ]);

            return false;
        }
    }

    public function getPrices(): Collection
    {
        try {
            $html = $this->fetchHtml(
                'https://idwebhost.com/domain-murah',
                waitForSelector: 'option[value=""]',
                useCookies: false,
            );

            if (! is_string($html) || trim($html) === '') {
                throw new Exception('Empty HTML response from IDwebhost.');
            }

            return $this->parsePricesFromHtml($html);
        } catch (GuzzleException|Exception $e) {
            $statusCode = null;
            $responseBody = null;

            if ($e instanceof RequestException && $e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
            }

            Log::error('Failed to fetch IDwebhost prices', [
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
            throw new Exception('IDwebhost cookies are not configured');
        }

        try {
            $html = $this->fetchHtml(
                'https://member.idwebhost.com/clientarea.php?action=domains',
                waitForSelector: 'table#tableDomainsList',
                useCookies: true,
            );

            if (! is_string($html) || trim($html) === '') {
                throw new Exception('Empty HTML response from IDwebhost domains page.');
            }

            return $this->parseDomainsFromHtml($html);
        } catch (GuzzleException|Exception $e) {
            $statusCode = null;
            $responseBody = null;

            if ($e instanceof RequestException && $e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
            }

            Log::error('Failed to fetch IDwebhost domains', [
                'registrar_id' => $this->registrar->id ?? null,
                'error' => $e->getMessage(),
                'status_code' => $statusCode,
                'response_body' => $responseBody,
            ]);

            throw $e;
        }
    }

    protected function fetchHtml(string $url, ?string $waitForSelector = null, bool $useCookies = false): string
    {
        $cookies = $useCookies ? $this->normalizeCookies($this->getCookies()) : null;

        if ($this->scrapingantEnabled && $this->scrapingantService !== null) {
            try {
                $html = $this->scrapingantService->scrape(
                    $url,
                    $waitForSelector,
                    $cookies,
                );

                if (is_string($html) && trim($html) !== '') {
                    return $html;
                }
            } catch (Exception $e) {
                Log::warning('ScrapingAnt failed, falling back to direct HTTP', [
                    'registrar_id' => $this->registrar->id ?? null,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $headers = [
            'Accept' => 'text/html,application/xhtml+xml',
        ];

        if ($cookies) {
            $headers['Cookie'] = $cookies;
        }

        $response = $this->client->get($url, ['headers' => $headers]);

        if ($response->getStatusCode() !== 200) {
            throw new Exception("IDwebhost request failed ({$url}): {$response->getStatusCode()}");
        }

        return $response->getBody()->getContents();
    }

    protected function normalizeCookies(?string $cookies): ?string
    {
        if ($cookies === null || trim($cookies) === '') {
            return null;
        }

        return preg_replace('/\s*;\s*/', '; ', trim($cookies)) ?: $cookies;
    }

    protected function createXPath(string $html): DOMXPath
    {
        $dom = new DOMDocument;

        $internalErrors = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        return new DOMXPath($dom);
    }

    protected function parsePricesFromHtml(string $html): Collection
    {
        $xpath = $this->createXPath($html);

        $options = $xpath->query(
            '//select[@data-hs-select and option[@value=""]]/option[@value != ""]'
        );

        if (! $options || $options->length === 0) {
            return collect();
        }

        $result = [];

        foreach ($options as $option) {
            /** @var DOMElement $option */
            $value = trim($option->getAttribute('value'));

            if ($value === '') {
                continue;
            }

            $tld = ltrim($value, '.');

            if ($tld === '') {
                continue;
            }

            $dataAttr = $option->getAttribute('data-hs-select-option');
            $priceText = $this->extractDescriptionFromDataAttribute($dataAttr);
            $priceValue = $this->parseIdrPrice($priceText);

            if ($priceValue === null) {
                continue;
            }

            $result[] = [
                'tld' => $tld,
                'register_price' => $priceValue,
                'renew_price' => null,
                'transfer_price' => null,
                'restore_price' => null,
                'privacy_price' => null,
                'misc_price' => null,
            ];
        }

        return collect($result);
    }

    protected function extractDescriptionFromDataAttribute(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $decoded = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5);
        $decoded = trim($decoded);

        if (
            (str_starts_with($decoded, "'") && str_ends_with($decoded, "'")) ||
            (str_starts_with($decoded, '"') && str_ends_with($decoded, '"'))
        ) {
            $decoded = substr($decoded, 1, -1);
        }

        $data = json_decode($decoded, true);

        if (! is_array($data)) {
            return null;
        }

        $description = $data['description'] ?? null;

        return is_string($description) ? $description : null;
    }

    protected function parseIdrPrice(?string $price): ?float
    {
        if ($price === null) {
            return null;
        }

        $clean = preg_replace('/[^\d,\.]/', '', $price);

        if ($clean === null || $clean === '') {
            return null;
        }

        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);

        if ($clean === '' || ! is_numeric($clean)) {
            return null;
        }

        return (float) $clean;
    }

    protected function parseDomainsFromHtml(string $html): Collection
    {
        $xpath = $this->createXPath($html);

        $rows = $xpath->query('//table[@id="tableDomainsList"]/tbody/tr');

        if (! $rows || $rows->length === 0) {
            return collect();
        }

        $domains = [];

        foreach ($rows as $tr) {
            /** @var DOMElement $tr */
            $tds = $tr->getElementsByTagName('td');

            if ($tds->length < 4) {
                continue;
            }

            $domainTd = $tds->item(1);
            $domainName = $this->extractDomainNameFromCell($domainTd);

            if ($domainName === null || $domainName === '') {
                continue;
            }

            $activationTd = $tds->item(2);
            $registrationDate = $this->extractDateFromCell($activationTd);

            $expiryTd = $tds->item(3);
            $expirationDate = $this->extractDateFromCell($expiryTd);

            $domains[] = [
                'domain_name' => $domainName,
                'registration_date' => $registrationDate,
                'expiration_date' => $expirationDate,
                'nameservers' => [],
                'security_lock' => null,
                'whois_privacy' => null,
            ];
        }

        return collect($domains);
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
                    } catch (Throwable) {
                    }
                }
            }
        }

        $visible = trim($td->textContent ?? '');

        if ($visible === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $visible);
        } catch (Throwable) {
            try {
                return Carbon::parse($visible);
            } catch (Throwable) {
                return null;
            }
        }
    }
}
