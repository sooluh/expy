<?php

namespace App\Filament\Resources\Registrars\Schemas;

use App\Filament\Resources\Registrars\Pages\ManageRegistrarFees;
use App\Settings\GeneralSettings;
use Filament\Forms\Components\TextInput;

class RegistrarFeeForm
{
    public static function configure(ManageRegistrarFees $page, bool $isEditing = false): array
    {
        return [
            TextInput::make('tld')
                ->label('TLD')
                ->placeholder('com, org, my.id, co.id, etc.')
                ->required()
                ->prefix('.')
                ->regex('/^[a-z0-9]+(?:\\.[a-z0-9-]+)*$/i')
                ->maxLength(50)
                ->disabled($isEditing)
                ->dehydrated(! $isEditing)
                ->unique(
                    table: 'registrar_fees',
                    column: 'tld',
                    ignorable: fn ($record) => $record,
                    modifyRuleUsing: function ($rule) use ($page) {
                        return $rule->where('registrar_id', $page->getOwnerRecord()->id);
                    }
                ),

            TextInput::make('register_price')
                ->label('Register price')
                ->numeric()
                ->minValue(0)
                ->dehydrateStateUsing(fn ($state) => $page->convertDisplayToRegistrar($state))
                ->columnSpan(1)
                ->prefix(fn () => self::currencyCode($page)),

            TextInput::make('renew_price')
                ->label('Renewal price')
                ->numeric()
                ->minValue(0)
                ->dehydrateStateUsing(fn ($state) => $page->convertDisplayToRegistrar($state))
                ->columnSpan(1)
                ->prefix(fn () => self::currencyCode($page)),

            TextInput::make('transfer_price')
                ->label('Transfer price')
                ->numeric()
                ->minValue(0)
                ->dehydrateStateUsing(fn ($state) => $page->convertDisplayToRegistrar($state))
                ->columnSpan(1)
                ->prefix(fn () => self::currencyCode($page)),

            TextInput::make('restore_price')
                ->label('Restore price')
                ->numeric()
                ->minValue(0)
                ->dehydrateStateUsing(fn ($state) => $page->convertDisplayToRegistrar($state))
                ->columnSpan(1)
                ->prefix(fn () => self::currencyCode($page)),

            TextInput::make('privacy_price')
                ->label('Privacy protection price')
                ->numeric()
                ->minValue(0)
                ->dehydrateStateUsing(fn ($state) => $page->convertDisplayToRegistrar($state))
                ->columnSpan(1)
                ->prefix(fn () => self::currencyCode($page)),

            TextInput::make('misc_price')
                ->label('Miscellaneous price')
                ->numeric()
                ->minValue(0)
                ->dehydrateStateUsing(fn ($state) => $page->convertDisplayToRegistrar($state))
                ->columnSpan(1)
                ->prefix(fn () => self::currencyCode($page)),
        ];
    }

    protected static function currencyCode(ManageRegistrarFees $page): string
    {
        return $page->getOwnerRecord()->currency->code ?? app(GeneralSettings::class)->currency;
    }
}
