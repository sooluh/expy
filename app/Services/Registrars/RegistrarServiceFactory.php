<?php

namespace App\Services\Registrars;

use App\Enums\RegistrarCode;
use App\Models\Registrar;
use App\Services\ScrapingantService;

class RegistrarServiceFactory
{
    public static function make(Registrar $registrar): ?object
    {
        return match ($registrar->api_support) {
            RegistrarCode::DYNADOT => new DynadotService($registrar),
            RegistrarCode::PORKBUN => new PorkbunService($registrar),
            RegistrarCode::IDWEBHOST => new IdwebhostService($registrar, app(ScrapingantService::class)),
            RegistrarCode::IDCLOUDHOST => new IdcloudhostService($registrar, app(ScrapingantService::class)),
            RegistrarCode::NONE => null,
            default => null,
        };
    }

    public static function hasApiSupport(Registrar $registrar): bool
    {
        return $registrar->api_support !== RegistrarCode::NONE;
    }
}
