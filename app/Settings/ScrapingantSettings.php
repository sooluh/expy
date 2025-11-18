<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class ScrapingantSettings extends Settings
{
    public ?string $api_key;

    public static function group(): string
    {
        return 'scrapingant';
    }

    public static function encrypted(): array
    {
        return ['api_key'];
    }
}
