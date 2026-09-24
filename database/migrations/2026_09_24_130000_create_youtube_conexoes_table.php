<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('youtube_conexoes', function (Blueprint $table): void {
            $table->id();
            $table->string('canal_id')->nullable();
            $table->string('canal_titulo')->nullable();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('expira_em')->nullable();
            $table->unsignedBigInteger('conectado_por')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('youtube_conexoes');
    }
};
