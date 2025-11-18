<?php

namespace App\Services\Registrars;

use App\Concerns\RegistrarService;
use App\Models\Registrar;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DynadotService
{
    use RegistrarService;

    const BASE_URL = 'https://api.dynadot.com';

    protected ?string $apiKey = null;

    protected Registrar $registrar;

    protected Client $client;

    public function __construct(Registrar $registrar)
    {
        $this->registrar = $registrar;
        $this->apiKey = $this->getApiKey();

        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'timeout' => 30,
        ]);
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    public function validateCredentials(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $response = $this->client->get('/restful/v1/tld/get_tld_price', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$this->apiKey}",
                ],
                'query' => [
                    'currency' => 'usd',
                    'count_per_page' => 1,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $response->getStatusCode() === 200 && ($data['code'] ?? null) === 200;
        } catch (GuzzleException|Exception $e) {
            Log::error('Dynadot credentials validation failed', [
                'registrar_id' => $this->registrar->id,
                'error' => $e->getMessage(),
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
            $response = $this->client->get('/restful/v1/tld/get_tld_price', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$this->apiKey}",
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
                    'tld' => $item['tld'],
                    'register_price' => $this->parsePrice($item['allYearsRegisterPrice'][0] ?? null),
                    'renew_price' => $this->parsePrice($item['allYearsRenewPrice'][0] ?? null),
                    'transfer_price' => $this->parsePrice($item['transferPrice'] ?? null),
                    'restore_price' => $this->parsePrice($item['restorePrice'] ?? null),
                    'privacy_price' => $item['supportPrivacy'] === 'Yes' ? 0.00 : null,
                    'misc_price' => $this->parsePrice($item['graceFeePrice'] ?? null),
                ];
            });
        } catch (GuzzleException|Exception $e) {
            Log::error('Failed to fetch Dynadot prices', [
                'registrar_id' => $this->registrar->id,
                'error' => $e->getMessage(),
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
            $response = $this->client->get('/restful/v1/domains', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$this->apiKey}",
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

            return collect($domainList)->map(function ($item) {
                $nameservers = collect($item['glueInfo']['name_server_settings']['name_servers'] ?? [])
                    ->map(fn ($ns) => $ns['server_name'])
                    ->toArray();

                return [
                    'domain_name' => $item['domainName'],
                    'registration_date' => Carbon::createFromTimestamp($item['registration']),
                    'expiration_date' => Carbon::createFromTimestamp($item['expiration']),
                    'nameservers' => $nameservers,
                    'security_lock' => strtolower($item['locked']) === 'yes',
                    'whois_privacy' => in_array(strtolower($item['privacy']), ['full privacy', 'partial privacy']),
                ];
            });
        } catch (GuzzleException|Exception $e) {
            Log::error('Failed to fetch Dynadot domains', [
                'registrar_id' => $this->registrar->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
