<?php

namespace App\Services\Registrars;

use App\Concerns\RegistrarService;
use App\Models\Registrar;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DynadotService
{
    use RegistrarService;

    const BASE_URL = 'https://api.dynadot.com';

    protected Registrar $registrar;

    protected Client $client;

    public function __construct(Registrar $registrar)
    {
        $this->registrar = $registrar;

        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'timeout' => 30,
        ]);
    }

    public function isConfigured(): bool
    {
        return ! empty($this->getApiKey());
    }

    public function validateCredentials(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $apiKey = $this->getApiKey();

            Log::info('Dynadot API validation attempt', [
                'registrar_id' => $this->registrar->id,
                'api_key_length' => strlen($apiKey ?? ''),
                'api_key_exists' => ! empty($apiKey),
            ]);

            $response = $this->client->get('/restful/v1/tld/get_tld_price', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$apiKey}",
                ],
                'query' => [
                    'currency' => 'usd',
                    'count_per_page' => 1,
                ],
            ]);

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            Log::info('Dynadot API validation response', [
                'registrar_id' => $this->registrar->id,
                'status_code' => $response->getStatusCode(),
                'response_code' => $data['code'] ?? null,
                'response_message' => $data['message'] ?? null,
            ]);

            return $response->getStatusCode() === 200 && ($data['code'] ?? null) === 200;
        } catch (GuzzleException|Exception $e) {
            $statusCode = null;
            $responseBody = null;

            if ($e instanceof RequestException && $e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
            }

            Log::error('Dynadot credentials validation failed', [
                'registrar_id' => $this->registrar->id,
                'error' => $e->getMessage(),
                'status_code' => $statusCode,
                'response_body' => $responseBody,
            ]);

            return false;
        }
    }

    public function getPrices(): Collection
    {
        if (! $this->isConfigured()) {
            throw new Exception('Dynadot API is not properly configured');
        }

        try {
            $apiKey = $this->getApiKey();

            $response = $this->client->get('/restful/v1/tld/get_tld_price', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$apiKey}",
                ],
                'query' => [
                    'currency' => 'usd',
                ],
            ]);

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if ($response->getStatusCode() !== 200) {
                throw new Exception("API request failed: {$response->getStatusCode()}");
            }

            if (($data['code'] ?? null) !== 200) {
                $message = $data['message'] ?? 'Unknown error';
                $code = $data['code'] ?? 'no code';

                throw new Exception("API Error (code: {$code}): {$message}");
            }

            $tldList = $data['data']['tldPriceList'] ?? [];

            return collect($tldList)->map(function ($item) {
                return [
                    'tld' => ltrim($item['tld'], '.'),
                    'register_price' => $this->parsePrice($item['allYearsRegisterPrice'][0] ?? null),
                    'renew_price' => $this->parsePrice($item['allYearsRenewPrice'][0] ?? null),
                    'transfer_price' => $this->parsePrice($item['transferPrice'] ?? null),
                    'restore_price' => $this->parsePrice($item['restorePrice'] ?? null),
                    'privacy_price' => $item['supportPrivacy'] === 'Yes' ? 0.00 : null,
                    'misc_price' => $this->parsePrice($item['graceFeePrice'] ?? null),
                ];
            });
        } catch (GuzzleException|Exception $e) {
            $statusCode = null;
            $responseBody = null;

            if ($e instanceof RequestException && $e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
            }

            Log::error('Failed to fetch Dynadot prices', [
                'registrar_id' => $this->registrar->id,
                'error' => $e->getMessage(),
                'status_code' => $statusCode,
                'response_body' => $responseBody,
            ]);

            throw $e;
        }
    }

    public function getDomains(): Collection
    {
        if (! $this->isConfigured()) {
            throw new Exception('Dynadot API is not properly configured');
        }

        try {
            $apiKey = $this->getApiKey();

            $response = $this->client->get('/restful/v1/domains', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$apiKey}",
                ],
                'query' => [
                    'currency' => 'usd',
                ],
            ]);

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if ($response->getStatusCode() !== 200) {
                throw new Exception("API request failed: {$response->getStatusCode()}");
            }

            if (($data['code'] ?? null) !== 200) {
                $message = $data['message'] ?? 'Unknown error';
                $code = $data['code'] ?? 'no code';

                throw new Exception("API Error (code: {$code}): {$message}");
            }

            $domainList = $data['data']['domainInfo'] ?? [];

            return collect($domainList)->map(fn ($item) => $this->formatDomain($item));
        } catch (GuzzleException|Exception $e) {
            $statusCode = null;
            $responseBody = null;

            if ($e instanceof RequestException && $e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
            }

            Log::error('Failed to fetch Dynadot domains', [
                'registrar_id' => $this->registrar->id,
                'error' => $e->getMessage(),
                'status_code' => $statusCode,
                'response_body' => $responseBody,
            ]);

            throw $e;
        }
    }

    public function getDomain(string $domainName): array
    {
        if (! $this->isConfigured()) {
            throw new Exception('Dynadot API is not properly configured');
        }

        try {
            $apiKey = $this->getApiKey();

            $response = $this->client->get("/restful/v1/domains/{$domainName}", [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$apiKey}",
                ],
            ]);

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if ($response->getStatusCode() !== 200) {
                throw new Exception("API request failed: {$response->getStatusCode()}");
            }

            if (($data['code'] ?? null) !== 200) {
                $message = $data['message'] ?? 'Unknown error';
                $code = $data['code'] ?? 'no code';

                throw new Exception("API Error (code: {$code}): {$message}");
            }

            $info = $data['data']['domainInfo'][0] ?? null;

            return $info ? $this->formatDomain($info) : [];
        } catch (GuzzleException|Exception $e) {
            $statusCode = null;
            $responseBody = null;

            if ($e instanceof RequestException && $e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
            }

            Log::error('Failed to fetch Dynadot domain', [
                'registrar_id' => $this->registrar->id,
                'domain' => $domainName,
                'error' => $e->getMessage(),
                'status_code' => $statusCode,
                'response_body' => $responseBody,
            ]);

            throw $e;
        }
    }

    protected function formatDomain(array $item): array
    {
        $nameservers = collect($item['glueInfo']['name_server_settings']['name_servers'] ?? [])
            ->map(fn ($ns) => $ns['server_name'])
            ->toArray();

        return [
            'domain_name' => $item['domainName'],
            'registration_date' => $this->parseTimestamp($item['registration'] ?? null),
            'expiration_date' => $this->parseTimestamp($item['expiration'] ?? null),
            'nameservers' => $nameservers,
            'security_lock' => strtolower($item['locked'] ?? '') === 'yes',
            'whois_privacy' => in_array(strtolower($item['privacy'] ?? ''), ['full privacy', 'partial privacy']),
        ];
    }

    protected function parseTimestamp(?int $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        $asInt = (int) $value;

        return $asInt > 10_000_000_000
            ? Carbon::createFromTimestampMs($asInt)
            : Carbon::createFromTimestamp($asInt);
    }
}
