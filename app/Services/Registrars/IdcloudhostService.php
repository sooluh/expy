<?php

namespace App\Services\Registrars;

use App\Models\Registrar;
use App\Services\ScrapingantService;
use App\Support\Concerns\RegistrarService;
use App\Support\Concerns\ScrapesHtmlWithFallback;
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
            $html = $this->fetchHtmlWithFallback('https://idcloudhost.com/domain/');

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
        // TODO: implement domain fetch when integration is ready.
        return collect();
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
}
