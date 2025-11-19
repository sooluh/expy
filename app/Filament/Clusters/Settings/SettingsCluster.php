<?php

namespace App\Filament\Clusters\Settings;

use BackedEnum;
use Filament\Clusters\Cluster;
use UnitEnum;

class SettingsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = 'tabler-settings';

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 132;
}
