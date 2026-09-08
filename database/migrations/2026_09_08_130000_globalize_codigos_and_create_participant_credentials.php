<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('codigos_inscricao', function (Blueprint $table): void {
            $table->dropIndex('codigos_inscricao_atividade_id_email_index');
            $table->dropColumn('atividade_id');
            $table->index('email');
            $table->string('redefinicao_token_hash', 64)->nullable()->unique();
            $table->timestamp('redefinicao_expira_em')->nullable()->index();
            $table->timestamp('redefinicao_usado_em')->nullable();
        });

        Schema::create('credenciais_participante', function (Blueprint $table): void {
            $table->id();
            // participantes fica na conexão cert; por isso este vínculo não recebe FK local.
            $table->unsignedBigInteger('participante_id')->nullable()->index();
            $table->string('email', 150)->unique();
            $table->string('senha');
            $table->unsignedInteger('credencial_versao')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credenciais_participante');
        Schema::table('codigos_inscricao', function (Blueprint $table): void {
            $table->dropUnique(['redefinicao_token_hash']);
            $table->dropIndex(['redefinicao_expira_em']);
            $table->dropIndex(['email']);
            $table->dropColumn(['redefinicao_token_hash', 'redefinicao_expira_em', 'redefinicao_usado_em']);
            $table->unsignedBigInteger('atividade_id')->nullable()->index();
            $table->index(['atividade_id', 'email']);
        });
    }
};
