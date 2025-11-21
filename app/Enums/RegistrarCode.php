<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RegistrarCode: int implements HasLabel
{
    case NONE = 0;
    // case CLOUDFLARE = 1;
    case DYNADOT = 2;
    case PORKBUN = 3;
    // case NAMECHEAP = 4;
    // case GODADDY = 5;
    case IDWEBHOST = 6;
    case IDCLOUDHOST = 7;

    public function getLabel(): string
    {
        return match ($this) {
            self::NONE => 'None',
            // self::CLOUDFLARE => 'Cloudflare',
            self::DYNADOT => 'Dynadot',
            self::PORKBUN => 'Porkbun',
            // self::NAMECHEAP => 'Namecheap',
            // self::GODADDY => 'GoDaddy',
            self::IDWEBHOST => 'IDwebhost',
            self::IDCLOUDHOST => 'IDCloudHost',
        };
    }
}
