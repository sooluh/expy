<?php

namespace App\Models;

use App\Enums\DomainSyncStatus;
use App\Observers\DomainObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Qopiku\FilamentSqids\Traits\HasSqids;

#[ObservedBy(DomainObserver::class)]
class Domain extends Model
{
    use HasSqids, SoftDeletes;

    protected $fillable = [
        'registrar_id',
        'registrar_fee_id',
        'domain_name',
        'registration_date',
        'expiration_date',
        'nameservers',
        'security_lock',
        'whois_privacy',
        'sync_status',
    ];

    protected function casts(): array
    {
        return [
            'registration_date' => 'date',
            'expiration_date' => 'date',
            'nameservers' => 'array',
            'security_lock' => 'boolean',
            'whois_privacy' => 'boolean',
            'sync_status' => DomainSyncStatus::class,
        ];
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(Registrar::class);
    }

    public function registrarFee(): BelongsTo
    {
        return $this->belongsTo(RegistrarFee::class);
    }
}
