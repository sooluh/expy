<?php

namespace App\Filament\Resources\Registrars\Schemas;

use App\Enums\ApiSupport;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class RegistrarForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->label('Registrar Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('url')
                    ->label('Registrar URL')
                    ->required()
                    ->url(),

                Select::make('currency_id')
                    ->label('Currency')
                    ->required()
                    ->native(false)
                    ->relationship('currency', 'code')
                    ->searchable(),

                TextInput::make('notes')
                    ->label('Notes')
                    ->nullable(),

                Select::make('api_support')
                    ->label('API Support')
                    ->required()
                    ->native(false)
                    ->live()
                    ->options(ApiSupport::class)
                    ->default(ApiSupport::NONE)
                    ->afterStateUpdated(function (callable $set, ApiSupport|int|string|null $state): void {
                        if (self::resolveApiSupport($state) !== ApiSupport::DYNADOT) {
                            $set('api_settings.api_key', null);
                        }
                    }),

                TextInput::make('api_settings.api_key')
                    ->label('Dynadot API Key')
                    ->password()
                    ->revealable()
                    ->visible(fn (callable $get) => self::resolveApiSupport($get('api_support')) === ApiSupport::DYNADOT)
                    ->dehydrated()
                    ->afterStateHydrated(function (TextInput $component, $state): void {
                        if (blank($state)) {
                            return;
                        }

                        try {
                            $component->state(Crypt::decryptString($state));
                        } catch (DecryptException) {
                            $component->state(null);
                        }
                    })
                    ->dehydrateStateUsing(function (?string $state) {
                        if (blank($state)) {
                            return null;
                        }

                        return Crypt::encryptString($state);
                    }),
            ]);
    }

    private static function resolveApiSupport(ApiSupport|int|string|null $value): ?ApiSupport
    {
        if ($value instanceof ApiSupport) {
            return $value;
        }

        if (blank($value) || ! is_numeric($value)) {
            return null;
        }

        return ApiSupport::tryFrom((int) $value);
    }
}
