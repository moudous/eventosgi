<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->restrictOnDelete();
            $table->string('titulo');
            $table->dateTime('data_inicio')->index();
            $table->dateTime('data_fim')->index();
            $table->boolean('ativo')->default(true)->index();
            $table->longText('modelo_trabalho')->nullable();
            $table->timestamps();
        });

        Schema::create('inscricoes_submissao', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submissao_id')->constrained('submissoes')->cascadeOnDelete();
            $table->string('titulo_trabalho');
            $table->string('email', 150);
            $table->string('senha');
            $table->unsignedInteger('credencial_versao')->default(1);
            $table->longText('conteudo')->nullable();
            $table->string('status', 20)->default('rascunho')->index();
            $table->decimal('nota', 6, 2)->nullable();
            $table->string('situacao', 100)->nullable();
            $table->timestamps();

            $table->index(['submissao_id', 'email']);
        });

        Schema::create('submissao_autores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inscricao_submissao_id')->constrained('inscricoes_submissao')->cascadeOnDelete();
            $table->string('nome');
            $table->boolean('principal')->default(false);
            $table->unsignedSmallInteger('ordem')->default(1);
            $table->timestamps();

            $table->index(['inscricao_submissao_id', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissao_autores');
        Schema::dropIfExists('inscricoes_submissao');
        Schema::dropIfExists('submissoes');
    }
};
