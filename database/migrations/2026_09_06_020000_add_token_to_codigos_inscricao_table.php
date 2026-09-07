<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('codigos_inscricao', function (Blueprint $table): void {
            // Consumidores externos (plugin do WordPress) nao compartilham sessao com esta
            // aplicacao: depois de conferir o codigo eles recebem um token e o reenviam
            // junto da inscricao.
            $table->unsignedBigInteger('participante_id')->nullable()->after('email');
            $table->string('token_hash', 64)->nullable()->after('codigo_hash')->index();
            $table->timestamp('token_expira_em')->nullable()->after('expira_em');
        });
    }

    public function down(): void
    {
        Schema::table('codigos_inscricao', function (Blueprint $table): void {
            $table->dropIndex(['token_hash']);
            $table->dropColumn(['participante_id', 'token_hash', 'token_expira_em']);
        });
    }
};
