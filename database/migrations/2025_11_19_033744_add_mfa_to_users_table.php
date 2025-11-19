<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('app_authentication_recovery_codes')->nullable()->after('settings');
            $table->text('app_authentication_secret')->nullable()->after('settings');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('app_authentication_secret');
            $table->dropColumn('app_authentication_recovery_codes');
        });
    }
};
