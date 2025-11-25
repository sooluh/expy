<?php

namespace App\Filament\Resources\Registrars\Schemas;

use App\Enums\RegistrarCode;
use App\Support\Concerns\RegistrarService;
use CodeWithDennis\SimpleAlert\Components\SimpleAlert;
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
                        $resolved = self::resolveRegistrar($state);

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

                SimpleAlert::make('scrapingant_alert')
                    ->warning()
                    ->icon('tabler-alert-triangle')
                    ->title('ScrapingAnt fallback required')
                    ->description('This registrar relies on ScrapingAnt for fallback. Please ensure ScrapingAnt API access is configured.')
                    ->visible(fn (callable $get) => in_array(self::resolveRegistrar($get('api_support')), [
                        RegistrarCode::IDWEBHOST,
                        RegistrarCode::IDCLOUDHOST,
                    ])),

                TextInput::make('api_settings.api_key')
                    ->label('API key')
                    ->password()
                    ->revealable()
                    ->dehydrated()
                    ->visible(fn (callable $get) => in_array(self::resolveRegistrar($get('api_support')), [
                        RegistrarCode::DYNADOT,
                        RegistrarCode::PORKBUN,
                    ]))
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
                    ->dehydrated()
                    ->visible(fn (callable $get) => in_array(self::resolveRegistrar($get('api_support')), [
                        RegistrarCode::PORKBUN,
                    ]))
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
                    ->dehydrated()
                    ->visible(fn (callable $get) => in_array(self::resolveRegistrar($get('api_support')), [
                        RegistrarCode::IDWEBHOST,
                        RegistrarCode::IDCLOUDHOST,
                    ]))
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
