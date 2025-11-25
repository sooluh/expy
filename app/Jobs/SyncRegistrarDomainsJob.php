<?php

namespace App\Jobs;

use App\Enums\DomainSyncStatus;
use App\Models\Domain;
use App\Models\Registrar;
use App\Support\Concerns\NotifiesJobFailure;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SyncRegistrarDomainsJob implements ShouldQueue
{
    use NotifiesJobFailure, Queueable;

    public int $timeout = 180;

    public function __construct(public int $registrarId, public ?int $userId = null) {}

    public function handle(): void
    {
        $registrar = Registrar::find($this->registrarId);

        if (! $registrar) {
            Log::warning('SyncRegistrarDomainsJob skipped: registrar not found', [
                'registrar_id' => $this->registrarId,
            ]);

            return;
        }

        $hasSupport = $registrar->hasApiSupport();

        Log::info('SyncRegistrarDomainsJob registrar context', [
            'registrar_id' => $this->registrarId,
            'api_support' => $registrar->api_support,
            'has_api_support' => $hasSupport,
        ]);

        if (! $hasSupport) {
            return;
        }

        $service = $registrar->getService();

        if (! $service) {
            Log::warning('SyncRegistrarDomainsJob skipped: service not resolved', [
                'registrar_id' => $this->registrarId,
            ]);

            return;
        }

        if (! method_exists($service, 'getDomains')) {
            Log::warning('SyncRegistrarDomainsJob skipped: getDomains missing', [
                'registrar_id' => $this->registrarId,
                'service' => get_class($service),
            ]);

            return;
        }

        if (method_exists($service, 'isConfigured') && ! $service->isConfigured()) {
            Log::warning('SyncRegistrarDomainsJob skipped: service not configured', [
                'registrar_id' => $this->registrarId,
                'service' => get_class($service),
            ]);

            return;
        }

        Log::info('SyncRegistrarDomainsJob fetching domains', [
            'registrar_id' => $this->registrarId,
            'service' => get_class($service),
        ]);

        try {
            /** @var Collection $domains */
            $domains = $service->getDomains();

            foreach ($domains as $data) {
                $domainName = $data['domain_name'] ?? null;

                if (! $domainName) {
                    continue;
                }

                $domain = Domain::firstOrNew(['domain_name' => $domainName]);

                if (! $domain->exists) {
                    $domain->registrar_id = $registrar->id;
                    $domain->sync_status = DomainSyncStatus::PENDING;
                }

                $domain->fill([
                    'registration_date' => $data['registration_date'] ?? $domain->registration_date,
                    'expiration_date' => $data['expiration_date'] ?? $domain->expiration_date,
                    'nameservers' => $data['nameservers'] ?? $domain->nameservers,
                    'security_lock' => $data['security_lock'] ?? $domain->security_lock,
                    'whois_privacy' => $data['whois_privacy'] ?? $domain->whois_privacy,
                ]);

                if ($domain->isDirty()) {
                    $domain->save();
                } elseif (! $domain->exists) {
                    $domain->save();
                }

                SyncDomainJob::dispatch($domain->id, $this->userId);
            }

            $registrar->forceFill(['last_sync_at' => now()])->save();
        } catch (Exception $e) {
            Log::error('Failed to sync registrar domains', [
                'registrar_id' => $this->registrarId,
                'error' => $e->getMessage(),
            ]);

            $this->notifyFailure(
                $this->userId,
                'Domain Sync Failed',
                "Failed to sync domains for {$registrar->name}: {$e->getMessage()}"
            );
        }
    }
}
