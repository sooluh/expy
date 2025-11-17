<?php

namespace App\Filament\Resources\Registrars\Schemas;

use App\Concerns\RegistrarService;
use App\Enums\ApiSupport;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class RegistrarForm
{
    use RegistrarService;

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
                        $resolved = self::resolveApiSupport($state);
                        if ($resolved !== ApiSupport::DYNADOT) {
                            $set('api_settings.api_key', null);
                        }
                        if ($resolved !== ApiSupport::PORKBUN) {
                            $set('api_settings.secret_key', null);
                        }
                    }),

                TextInput::make('api_settings.api_key')
                    ->label(fn (callable $get) => self::resolveApiSupport($get('api_support')) === ApiSupport::DYNADOT ? 'API Production Key' : 'API Key')
                    ->password()
                    ->revealable()
                    ->visible(fn (callable $get) => in_array(self::resolveApiSupport($get('api_support')), [ApiSupport::DYNADOT, ApiSupport::PORKBUN]))
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

                TextInput::make('api_settings.secret_key')
                    ->label('Secret Key')
                    ->password()
                    ->revealable()
                    ->visible(fn (callable $get) => self::resolveApiSupport($get('api_support')) === ApiSupport::PORKBUN)
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
}
