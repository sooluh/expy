<?php

namespace App\Concerns;

use App\Enums\ApiSupport;

trait RegistrarService
{
    protected static function resolveApiSupport(mixed $value): ApiSupport
    {
        if ($value instanceof ApiSupport) {
            return $value;
        }

        if (is_string($value)) {
            return ApiSupport::tryFrom($value) ?? ApiSupport::NONE;
        }

        return ApiSupport::NONE;
    }

    protected static function requiresApiKey(mixed $apiSupport): bool
    {
        $support = static::resolveApiSupport($apiSupport);

        return match ($support) {
            ApiSupport::DYNADOT => true,
            ApiSupport::PORKBUN => true,
            ApiSupport::NONE => false,
            default => false,
        };
    }
}
