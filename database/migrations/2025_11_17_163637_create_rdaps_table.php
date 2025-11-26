<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rdaps', function (Blueprint $table) {
            $table->id();
            $table->string('tld')->unique();
            $table->text('rdap');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rdaps');
    }
};
