<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrars', function (Blueprint $table) {
            $table->foreignId('currency_id')->after('id')->nullable()->constrained('currencies');
        });
    }

    public function down(): void
    {
        Schema::table('registrars', function (Blueprint $table) {
            $table->dropForeign(['currency_id']);
            $table->dropColumn('currency_id');
        });
    }
};
