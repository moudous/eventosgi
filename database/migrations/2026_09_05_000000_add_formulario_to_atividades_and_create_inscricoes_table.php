<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atividades', function (Blueprint $table): void {
            $table->longText('formulario')->nullable();
        });

        Schema::create('inscricoes_atividade', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('atividade_id')->index();
            $table->json('resposta');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscricoes_atividade');
        Schema::table('atividades', function (Blueprint $table): void {
            $table->dropColumn('formulario');
        });
    }
};