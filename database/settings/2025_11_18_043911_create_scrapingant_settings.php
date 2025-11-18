<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->addEncrypted('scrapingant.api_key', env('SCRAPINGANT_API_KEY', null));
    }
};
