<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->addEncrypted('currencyapi.api_key', env('CURRENCYAPI_API_KEY', null));
    }
};
