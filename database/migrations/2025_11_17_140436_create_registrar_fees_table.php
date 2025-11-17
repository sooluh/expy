<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrar_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registrar_id')->constrained()->cascadeOnDelete();
            $table->string('tld');
            $table->decimal('register_price', 12, 2)->nullable();
            $table->decimal('renew_price', 12, 2)->nullable();
            $table->decimal('transfer_price', 12, 2)->nullable();
            $table->decimal('restore_price', 12, 2)->nullable();
            $table->decimal('privacy_price', 12, 2)->nullable();
            $table->decimal('misc_price', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['registrar_id', 'tld']);
            $table->index('tld');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrar_fees');
    }
};
