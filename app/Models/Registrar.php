<?php

namespace App\Models;

use App\Enums\RegistrarCode;
use App\Services\Registrars\RegistrarServiceFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Qopiku\FilamentSqids\Traits\HasSqids;

class Registrar extends Model
{
    use HasSqids, SoftDeletes;

    protected $fillable = [
        'currency_id',
        'name',
        'url',
        'notes',
        'api_support',
        'api_settings',
    ];

    protected function casts(): array
    {
        return [
            'api_support' => RegistrarCode::class,
            'api_settings' => 'array',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function fees(): HasMany
    {
        return $this->hasMany(RegistrarFee::class);
    }

    public function getService(): ?object
    {
        return RegistrarServiceFactory::make($this);
    }

    public function hasApiSupport(): bool
    {
        return RegistrarServiceFactory::hasApiSupport($this);
    }
}
