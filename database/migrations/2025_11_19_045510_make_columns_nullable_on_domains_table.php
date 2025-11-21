<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->date('registration_date')->nullable()->change();
            $table->date('expiration_date')->nullable()->change();
            $table->json('nameservers')->default('[]')->change();
            $table->boolean('security_lock')->nullable()->change();
            $table->boolean('whois_privacy')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->date('registration_date')->nullable(false)->change();
            $table->date('expiration_date')->nullable(false)->change();
            $table->json('nameservers')->default(null)->change();
            $table->boolean('security_lock')->nullable(false)->change();
            $table->boolean('whois_privacy')->nullable(false)->change();
        });
    }
};
