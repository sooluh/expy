<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrars', function (Blueprint $table): void {
            $table->timestamp('last_sync_at')->nullable()->after('api_settings');
        });
    }

    public function down(): void
    {
        Schema::table('registrars', function (Blueprint $table): void {
            $table->dropColumn('last_sync_at');
        });
    }
};
