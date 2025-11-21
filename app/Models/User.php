<?php

namespace App\Models;

use App\Support\Concerns\HasAvatar;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Qopiku\FilamentSqids\Traits\HasSqids;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmail
{
    use HasAvatar, HasSqids, Notifiable, SoftDeletes;

    protected $fillable = [
        'username',
        'email',
        'password',
        'name',
        'avatar',
        'settings',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'settings' => 'array',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
            'email_verified_at' => 'datetime',
        ];
    }

    public function isVerified(): Attribute
    {
        return Attribute::get(fn () => (bool) $this->email_verified_at);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_verified;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url;
    }

    public function settings(array $revisions): self
    {
        $this->settings = array_merge($this->settings ?? [], $revisions);
        $this->save();

        return $this;
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }
}
