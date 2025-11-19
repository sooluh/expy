<?php

namespace App\Filament\Resources\Domains;

use App\Filament\Resources\Domains\Pages\ManageDomains;
use App\Filament\Resources\Domains\Schemas\DomainForm;
use App\Filament\Resources\Domains\Tables\DomainsTable;
use App\Models\Domain;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class DomainResource extends Resource
{
    protected static ?string $model = Domain::class;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-world';

    protected static string|UnitEnum|null $navigationGroup = 'Domain Management';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return DomainForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DomainsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDomains::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScope(SoftDeletingScope::class);
    }
}
