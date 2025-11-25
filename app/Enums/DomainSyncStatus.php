<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum DomainSyncStatus: int implements HasColor, HasIcon, HasLabel
{
    case PENDING = 0;
    case SYNC_INTEGRATION = 1;
    case SYNC_RDAP = 2;
    case SYNC_WHOIS = 3;
    case COMPLETED = 4;
    case FAILED_SYNC_INTEGRATION = 5;
    case FAILED_SYNC_RDAP = 6;
    case FAILED_SYNC_WHOIS = 7;

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::SYNC_INTEGRATION => 'Syncing Integration',
            self::SYNC_RDAP => 'Syncing RDAP',
            self::SYNC_WHOIS => 'Syncing WHOIS',
            self::COMPLETED => null,
            self::FAILED_SYNC_INTEGRATION => 'Failed',
            self::FAILED_SYNC_RDAP => 'Failed',
            self::FAILED_SYNC_WHOIS => 'Failed',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDING => 'tabler-clock',
            self::SYNC_INTEGRATION, self::SYNC_RDAP, self::SYNC_WHOIS => 'tabler-refresh-dot',
            self::COMPLETED => null,
            self::FAILED_SYNC_INTEGRATION,
            self::FAILED_SYNC_RDAP,
            self::FAILED_SYNC_WHOIS => 'tabler-alert-triangle',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::SYNC_INTEGRATION, self::SYNC_RDAP, self::SYNC_WHOIS => 'info',
            self::COMPLETED => null,
            self::FAILED_SYNC_INTEGRATION,
            self::FAILED_SYNC_RDAP,
            self::FAILED_SYNC_WHOIS => 'warning',
        };
    }
}
