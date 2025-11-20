<?php

namespace App\Observers;

use App\Jobs\SyncDomainJob;
use App\Models\Domain;
use App\Models\Registrar;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class DomainObserver
{
    public function saving(Domain $domain): void
    {
        if (! $domain->isDirty('domain_name') && ! $domain->isDirty('registrar_id')) {
            return;
        }

        $registrar = $this->resolveRegistrar($domain);

        if (! $registrar) {
            return;
        }

        [$feeId, $coTld] = $this->resolveRegistrarFeeId($domain->domain_name, $registrar);

        if (! $feeId && $coTld) {
            $this->notifyMissingFee($domain->domain_name, $registrar->name, $coTld);
        }

        $domain->registrar_fee_id = $feeId;
    }

    public function saved(Domain $domain): void
    {
        if (! $domain->wasRecentlyCreated && ! $domain->wasChanged('domain_name')) {
            return;
        }

        SyncDomainJob::dispatch($domain->id, Auth::id());
    }

    protected function resolveRegistrar(?Domain $domain): ?Registrar
    {
        if (! $domain || ! $domain->registrar_id) {
            return null;
        }

        return Registrar::find($domain->registrar_id);
    }

    protected function resolveRegistrarFeeId(string $domainName, Registrar $registrar): array
    {
        $parts = explode('.', strtolower($domainName));

        $partCount = count($parts);

        if ($partCount < 2) {
            return [null, null];
        }

        $coTldCandidate = null;

        if ($partCount >= 3) {
            $coTldCandidate = implode('.', array_slice($parts, -2));
        }

        $tldCandidate = $parts[$partCount - 1];

        $fees = $registrar->fees()->pluck('id', 'tld');
        $coTldMissing = false;

        if ($coTldCandidate) {
            if ($fees->has($coTldCandidate)) {
                return [$fees->get($coTldCandidate), $coTldCandidate];
            }

            $coTldMissing = true;
        }

        if ($fees->has($tldCandidate)) {
            return [$fees->get($tldCandidate), $coTldMissing ? $coTldCandidate : null];
        }

        return [null, $coTldMissing ? $coTldCandidate : null];
    }

    protected function notifyMissingFee(
        string $domainName,
        string $registrarName,
        string $tld
    ): void {
        $user = $this->user();

        if (! $user) {
            return;
        }

        Notification::make()
            ->title('Registrar fee not found')
            ->body("{$registrarName} has no fee for .{$tld} used by {$domainName}.")
            ->danger()
            ->sendToDatabase($user);
    }

    protected function user(): ?User
    {
        $userId = Auth::id();

        return $userId ? User::find($userId) : null;
    }
}
