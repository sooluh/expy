<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->foreignId('registrar_fee_id')->after('registrar_id')->nullable()->constrained('registrar_fees');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropForeign(['registrar_fee_id']);
            $table->dropColumn('registrar_fee_id');
        });
    }
};
