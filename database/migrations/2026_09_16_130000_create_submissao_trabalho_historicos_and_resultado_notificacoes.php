<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscritos_submissao_trabalhos', function (Blueprint $table): void {
            $table->unsignedInteger('notificacao_resultado_versao')->default(1)->after('eposter_enviado_em');
        });

        Schema::create('submissao_trabalho_historicos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('inscrito_submissao_trabalho_id');
            $table->foreign('inscrito_submissao_trabalho_id', 'sub_trab_hist_trabalho_fk')
                ->references('id')->on('inscritos_submissao_trabalhos')->cascadeOnDelete();
            $table->string('historico');
            $table->string('usuario')->nullable();
            $table->json('dados')->nullable();
            $table->timestamp('data_hora')->useCurrent()->index();
            $table->index(['inscrito_submissao_trabalho_id', 'data_hora'], 'sub_trab_hist_data_idx');
        });

        Schema::create('submissao_resultado_notificacoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('inscrito_submissao_trabalho_id');
            $table->foreign('inscrito_submissao_trabalho_id', 'sub_result_notif_trabalho_fk')
                ->references('id')->on('inscritos_submissao_trabalhos')->cascadeOnDelete();
            $table->unsignedInteger('versao');
            $table->string('tipo', 20);
            $table->string('email', 150);
            $table->string('nome')->nullable();
            $table->boolean('principal')->default(false);
            $table->unsignedBigInteger('disparo_gi_id')->nullable();
            $table->timestamp('enviado_em')->useCurrent();
            $table->unique(
                ['inscrito_submissao_trabalho_id', 'versao', 'tipo', 'email'],
                'sub_result_notif_destino_unique',
            );
        });

        $agora = now();
        DB::table('inscritos_submissao_trabalhos')->orderBy('id')->each(function ($trabalho) use ($agora): void {
            DB::table('submissao_trabalho_historicos')->insert([
                'inscrito_submissao_trabalho_id' => $trabalho->id,
                'historico' => 'O autor submeteu o trabalho',
                'usuario' => 'Autor',
                'dados' => json_encode(['titulo' => $trabalho->titulo_trabalho], JSON_UNESCAPED_UNICODE),
                'data_hora' => $trabalho->created_at ?? $agora,
            ]);
            if ($trabalho->status === 'avaliado' && in_array($trabalho->situacao, ['aprovado', 'reprovado'], true)) {
                DB::table('submissao_trabalho_historicos')->insert([
                    'inscrito_submissao_trabalho_id' => $trabalho->id,
                    'historico' => 'Situação alterada para '.$trabalho->situacao,
                    'usuario' => 'Registro anterior à implantação do histórico',
                    'dados' => json_encode(['situacao' => $trabalho->situacao], JSON_UNESCAPED_UNICODE),
                    'data_hora' => $trabalho->updated_at ?? $agora,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissao_resultado_notificacoes');
        Schema::dropIfExists('submissao_trabalho_historicos');
        Schema::table('inscritos_submissao_trabalhos', function (Blueprint $table): void {
            $table->dropColumn('notificacao_resultado_versao');
        });
    }
};
