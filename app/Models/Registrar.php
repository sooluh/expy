<?php

namespace App\Models;

use App\Enums\ApiSupport;
use Illuminate\Database\Eloquent\Model;
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
            'api_support' => ApiSupport::class,
            'api_settings' => 'array',
        ];
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
}
