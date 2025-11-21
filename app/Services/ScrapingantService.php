<?php

namespace App\Services;

use App\Settings\ScrapingantSettings;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ScrapingantService
{
    public const BASE_URL = 'https://api.scrapingant.com';

    private Client $client;

    public function __construct(
        private ScrapingantSettings $settings,
        ?Client $client = null
    ) {
        $this->client = $client ?? new Client([
            'base_uri' => self::BASE_URL,
            'http_errors' => false,
        ]);
    }

    public function scrape(string $url, ?string $waitForSelector = null, ?string $cookies = null): ?string
    {
        $apiKey = $this->getApiKey();

        $proxyCountries = [
            'US',
            'AE',
            'BR',
            'CA',
            'CN',
            'CZ',
            'DE',
            'ES',
            'FR',
            'GB',
            'HK',
            'IN',
            'IT',
            'JP',
            'NL',
            'PL',
            'RU',
            'SA',
            'SG',
            'ID',
            'KR',
            'VN',
        ];

        $baseQuery = [
            'url' => rawurlencode($url),
            'x-api-key' => $apiKey,
            'proxy_country' => $proxyCountries[array_rand($proxyCountries)],
        ];

        if ($waitForSelector !== null) {
            $baseQuery['wait_for_selector'] = rawurlencode($waitForSelector);
        }

        if ($cookies !== null && trim($cookies) !== '') {
            $baseQuery['cookies'] = rawurlencode($cookies);
        }

        return $this->sendRequestWithRetry($baseQuery);
    }

    protected function sendRequestWithRetry(array $query): ?string
    {
        $attempts = [
            $query,
            array_merge($query, ['browser' => 'false']),
        ];

        foreach ($attempts as $payload) {
            $response = $this->sendRequest($payload);

            if ($response !== null) {
                return $response;
            }
        }

        return null;
    }

    protected function sendRequest(array $query): ?string
    {
        try {
            Log::info('ScrapingAnt request', [
                'url' => $query['url'],
                'wait_for_selector' => $query['wait_for_selector'] ?? null,
                'cookies_present' => isset($query['cookies']) && trim($query['cookies']) !== '',
                'cookies_length' => isset($query['cookies']) ? strlen($query['cookies']) : 0,
                'proxy_country' => $query['proxy_country'] ?? null,
                'browser' => $query['browser'] ?? null,
            ]);

            $response = $this->client->get('/v2/general', ['query' => $query]);
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            if ($statusCode !== 200) {
                Log::warning('ScrapingAnt returned non-200 status.', [
                    'url' => $query['url'],
                    'status_code' => $statusCode,
                    'body' => $body,
                ]);

                return null;
            }

            if (! is_string($body) || trim($body) === '') {
                Log::warning('ScrapingAnt returned empty body.', [
                    'url' => $query['url'],
                    'status_code' => $statusCode,
                ]);

                return null;
            }

            return $body;
        } catch (GuzzleException $exception) {
            $statusCode = null;
            $responseBody = null;

            if ($exception instanceof RequestException && $exception->hasResponse()) {
                $statusCode = $exception->getResponse()->getStatusCode();
                $responseBody = $exception->getResponse()->getBody()->getContents();
            }

            Log::warning('ScrapingAnt request failed.', [
                'url' => $query['url'],
                'error' => $exception->getMessage(),
                'status_code' => $statusCode,
                'response_body' => $responseBody,
            ]);

            return null;
        }
    }

    public function getApiKey(): string
    {
        $this->settings->refresh();
        $apiKey = $this->settings->api_key;

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('ScrapingAnt API key is not configured.');
        }

        return $apiKey;
    }
}
