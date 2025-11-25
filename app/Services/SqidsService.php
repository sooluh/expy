<?php

namespace App\Services;

use Exception;
use Sqids\Sqids;

class SqidsService
{
    private static ?Sqids $instance = null;

    public static function getInstance(): Sqids
    {
        if (self::$instance === null) {
            self::$instance = new Sqids(
                alphabet: self::alphabet(),
                minLength: self::minLength()
            );
        }

        return self::$instance;
    }

    private function __construct() {}

    private function __clone() {}

    public function __wakeup()
    {
        throw new Exception('Cannot unserialize singleton');
    }

    private static function alphabet(): string
    {
        return (string) config('filament-sqids.alphabet', '');
    }

    private static function minLength(): int
    {
        return (int) config('filament-sqids.min_length', 0);
    }
}
