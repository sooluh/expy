<?php

namespace App\Filament\Pages;

use App\Models\User;
use Caresome\FilamentAuthDesigner\Pages\Auth\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function mount(): void
    {
        if (! User::exists()) {
            redirect()->route('filament.studio.auth.register');

            return;
        }

        parent::mount();
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('username')
            ->label(__('Username'))
            ->required()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        $login_type = filter_var($data['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        return [$login_type => $data['username'], 'password' => $data['password']];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.username' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }
}
