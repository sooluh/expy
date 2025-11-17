<?php

namespace App\Services;

use Exception;
use Sqids\Sqids;

class SqidsService
{
    private static ?Sqids $instance = null;

    private const ALPHABET = config('filament-sqids.alphabet');

    private const MIN_LENGTH = config('filament-sqids.min_length');

    public static function getInstance(): Sqids
    {
        if (self::$instance === null) {
            self::$instance = new Sqids(
                alphabet: self::ALPHABET,
                minLength: self::MIN_LENGTH
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
}
