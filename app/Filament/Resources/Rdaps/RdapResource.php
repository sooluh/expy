<?php

namespace App\Filament\Resources\Rdaps;

use App\Filament\Resources\Rdaps\Pages\ManageRdaps;
use App\Filament\Resources\Rdaps\Schemas\RdapForm;
use App\Filament\Resources\Rdaps\Tables\RdapsTable;
use App\Models\Rdap;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class RdapResource extends Resource
{
    protected static ?string $model = Rdap::class;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-world-search';

    protected static string|UnitEnum|null $navigationGroup = 'External Integrations';

    protected static ?int $navigationSort = 99;

    public static function form(Schema $schema): Schema
    {
        return RdapForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RdapsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRdaps::route('/'),
        ];
    }
}
