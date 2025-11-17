<?php

namespace App\Console\Commands;

use App\Models\Currency;
use App\Services\CurrencyapiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncCurrencies extends Command
{
    protected $signature = 'app:sync-currencies';

    protected $description = 'Sync currencies from CurrencyAPI into the local database';

    public function __construct(private CurrencyapiService $currencyapiService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Fetching latest currency rates...');

        try {
            $currencies = $this->currencyapiService->latest();
        } catch (Throwable $exception) {
            $this->error('Failed to fetch currency rates: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (empty($currencies)) {
            $this->warn('CurrencyAPI returned an empty payload. Nothing to sync.');

            return self::INVALID;
        }

        $timestamp = now();

        $payload = collect($currencies)
            ->map(function (array $currency) use ($timestamp) {
                return [
                    'code' => $currency['code'],
                    'value' => (float) $currency['value'],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            })
            ->all();

        DB::transaction(function () use ($payload) {
            Currency::query()->upsert(
                $payload,
                ['code'],
                ['value', 'updated_at']
            );
        });

        $this->info('Synced '.count($payload).' currencies.');

        return self::SUCCESS;
    }
}
