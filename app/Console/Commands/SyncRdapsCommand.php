<?php

namespace App\Console\Commands;

use App\Models\Rdap;
use App\Services\IanaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncRdapsCommand extends Command
{
    protected $signature = 'app:sync-rdaps';

    protected $description = 'Sync RDAP DNS services from IANA into the local database';

    public function __construct(private IanaService $ianaService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Fetching latest RDAP DNS services...');

        try {
            $services = $this->ianaService->fetchRdapServices();
        } catch (Throwable $exception) {
            $this->error('Failed to fetch RDAP services: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (empty($services)) {
            $this->warn('RDAP API returned an empty payload. Nothing to sync.');

            return self::INVALID;
        }

        $timestamp = now();

        $payload = collect($services)
            ->map(function (array $service) use ($timestamp) {
                return [
                    'tld' => $service['tld'],
                    'rdap' => $service['rdap'],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            })
            ->all();

        DB::transaction(function () use ($payload) {
            Rdap::query()->upsert(
                $payload,
                ['tld'],
                ['rdap', 'updated_at']
            );
        });

        $this->info('Synced '.count($payload).' RDAP DNS services.');

        return self::SUCCESS;
    }
}
