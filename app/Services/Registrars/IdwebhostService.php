<?php

namespace App\Services\Registrars;

use App\Concerns\RegistrarService;
use App\Models\Registrar;
use App\Services\ScrapingantService;
use DOMDocument;
use DOMXPath;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class IdwebhostService
{
    use RegistrarService;

    public const BASE_URL = 'https://idwebhost.com';

    protected Registrar $registrar;

    protected Client $client;

    protected ?ScrapingantService $scrapingantService = null;

    protected bool $scrapingantEnabled = false;

    public function __construct(Registrar $registrar, ScrapingantService $scrapingantService)
    {
        $this->registrar = $registrar;
        $this->scrapingantService = $scrapingantService;

        try {
            $this->scrapingantService->getApiKey();
            $this->scrapingantEnabled = true;
        } catch (Exception $e) {
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
        return true;
    }

    public function getPrices(): Collection
    {
        try {
            $html = $this->fetchPricesPageHtml();

            if (! is_string($html) || trim($html) === '') {
                throw new Exception('Empty HTML response from Idwebhost.');
            }

            return $this->parsePricesFromHtml($html);
        } catch (GuzzleException|Exception $e) {
            Log::error('Failed to fetch Idwebhost prices', [
                'registrar_id' => $this->registrar->id ?? null,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getDomains(): Collection
    {
        return collect();
    }

    protected function fetchPricesPageHtml(): string
    {
        $url = 'https://idwebhost.com/domain-murah';

        if ($this->scrapingantEnabled && $this->scrapingantService !== null) {
            try {
                $html = $this->scrapingantService->scrape($url, 'option[value=""]');

                if (is_string($html) && trim($html) !== '') {
                    return $html;
                }
            } catch (Exception $e) {
                Log::warning('ScrapingAnt failed for Idwebhost, falling back to direct HTTP', [
                    'registrar_id' => $this->registrar->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $response = $this->client->get('/domain-murah');

        if ($response->getStatusCode() !== 200) {
            throw new Exception("Idwebhost page request failed: {$response->getStatusCode()}");
        }

        return $response->getBody()->getContents();
    }

    protected function parsePricesFromHtml(string $html): Collection
    {
        $dom = new DOMDocument;

        $internalErrors = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        $xpath = new DOMXPath($dom);

        $options = $xpath->query(
            '//select[@data-hs-select and option[@value=""]]/option[@value != ""]'
        );

        if (! $options || $options->length === 0) {
            return collect();
        }

        $result = [];

        foreach ($options as $option) {
            /** @var \DOMElement $option */
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

            if ($priceText === null) {
                continue;
            }

            $price = $this->parseIdrPrice($priceText);

            if ($price === null) {
                continue;
            }

            $result[] = [
                'tld' => $tld,
                'register_price' => $price,
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
}
