<?php

namespace App\Services\Registrars;

use App\Models\Registrar;
use App\Support\Concerns\RegistrarService;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PorkbunService
{
    use RegistrarService;

    const BASE_URL = 'https://api.porkbun.com';

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
        return ! empty($this->getApiKey()) && ! empty($this->getSecretKey());
    }

    public function validateCredentials(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $apiKey = $this->getApiKey();
            $secretKey = $this->getSecretKey();

            Log::info('Porkbun API validation attempt', [
                'registrar_id' => $this->registrar->id,
                'api_key_length' => strlen($apiKey ?? ''),
                'api_key_exists' => ! empty($apiKey),
                'secret_key_exists' => ! empty($secretKey),
            ]);

            $response = $this->client->post('/api/json/v3/ping', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'apikey' => $apiKey,
                    'secretapikey' => $secretKey,
                ],
            ]);

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            Log::info('Porkbun API validation response', [
                'registrar_id' => $this->registrar->id,
                'status_code' => $response->getStatusCode(),
                'response_status' => $data['status'] ?? null,
            ]);

            return $response->getStatusCode() === 200 && ($data['status'] ?? null) === 'SUCCESS';
        } catch (GuzzleException|Exception $e) {
            $statusCode = null;
            $responseBody = null;

            if ($e instanceof RequestException && $e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
            }

            Log::error('Porkbun credentials validation failed', [
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
        try {
            $response = $this->client->post('/api/json/v3/pricing/get', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if ($response->getStatusCode() !== 200) {
                throw new Exception("API request failed: {$response->getStatusCode()}");
            }

            if (($data['status'] ?? null) !== 'SUCCESS') {
                $message = $data['message'] ?? 'Unknown error';

                throw new Exception("API Error: {$message}");
            }

            $pricing = $data['pricing'] ?? [];

            return collect($pricing)
                ->filter(function ($priceData, $tld) {
                    return ($priceData['specialType'] ?? null) !== 'handshake';
                })
                ->map(function ($priceData, $tld) {
                    return [
                        'tld' => $tld,
                        'register_price' => $this->parsePrice($priceData['registration'] ?? null),
                        'renew_price' => $this->parsePrice($priceData['renewal'] ?? null),
                        'transfer_price' => $this->parsePrice($priceData['transfer'] ?? null),
                        'restore_price' => null,
                        'privacy_price' => null,
                        'misc_price' => null,
                    ];
                })
                ->values();
        } catch (GuzzleException|Exception $e) {
            $statusCode = null;
            $responseBody = null;

            if ($e instanceof RequestException && $e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
            }

            Log::error('Failed to fetch Porkbun prices', [
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
            throw new Exception('Porkbun API is not properly configured');
        }

        try {
            $apiKey = $this->getApiKey();
            $secretKey = $this->getSecretKey();

            $response = $this->client->get('/api/json/v3/domain/listAll', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'secretapikey' => $secretKey,
                    'apikey' => $apiKey,
                    'start' => '0',
                    'includeLabels' => 'no',
                ],
            ]);

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if ($response->getStatusCode() !== 200) {
                throw new Exception("API request failed: {$response->getStatusCode()}");
            }

            if (($data['status'] ?? null) !== 'SUCCESS') {
                $message = $data['message'] ?? 'Unknown error';
                $status = $data['status'] ?? 'no status';

                throw new Exception("API Error (status: {$status}): {$message}");
            }

            $domainList = $data['domains'] ?? [];

            return collect($domainList)->map(function ($item) {
                return [
                    'domain_name' => $item['domain'],
                    'registration_date' => Carbon::createFromTimestamp($item['createDate']),
                    'expiration_date' => Carbon::createFromTimestamp($item['expireDate']),
                    'nameservers' => [],
                    'security_lock' => $item['securityLock'] === '1',
                    'whois_privacy' => $item['whoisPrivacy'] === '1',
                ];
            });
        } catch (GuzzleException|Exception $e) {
            $statusCode = null;
            $responseBody = null;

            if ($e instanceof RequestException && $e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
            }

            Log::error('Failed to fetch Porkbun domains', [
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
        return [];
    }
}
