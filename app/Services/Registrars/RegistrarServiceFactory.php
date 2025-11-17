<?php

namespace App\Services\Registrars;

use App\Enums\ApiSupport;
use App\Models\Registrar;

class RegistrarServiceFactory
{
    public static function make(Registrar $registrar): ?object
    {
        return match ($registrar->api_support) {
            ApiSupport::DYNADOT => new DynadotService($registrar),
            ApiSupport::NONE => null,
            default => null,
        };
    }

    public static function hasApiSupport(Registrar $registrar): bool
    {
        return $registrar->api_support !== ApiSupport::NONE;
    }
}
