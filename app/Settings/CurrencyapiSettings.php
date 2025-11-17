<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class CurrencyapiSettings extends Settings
{
    public ?string $api_key;

    public static function group(): string
    {
        return 'currencyapi';
    }

    public static function encrypted(): array
    {
        return ['api_key'];
    }
}
