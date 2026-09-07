<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codigos_inscricao', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('atividade_id');
            $table->string('email', 150);
            $table->string('codigo_hash', 64);
            $table->unsignedSmallInteger('tentativas')->default(0);
            $table->timestamp('expira_em');
            $table->timestamp('validado_em')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['atividade_id', 'email']);
            $table->index('expira_em');
        });

        Schema::table('inscricoes_atividade', function (Blueprint $table): void {
            $table->unsignedBigInteger('participante_id')->nullable()->after('atividade_id')->index();
            $table->string('participante_email', 150)->nullable()->after('participante_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('inscricoes_atividade', function (Blueprint $table): void {
            $table->dropIndex(['participante_id']);
            $table->dropIndex(['participante_email']);
            $table->dropColumn(['participante_id', 'participante_email']);
        });

        Schema::dropIfExists('codigos_inscricao');
    }
};
