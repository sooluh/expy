<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

class IanaService
{
    public const BASE_URL = 'https://data.iana.org';

    public Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client([
            'base_uri' => rtrim(self::BASE_URL, '/').'/',
            'headers' => [
                'Accept' => 'application/json',
            ],
            'http_errors' => true,
        ]);
    }

    public function fetchRdapServices(): ?array
    {
        try {
            $response = $this->client->get('rdap/dns.json');
        } catch (GuzzleException $exception) {
            throw new RuntimeException('Failed to fetch RDAP data from IANA.', 0, $exception);
        }

        $payload = json_decode($response->getBody()->getContents(), true);

        if (! is_array($payload)) {
            return [];
        }

        $services = $payload['services'] ?? [];

        if (! is_array($services)) {
            return [];
        }

        $rdaps = [];

        foreach ($services as $service) {
            $tlds = $service[0] ?? [];
            $rdapUrl = $service[1][0] ?? null;

            if ($rdapUrl) {
                foreach ($tlds as $tld) {
                    $rdaps[] = [
                        'tld' => $tld,
                        'rdap' => $rdapUrl,
                    ];
                }
            }
        }

        return $rdaps;
    }
}
