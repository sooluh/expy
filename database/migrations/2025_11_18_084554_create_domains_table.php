<?php

use App\Enums\DomainSyncStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registrar_id')->constrained()->cascadeOnDelete();
            $table->string('domain_name')->unique();
            $table->date('registration_date');
            $table->date('expiration_date');
            $table->json('nameservers');
            $table->boolean('security_lock')->default(false);
            $table->boolean('whois_privacy')->default(false);
            $table->integer('sync_status')->default(DomainSyncStatus::PENDING->value);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
