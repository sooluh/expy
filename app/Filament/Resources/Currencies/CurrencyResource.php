<?php

namespace App\Filament\Resources\Currencies;

use App\Filament\Resources\Currencies\Pages\ManageCurrencies;
use App\Filament\Resources\Currencies\Schemas\CurrencyForm;
use App\Filament\Resources\Currencies\Tables\CurrenciesTable;
use App\Models\Currency;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-currency-dollar';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return CurrencyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CurrenciesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCurrencies::route('/'),
        ];
    }
}
