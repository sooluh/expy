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

        $query = [
            'url' => $url,
            'x-api-key' => $apiKey,
        ];

        if ($waitForSelector !== null) {
            $query['wait_for_selector'] = $waitForSelector;
        }

        if ($cookies !== null && trim($cookies) !== '') {
            $query['cookies'] = $cookies;
        }

        try {
            $response = $this->client->get('/v2/general', ['query' => $query]);
            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                Log::warning('ScrapingAnt returned non-200 status.', [
                    'url' => $url,
                    'status_code' => $statusCode,
                    'body' => $response->getBody()->getContents(),
                ]);

                return null;
            }

            $body = $response->getBody()->getContents();

            if (! is_string($body) || trim($body) === '') {
                Log::warning('ScrapingAnt returned empty body.', [
                    'url' => $url,
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
                'url' => $url,
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
