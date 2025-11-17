<?php

namespace App\Services\Registrars;

use App\Concerns\RegistrarService;
use App\Models\Registrar;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PorkbunService
{
    use RegistrarService;

    const BASE_URL = 'https://api.porkbun.com';

    protected ?string $apiKey = null;

    protected ?string $secretKey = null;

    protected Registrar $registrar;

    protected Client $client;

    public function __construct(Registrar $registrar)
    {
        $this->registrar = $registrar;
        $this->apiKey = $this->getApiKey();
        $this->secretKey = $this->getSecretKey();
        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'timeout' => 30,
        ]);
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey) && ! empty($this->secretKey);
    }

    public function validateCredentials(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $response = $this->client->post('/api/json/v3/ping', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'apikey' => $this->apiKey,
                    'secretapikey' => $this->secretKey,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $response->getStatusCode() === 200 && ($data['status'] ?? null) === 'SUCCESS';
        } catch (GuzzleException|Exception $e) {
            Log::error('Porkbun credentials validation failed', [
                'registrar_id' => $this->registrar->id,
                'error' => $e->getMessage(),
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
            Log::error('Failed to fetch Porkbun prices', [
                'registrar_id' => $this->registrar->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
