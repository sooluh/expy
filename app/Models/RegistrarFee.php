<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrarFee extends Model
{
    protected $fillable = [
        'registrar_id',
        'tld',
        'register_price',
        'renew_price',
        'transfer_price',
        'restore_price',
        'privacy_price',
        'misc_price',
    ];

    protected $casts = [
        'register_price' => 'decimal:2',
        'renew_price' => 'decimal:2',
        'transfer_price' => 'decimal:2',
        'restore_price' => 'decimal:2',
        'privacy_price' => 'decimal:2',
        'misc_price' => 'decimal:2',
    ];

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(Registrar::class);
    }
}
