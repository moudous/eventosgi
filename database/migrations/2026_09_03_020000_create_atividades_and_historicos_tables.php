<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atividades', function (Blueprint $table): void {
            $table->id();
            $table->string('nome');
            $table->boolean('ativo')->default(true)->index();
            $table->unsignedBigInteger('criado_por')->index();
            $table->unsignedBigInteger('evento_id')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('historico_eventos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('evento_id')->index();
            $table->string('historico');
            $table->unsignedBigInteger('usuario')->nullable()->index();
            $table->json('dados')->nullable();
            $table->timestamp('data_hora')->useCurrent()->index();
        });

        Schema::create('historico_atividades', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('atividade_id')->index();
            $table->string('historico');
            $table->unsignedBigInteger('usuario')->nullable()->index();
            $table->json('dados')->nullable();
            $table->timestamp('data_hora')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historico_atividades');
        Schema::dropIfExists('historico_eventos');
        Schema::dropIfExists('atividades');
    }
};
