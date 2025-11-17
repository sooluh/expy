<?php

namespace App\Filament\Resources\Registrars;

use App\Filament\Resources\Registrars\Pages\ManageRegistrarFees;
use App\Filament\Resources\Registrars\Pages\ManageRegistrars;
use App\Filament\Resources\Registrars\RelationManagers\FeesRelationManager;
use App\Filament\Resources\Registrars\Schemas\RegistrarForm;
use App\Filament\Resources\Registrars\Tables\RegistrarsTable;
use App\Models\Registrar;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RegistrarResource extends Resource
{
    protected static ?string $model = Registrar::class;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-server-spark';

    public static function form(Schema $schema): Schema
    {
        return RegistrarForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RegistrarsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRegistrars::route('/'),
            'fees' => ManageRegistrarFees::route('/{record}/fees'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            FeesRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScope(SoftDeletingScope::class);
    }
}
