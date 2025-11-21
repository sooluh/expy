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
use Illuminate\Support\Str;
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
            $html = $this->fetchHtml($url);

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
            $client = new Client(['base_uri' => self::BASE_URL, 'timeout' => 30]);

            $response = $client->get('https://idwebhost.com/domain-murah', [
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml',
                ],
                'http_errors' => false,
            ]);

            $html = $response->getBody()->getContents();

            if (! is_string($html) || trim($html) === '') {
                throw new Exception('Empty HTML response from IDwebhost.');
            }

            $csrfToken = $this->extractCsrfToken($html);

            Log::debug('[IDwebhost] CSRF token extracted', ['csrfToken' => $csrfToken]);

            $registerPrices = $this->parsePricesFromHtml($html);
            $randomDomain = strtolower(preg_replace('/[^a-z0-9]/', '', Str::random(8)));

            Log::debug('[IDwebhost] Random domain for AJAX', ['randomDomain' => $randomDomain]);

            $setCookies = $response->getHeader('Set-Cookie');
            $cookieForAjax = '';

            foreach ($setCookies as $sc) {
                $cookieForAjax .= explode(';', $sc)[0].'; ';
            }

            $cookieForAjax = trim($cookieForAjax);
            $cookieForAjax = $this->normalizeCookies($cookieForAjax);

            Log::debug('[IDwebhost] Cookie for AJAX', ['cookieForAjax' => $cookieForAjax]);

            $renewPrices = collect();

            $types = [
                'recommend',
                'domainid',
                'promo',
                'perusahaan',
                'organisasi',
                'pendidikan',
                'toko',
                'profesi',
                'bisnis',
                'personal',
                'umum',
            ];

            foreach ($types as $type) {
                Log::debug('[IDwebhost] Preflight request', [
                    'type' => $type,
                    'domainname' => $randomDomain,
                    'token' => $csrfToken,
                ]);

                $preflight = $this->postAjax(
                    'https://idwebhost.com/index.php?action=orderwhmcs.validatedomain',
                    ['domainname' => $randomDomain, 'token' => $csrfToken],
                    $cookieForAjax
                );

                Log::debug('[IDwebhost] Preflight response', ['type' => $type, 'response' => $preflight]);

                if (! isset($preflight['response']) || $preflight['response'] != 1) {
                    continue;
                }

                $domainToCheck = $randomDomain.'.com';

                Log::debug('[IDwebhost] Whois request', [
                    'type' => $type,
                    'domain' => $domainToCheck,
                    'token' => $csrfToken,
                ]);

                $whois = $this->postAjax(
                    'https://idwebhost.com/index.php?action=whois.getwhoisdomain',
                    ['domain' => $domainToCheck, 'token' => $csrfToken, 'type' => $type],
                    $cookieForAjax
                );

                Log::debug('[IDwebhost] Whois response', ['type' => $type, 'response' => $whois]);

                if (! isset($whois['result']) || ! is_array($whois['result'])) {
                    continue;
                }

                foreach ($whois['result'] as $row) {
                    Log::debug('[IDwebhost] Whois row', ['type' => $type, 'row' => $row]);

                    if (! isset($row['tld']) || ! isset($row['price_renew'])) {
                        continue;
                    }

                    $tld = ltrim($row['tld'], '.');
                    $renewPrices[$tld] = (float) $row['price_renew'];
                }
            }

            Log::debug('[IDwebhost] Final renewPrices', ['renewPrices' => $renewPrices]);

            $merged = $registerPrices->map(function ($item) use ($renewPrices) {
                $tld = $item['tld'] ?? null;
                $renew = $tld && isset($renewPrices[$tld]) ? $renewPrices[$tld] : null;

                return [
                    ...$item,
                    'renew_price' => $renew,
                    'transfer_price' => $renew,
                ];
            });

            Log::debug('[IDwebhost] Final merged prices', ['merged' => $merged]);

            return $merged;
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

    protected function extractCsrfToken(string $html): ?string
    {
        if (! preg_match("/let csrfToken = '([a-f0-9]+)';/", $html, $matches)) {
            return null;
        }

        return $matches[1] ?? null;
    }

    protected function postAjax(string $url, array $payload, ?string $cookie = null): array
    {
        $domainName = $payload['domainname'] ?? ($payload['domain'] ?? '');
        $referer = 'https://idwebhost.com/cek-domain/'.$domainName;

        $headers = [
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
            'X-Requested-With' => 'XMLHttpRequest',
            'Referer' => $referer,
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36',
        ];

        if ($cookie) {
            $headers['Cookie'] = $cookie;
        }

        $multipart = [];

        foreach ($payload as $key => $value) {
            $multipart[] = [
                'name' => $key,
                'contents' => $value,
            ];
        }

        $options = [
            'headers' => $headers,
            'multipart' => $multipart,
            'timeout' => 30,
        ];

        $response = $this->client->post($url, $options);

        if ($response->getStatusCode() !== 200) {
            throw new Exception("IDwebhost AJAX request failed ({$url}): {$response->getStatusCode()}");
        }

        $body = $response->getBody()->getContents();
        $json = json_decode($body, true);

        return is_array($json) ? $json : [];
    }

    public function getDomains(): Collection
    {
        if (empty($this->getCookies())) {
            throw new Exception('IDwebhost cookies are not configured');
        }

        try {
            $html = $this->fetchHtml('https://member.idwebhost.com/clientarea.php?action=domains', 'table#tableDomainsList');

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

    public function getDomain(string $domainName): array
    {
        // TODO: implement domain fetch when integration is ready.
        return [];
    }

    protected function fetchHtml(string $url, ?string $waitForSelector = null): string
    {
        $cookies = $this->normalizeCookies($this->getCookies());

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
