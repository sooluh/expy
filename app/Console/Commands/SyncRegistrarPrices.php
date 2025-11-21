<?php

namespace App\Console\Commands;

use App\Enums\RegistrarCode;
use App\Models\Registrar;
use App\Support\Concerns\SyncsRegistrarFees;
use Exception;
use Illuminate\Console\Command;

class SyncRegistrarPrices extends Command
{
    use SyncsRegistrarFees;

    protected $signature = 'registrar:sync-prices {registrar? : The registrar ID or "all" for all registrars}';

    protected $description = 'Synchronize TLD prices from registrar API';

    public function handle()
    {
        $registrarArg = $this->argument('registrar');

        if ($registrarArg === 'all' || $registrarArg === null) {
            return $this->syncAllRegistrars();
        }

        return $this->syncRegistrar($registrarArg);
    }

    protected function syncAllRegistrars(): int
    {
        $registrars = Registrar::where('api_support', '!=', RegistrarCode::NONE)->get();

        if ($registrars->isEmpty()) {
            $this->info('No registrars with API support found.');

            return self::SUCCESS;
        }

        $this->info("Syncing {$registrars->count()} registrar(s)...");

        foreach ($registrars as $registrar) {
            $this->syncRegistrar($registrar->id);
        }

        return self::SUCCESS;
    }

    protected function syncRegistrar(int|string $registrarId): int
    {
        $registrar = Registrar::find($registrarId);

        if (! $registrar) {
            $this->error("Registrar #{$registrarId} not found.");

            return self::FAILURE;
        }

        if (! $registrar->hasApiSupport()) {
            $this->error("{$registrar->name} does not have API support enabled.");

            return self::FAILURE;
        }

        $service = $registrar->getService();

        if (! $service || ! $service->isConfigured()) {
            $this->error("{$registrar->name} API is not properly configured.");

            return self::FAILURE;
        }

        $this->info("Syncing prices for {$registrar->name}...");

        try {
            $prices = $service->getPrices();
            $newCount = 0;
            $updatedCount = 0;

            foreach ($prices as $priceData) {
                $payload = $this->normalizeFeePayload($priceData);

                if (! $payload['tld']) {
                    continue;
                }

                $fee = $registrar->fees()->where('tld', $payload['tld'])->first();

                if ($fee) {
                    if ($this->feeHasChanges($fee, $payload)) {
                        $fee->update($payload);
                        $updatedCount++;
                    }
                } else {
                    $registrar->fees()->create($payload);
                    $newCount++;
                }
            }

            if ($service->supportsDeferredPriceSync()) {
                $service->dispatchDeferredPriceSync();
                $this->info("Queued additional price sync tasks for {$registrar->name}.");
            }

            $this->info("{$registrar->name}: {$newCount} new, {$updatedCount} updated");

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error("Failed to sync {$registrar->name}: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
