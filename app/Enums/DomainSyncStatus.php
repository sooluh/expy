<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DomainSyncStatus: int implements HasLabel
{
    case PENDING = 0;
    case SYNC_INTEGRATION = 1;
    case SYNC_RDAP = 2;
    case COMPLETED = 3;
    case FAILED_SYNC_INTEGRATION = 4;
    case FAILED_SYNC_RDAP = 5;

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::SYNC_INTEGRATION => 'Syncing Integration',
            self::SYNC_RDAP => 'Syncing RDAP',
            self::COMPLETED => 'Completed',
            self::FAILED_SYNC_INTEGRATION => 'Failed',
            self::FAILED_SYNC_RDAP => 'Failed',
        };
    }
}
