<?php

namespace App\Services;

use App\Settings\CurrencyapiSettings;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

class CurrencyapiService
{
    public const BASE_URL = 'https://api.currencyapi.com/v3/';

    private Client $client;

    public function __construct(
        private CurrencyapiSettings $settings,
        ?Client $client = null
    ) {
        $this->client = $client ?? new Client([
            'base_uri' => rtrim(self::BASE_URL, '/').'/',
            'headers' => [
                'Accept' => 'application/json',
            ],
            'http_errors' => true,
        ]);
    }

    public function latest(): array
    {
        try {
            $response = $this->client->get('latest', [
                'headers' => [
                    'apikey' => $this->getApiKey(),
                ],
            ]);
        } catch (GuzzleException $exception) {
            throw new RuntimeException('Failed to fetch latest currencies.', 0, $exception);
        }

        $payload = json_decode($response->getBody()->getContents(), true);

        if (! is_array($payload)) {
            return [];
        }

        $data = $payload['data'] ?? [];

        if (! is_array($data)) {
            return [];
        }

        $currencies = [];

        foreach ($data as $item) {
            $code = $item['code'] ?? null;
            $value = $item['value'] ?? null;

            if ($code === null || $value === null) {
                continue;
            }

            $currencies[] = [
                'code' => $code,
                'value' => $value,
            ];
        }

        return $currencies;
    }

    public function getApiKey(): string
    {
        $payload = $this->settings->api_key;

        if (! is_string($payload) || trim($payload) === '') {
            throw new RuntimeException('Currencyapi API key is not configured.');
        }

        return $payload;
    }
}
