<?php

namespace App\Models;

use App\Concerns\HasAvatar;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Qopiku\FilamentSqids\Traits\HasSqids;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    use HasAvatar, HasSqids, Notifiable, SoftDeletes;

    protected $fillable = [
        'username',
        'email',
        'password',
        'name',
        'avatar',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
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
}
