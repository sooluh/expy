<?php

namespace App\Services;

use App\Settings\ScrapingantSettings;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

class ScrapingantService
{
    public const BASE_URL = 'https://api.scrapingant.com/v2';

    private Client $client;

    public function __construct(
        private ScrapingantSettings $settings,
        ?Client $client = null
    ) {
        $this->client = $client ?? new Client([
            'base_uri' => self::BASE_URL,
            'http_errors' => true,
        ]);
    }

    public function scrape(string $url, ?string $waitForSelector = null): ?string
    {
        $query = [
            'url' => $url,
            'x-api-key' => $this->getApiKey(),
        ];

        if ($waitForSelector !== null) {
            $query['wait_for_selector'] = $waitForSelector;
        }

        try {
            $response = $this->client->get('/general', ['query' => $query]);
        } catch (GuzzleException $exception) {
            throw new RuntimeException('Failed to fetch data from ScrapingAnt.', 0, $exception);
        }

        return $response->getBody()->getContents();
    }

    public function getApiKey(): string
    {
        $apiKey = $this->settings->api_key;

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('ScrapingAnt API key is not configured.');
        }

        return $apiKey;
    }
}
