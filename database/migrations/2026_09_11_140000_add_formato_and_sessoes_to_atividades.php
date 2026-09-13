<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atividades', function (Blueprint $table): void {
            // O default preserva todas as atividades cadastradas antes desta funcionalidade.
            $table->string('formato', 20)->default('simples')->after('tipo')->index();
        });

        Schema::create('sessoes_atividade', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('atividade_id')->constrained('atividades')->cascadeOnDelete();
            $table->string('nome');
            $table->dateTime('data_inicio')->nullable();
            $table->dateTime('data_fim')->nullable();
            $table->unsignedInteger('limite_vagas')->nullable();
            $table->boolean('ativo')->default(true)->index();
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('inscricoes_atividade', function (Blueprint $table): void {
            // Nulo para que todas as inscricoes anteriores continuem validas.
            $table->foreignId('sessao_atividade_id')->nullable()->after('atividade_id')
                ->constrained('sessoes_atividade')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inscricoes_atividade', fn (Blueprint $table) => $table->dropConstrainedForeignId('sessao_atividade_id'));
        Schema::dropIfExists('sessoes_atividade');
        Schema::table('atividades', fn (Blueprint $table) => $table->dropColumn('formato'));
    }
};
