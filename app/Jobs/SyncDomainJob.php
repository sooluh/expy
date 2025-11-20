<?php

namespace App\Jobs;

use App\Enums\DomainSyncStatus;
use App\Enums\RegistrarCode;
use App\Models\Domain;
use App\Models\User;
use App\Services\IanaService;
use App\Services\WhoisService;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Throwable;

class SyncDomainJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $domainId, public ?int $userId = null) {}

    public function handle(IanaService $ianaService, WhoisService $whoisService): void
    {
        $domain = Domain::with('registrar')->find($this->domainId);

        if (! $domain) {
            $this->notify('Domain not found', 'danger');

            return;
        }

        $details = [];

        $registrar = $domain->registrar;

        if ($registrar && $registrar->api_support !== RegistrarCode::NONE) {
            $this->setStatus($domain, DomainSyncStatus::SYNC_INTEGRATION);

            try {
                $service = $registrar->getService();

                if ($service && method_exists($service, 'getDomain')) {
                    $details = $this->mergeDetails(
                        $details,
                        $service->getDomain($domain->domain_name) ?? []
                    );
                }
            } catch (Throwable $exception) {
                $domain->sync_status = DomainSyncStatus::FAILED_SYNC_INTEGRATION;
                $domain->save();

                $message = "Failed to sync integration for {$domain->domain_name}: "
                    .$exception->getMessage();

                $this->notify($message, 'danger');

                return;
            }

            if ($this->isComplete($details)) {
                $this->finalize($domain, $details);

                return;
            }
        }

        $this->setStatus($domain, DomainSyncStatus::SYNC_RDAP);

        try {
            $details = $this->mergeDetails(
                $details,
                $ianaService->lookupDomainDetails($domain->domain_name)
            );
        } catch (Throwable $exception) {
            $this->setStatus($domain, DomainSyncStatus::FAILED_SYNC_RDAP);

            $this->notify(
                "Failed to sync RDAP for {$domain->domain_name}: "
                .$exception->getMessage(),
                'warning'
            );
        }

        if ($this->isComplete($details)) {
            $this->finalize($domain, $details);

            return;
        }

        $this->setStatus($domain, DomainSyncStatus::SYNC_WHOIS);

        try {
            $details = $this->mergeDetails(
                $details,
                $whoisService->lookupDomainDetails($domain->domain_name)
            );
        } catch (Throwable $exception) {
            $this->setStatus($domain, DomainSyncStatus::FAILED_SYNC_WHOIS);

            $this->notify(
                "Failed to sync WHOIS for {$domain->domain_name}: {$exception->getMessage()}",
                'danger'
            );

            return;
        }

        $this->finalize($domain, $details);
    }

    protected function notify(string $message, string $status): void
    {
        if (! $this->userId) {
            return;
        }

        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        Notification::make()
            ->title('Domain Sync')
            ->body($message)
            ->status($status)
            ->sendToDatabase($user);
    }

    protected function mergeDetails(array $current, array $incoming): array
    {
        $merged = $current;

        foreach ($incoming as $key => $value) {
            if ($value === null) {
                continue;
            }

            if ($key === 'nameservers') {
                $ns = collect($value ?? [])->filter()->values()->toArray();
                if (! empty($ns)) {
                    $merged[$key] = $ns;
                }

                continue;
            }

            $merged[$key] = $value;
        }

        return $merged;
    }

    protected function isComplete(array $details): bool
    {
        $registration = Arr::get($details, 'registration_date');
        $expiration = Arr::get($details, 'expiration_date');
        $nameservers = Arr::get($details, 'nameservers', []);

        return $registration !== null
            && $expiration !== null
            && ! empty($nameservers);
    }

    protected function finalize(Domain $domain, array $details): void
    {
        $domain->fill([
            'registration_date' => $details['registration_date'] ?? null,
            'expiration_date' => $details['expiration_date'] ?? null,
            'nameservers' => $details['nameservers'] ?? [],
            'security_lock' => $details['security_lock'] ?? true,
            'whois_privacy' => $details['whois_privacy'] ?? true,
            'sync_status' => DomainSyncStatus::COMPLETED,
        ])->save();

        $this->notify("Synced {$domain->domain_name} successfully.", 'success');
    }

    protected function setStatus(Domain $domain, DomainSyncStatus $status): void
    {
        $domain->sync_status = $status;
        $domain->save();
    }
}
