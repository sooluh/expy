<?php

namespace App\Filament\Pages;

use App\Models\User;
use Caresome\FilamentAuthDesigner\Pages\Auth\Register as BaseRegister;
use CodeWithDennis\SimpleAlert\Components\SimpleAlert;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class Register extends BaseRegister
{
    public function mount(): void
    {
        if (User::exists()) {
            redirect()->route('filament.studio.auth.login');

            return;
        }

        parent::mount();
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Create admin account';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SimpleAlert::make('first_time_setup')
                    ->info()
                    ->icon('tabler-help-octagon')
                    ->title('First time setup')
                    ->description('Since this is your first time setting up the application, please create an admin account.'),

                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    protected function handleRegistration(array $data): Model
    {
        if (User::exists()) {
            abort(403, 'Registration is disabled.');
        }

        return parent::handleRegistration([
            ...$data,
            'name' => '',
            'username' => preg_replace('/[^a-zA-Z0-9]/', '_', explode('@', $data['email'])[0]),
            'email_verified_at' => now(),
        ]);
    }
}
