<?php

namespace App\Filament\Resources\Registrars\Schemas;

use App\Enums\RegistrarCode;
use App\Support\Concerns\RegistrarService;
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
                    ->label('Registrar name')
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
                    ->label('API support')
                    ->required()
                    ->native(false)
                    ->live()
                    ->options(RegistrarCode::class)
                    ->default(RegistrarCode::NONE)
                    ->afterStateUpdated(function (callable $set, RegistrarCode|int|string|null $state): void {
                        $resolved = self::resolveApiSupport($state);

                        if ($resolved !== RegistrarCode::DYNADOT) {
                            $set('api_settings.api_key', null);
                        }

                        if ($resolved !== RegistrarCode::PORKBUN) {
                            $set('api_settings.secret_key', null);
                        }

                        if ($resolved !== RegistrarCode::IDWEBHOST) {
                            $set('api_settings.cookies', null);
                        }
                    }),

                TextInput::make('api_settings.api_key')
                    ->label(fn (callable $get) => self::resolveApiSupport($get('api_support')) === RegistrarCode::DYNADOT ? 'API production key' : 'API key')
                    ->password()
                    ->revealable()
                    ->visible(fn (callable $get) => in_array(self::resolveApiSupport($get('api_support')), [RegistrarCode::DYNADOT, RegistrarCode::PORKBUN]))
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
                    ->label('Secret key')
                    ->password()
                    ->revealable()
                    ->visible(fn (callable $get) => self::resolveApiSupport($get('api_support')) === RegistrarCode::PORKBUN)
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

                TextInput::make('api_settings.cookies')
                    ->label('Cookies')
                    ->belowContent('Format: cookie_name_1=cookie_value_1;cookie_name_2=...')
                    ->password()
                    ->revealable()
                    ->visible(fn (callable $get) => in_array(self::resolveApiSupport($get('api_support')), [RegistrarCode::IDWEBHOST, RegistrarCode::IDCLOUDHOST]))
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
