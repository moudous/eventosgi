<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('livekit_configuracoes', function (Blueprint $table): void {
            $table->id();
            $table->string('url', 500);
            $table->string('api_key', 500);
            $table->text('api_secret');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('livekit_configuracoes');
    }
};
