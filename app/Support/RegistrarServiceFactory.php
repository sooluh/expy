<?php

namespace App\Support;

use App\Enums\RegistrarCode;
use App\Models\Registrar;
use App\Services\Registrars\DynadotService;
use App\Services\Registrars\IdcloudhostService;
use App\Services\Registrars\IdwebhostService;
use App\Services\Registrars\PorkbunService;
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
