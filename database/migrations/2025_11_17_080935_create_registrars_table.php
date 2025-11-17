<?php

use App\Enums\ApiSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrars', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->text('notes')->nullable();
            $table->tinyInteger('api_support')->nullable()->default(ApiSupport::NONE->value);
            $table->json('api_settings')->nullable()->default('{}');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrars');
    }
};
