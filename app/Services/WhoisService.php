<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Iodev\Whois\Factory;
use RuntimeException;
use Throwable;

class WhoisService
{
    private const PRIVACY_KEYWORDS = [
        'redacted for privacy',
        'redacted for privac',
        'whoisprivacy',
        'whois protection',
        'whoisprotection',
        'privacy service',
        'data not disclosed',
        'contact privacy',
        'proxy',
        'whoisguard',
        'domain admin',
    ];

    public function lookupDomainDetails(string $domainName): array
    {
        $whois = Factory::get()->createWhois();

        try {
            $rawResponse = $whois->lookupDomain($domainName);
            $rawText = method_exists($rawResponse, 'getText')
                ? $rawResponse->getText()
                : (string) $rawResponse;
            $info = $whois->loadDomainInfo($domainName);
        } catch (Throwable $throwable) {
            throw new RuntimeException(
                'WHOIS lookup failed: '.$throwable->getMessage(),
                0,
                $throwable
            );
        }

        if (! $info) {
            throw new RuntimeException('WHOIS info not found for '.$domainName);
        }

        $registration = $info->creationDate ?? null;
        $expiration = $info->expirationDate ?? null;

        $hasLock = collect($info->states ?? [])
            ->map(fn ($state) => strtolower((string) $state))
            ->contains(fn ($state) => str_contains($state, 'transferprohibited'));

        $nameservers = collect($info->nameServers ?? [])
            ->filter()
            ->values()
            ->toArray();

        $whoisPrivacy = $this->detectPrivacy($rawText ?? null, $info);

        return [
            'domain_name' => $domainName,
            'registration_date' => $registration ? CarbonImmutable::createFromTimestamp($registration) : null,
            'expiration_date' => $expiration ? CarbonImmutable::createFromTimestamp($expiration) : null,
            'nameservers' => $nameservers,
            'security_lock' => $hasLock,
            'whois_privacy' => $whoisPrivacy,
        ];
    }

    protected function detectPrivacy(?string $rawText, object $info): bool
    {
        $haystack = strtolower($rawText ?? '');

        if ($haystack !== '') {
            foreach (self::PRIVACY_KEYWORDS as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return true;
                }
            }
        }

        $states = collect($info->states ?? [])
            ->map(fn ($state) => strtolower((string) $state));

        return $states->contains(fn (string $state) => str_contains($state, 'redacted'));
    }
}
