<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'code',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
        ];
    }
}
