<?php

namespace App\Services;

use App\Models\Rdap;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class IanaService
{
    public const BASE_URL = 'https://data.iana.org';

    public Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client([
            'base_uri' => self::BASE_URL,
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function fetchRdapServices(): ?array
    {
        try {
            $response = $this->client->get('/rdap/dns.json');

            if ($response->getStatusCode() !== 200) {
                Log::warning('IANA RDAP fetch returned non-200', [
                    'status_code' => $response->getStatusCode(),
                    'body' => $response->getBody()->getContents(),
                ]);

                return null;
            }
        } catch (GuzzleException $exception) {
            $statusCode = null;
            $responseBody = null;

            if ($exception instanceof RequestException && $exception->hasResponse()) {
                $statusCode = $exception->getResponse()->getStatusCode();
                $responseBody = $exception->getResponse()->getBody()->getContents();
            }

            Log::error('Failed to fetch RDAP data from IANA.', [
                'error' => $exception->getMessage(),
                'status_code' => $statusCode,
                'response_body' => $responseBody,
            ]);

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

    public function lookupDomainDetails(string $domainName): array
    {
        $tld = $this->extractTld($domainName);
        $rdapUrl = Rdap::where('tld', $tld)->value('rdap');

        if (! $rdapUrl) {
            throw new RuntimeException("RDAP URL not found for .{$tld}");
        }

        $client = new Client([
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        try {
            $rdapUrl = rtrim($rdapUrl, '/');
            $response = $client->get("{$rdapUrl}/domain/{$domainName}");
        } catch (GuzzleException $exception) {
            Log::error('Failed to query RDAP', [
                'domain' => $domainName,
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException('Failed to query RDAP', 0, $exception);
        }

        if ($response->getStatusCode() !== 200) {
            Log::warning('RDAP lookup returned non-200', [
                'domain' => $domainName,
                'status' => $response->getStatusCode(),
                'body' => $response->getBody()->getContents(),
            ]);

            throw new RuntimeException(
                'RDAP lookup failed with status '.$response->getStatusCode()
            );
        }

        $data = json_decode($response->getBody()->getContents(), true);

        if (! is_array($data)) {
            throw new RuntimeException('Invalid RDAP response');
        }

        $events = collect($data['events'] ?? []);

        $registrationDate = $this->parseEventDate($events, 'registration');
        $expirationDate = $this->parseEventDate($events, 'expiration');

        $nameservers = collect($data['nameservers'] ?? [])
            ->map(fn ($ns) => $ns['ldhName'] ?? null)
            ->filter()
            ->values()
            ->toArray();

        $statusList = collect($data['status'] ?? [])
            ->map(fn ($status) => strtolower((string) $status))
            ->values();

        $securityLock = $this->hasTransferLock($statusList);
        $whoisPrivacy = $this->hasPrivacyHints($data);

        return [
            'domain_name' => $domainName,
            'registration_date' => $registrationDate,
            'expiration_date' => $expirationDate,
            'nameservers' => $nameservers,
            'security_lock' => $securityLock,
            'whois_privacy' => $whoisPrivacy,
        ];
    }

    protected function hasTransferLock($statuses): bool
    {
        return collect($statuses)->contains(
            fn (string $status) => str_contains($status, 'transfer prohibited')
                || str_contains($status, 'transferprohibited')
                || str_contains($status, 'clienttransferprohibited')
        );
    }

    protected function hasPrivacyHints(array $data): bool
    {
        $haystack = strtolower(json_encode([
            $data['entities'] ?? [],
            $data['remarks'] ?? [],
            $data['notices'] ?? [],
            $data['redacted'] ?? [],
        ]) ?? '');

        return str_contains($haystack, 'redacted') || str_contains($haystack, 'privacy');
    }

    protected function extractTld(string $domain): string
    {
        $parts = explode('.', strtolower($domain));

        return count($parts) > 1 ? end($parts) : $domain;
    }

    protected function parseEventDate($events, string $action): ?CarbonImmutable
    {
        $event = collect($events)->firstWhere('eventAction', $action);

        if (! $event || empty($event['eventDate'])) {
            return null;
        }

        return CarbonImmutable::parse($event['eventDate']);
    }
}
