<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transmissoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->string('status', 20)->default('agendada')->index();
            $table->timestamp('agendada_para')->nullable()->index();
            $table->timestamp('iniciada_em')->nullable();
            $table->timestamp('finalizada_em')->nullable();
            $table->string('youtube_broadcast_id')->nullable()->index();
            $table->string('youtube_video_id')->nullable()->index();
            $table->string('youtube_url')->nullable();
            $table->string('miniatura_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transmissoes');
    }
};