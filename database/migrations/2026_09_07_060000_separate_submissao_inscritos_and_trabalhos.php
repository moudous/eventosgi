<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscritos_submissao', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submissao_id')->constrained('submissoes')->cascadeOnDelete();
            $table->string('email', 150);
            $table->string('senha');
            $table->unsignedInteger('credencial_versao')->default(1);
            $table->timestamps();
            $table->unique(['submissao_id', 'email'], 'inscritos_submissao_evento_email_unique');
        });

        Schema::create('inscritos_submissao_trabalhos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('inscrito_submissao_id');
            $table->string('titulo_trabalho');
            $table->longText('conteudo')->nullable();
            $table->string('status', 20)->default('rascunho')->index();
            $table->decimal('nota', 6, 2)->nullable();
            $table->string('situacao', 100)->nullable();
            $table->timestamps();
            $table->foreign('inscrito_submissao_id', 'trabalhos_inscrito_fk')
                ->references('id')->on('inscritos_submissao')->cascadeOnDelete();
            $table->index(['inscrito_submissao_id', 'updated_at'], 'trabalhos_inscrito_data_idx');
        });

        Schema::table('submissao_autores', function (Blueprint $table): void {
            $table->unsignedBigInteger('inscrito_submissao_trabalho_id')->nullable()->after('id');
        });

        $contas = [];
        foreach (DB::table('inscricoes_submissao')->orderBy('id')->get() as $legado) {
            $email = mb_strtolower(trim((string) $legado->email));
            $chave = $legado->submissao_id.'|'.$email;

            if (! isset($contas[$chave])) {
                $contas[$chave] = DB::table('inscritos_submissao')->insertGetId([
                    'submissao_id' => $legado->submissao_id,
                    'email' => $email,
                    'senha' => $legado->senha,
                    'credencial_versao' => $legado->credencial_versao,
                    'created_at' => $legado->created_at,
                    'updated_at' => $legado->updated_at,
                ]);
            }

            $trabalhoId = DB::table('inscritos_submissao_trabalhos')->insertGetId([
                'inscrito_submissao_id' => $contas[$chave],
                'titulo_trabalho' => $legado->titulo_trabalho,
                'conteudo' => $legado->conteudo,
                'status' => $legado->status,
                'nota' => $legado->nota,
                'situacao' => $legado->situacao,
                'created_at' => $legado->created_at,
                'updated_at' => $legado->updated_at,
            ]);

            DB::table('submissao_autores')->where('inscricao_submissao_id', $legado->id)
                ->update(['inscrito_submissao_trabalho_id' => $trabalhoId]);
        }

        Schema::table('submissao_autores', function (Blueprint $table): void {
            $table->dropForeign(['inscricao_submissao_id']);
            $table->dropIndex('submissao_autores_inscricao_submissao_id_ordem_index');
            $table->dropColumn('inscricao_submissao_id');
        });
        Schema::table('submissao_autores', function (Blueprint $table): void {
            $table->unsignedBigInteger('inscrito_submissao_trabalho_id')->nullable(false)->change();
            $table->foreign('inscrito_submissao_trabalho_id', 'autores_trabalho_fk')
                ->references('id')->on('inscritos_submissao_trabalhos')->cascadeOnDelete();
            $table->index(['inscrito_submissao_trabalho_id', 'ordem'], 'autores_trabalho_ordem_idx');
        });

        Schema::drop('inscricoes_submissao');
    }

    public function down(): void
    {
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

        Schema::table('submissao_autores', function (Blueprint $table): void {
            $table->unsignedBigInteger('inscricao_submissao_id')->nullable()->after('id');
        });

        $trabalhos = DB::table('inscritos_submissao_trabalhos as trabalho')
            ->join('inscritos_submissao as inscrito', 'inscrito.id', '=', 'trabalho.inscrito_submissao_id')
            ->select('trabalho.*', 'inscrito.submissao_id', 'inscrito.email', 'inscrito.senha', 'inscrito.credencial_versao')
            ->orderBy('trabalho.id')->get();

        foreach ($trabalhos as $trabalho) {
            $legadoId = DB::table('inscricoes_submissao')->insertGetId([
                'submissao_id' => $trabalho->submissao_id,
                'titulo_trabalho' => $trabalho->titulo_trabalho,
                'email' => $trabalho->email,
                'senha' => $trabalho->senha,
                'credencial_versao' => $trabalho->credencial_versao,
                'conteudo' => $trabalho->conteudo,
                'status' => $trabalho->status,
                'nota' => $trabalho->nota,
                'situacao' => $trabalho->situacao,
                'created_at' => $trabalho->created_at,
                'updated_at' => $trabalho->updated_at,
            ]);
            DB::table('submissao_autores')->where('inscrito_submissao_trabalho_id', $trabalho->id)
                ->update(['inscricao_submissao_id' => $legadoId]);
        }

        Schema::table('submissao_autores', function (Blueprint $table): void {
            $table->dropForeign('autores_trabalho_fk');
            $table->dropIndex('autores_trabalho_ordem_idx');
            $table->dropColumn('inscrito_submissao_trabalho_id');
        });
        Schema::table('submissao_autores', function (Blueprint $table): void {
            $table->unsignedBigInteger('inscricao_submissao_id')->nullable(false)->change();
            $table->foreign('inscricao_submissao_id')->references('id')->on('inscricoes_submissao')->cascadeOnDelete();
            $table->index(['inscricao_submissao_id', 'ordem']);
        });

        Schema::drop('inscritos_submissao_trabalhos');
        Schema::drop('inscritos_submissao');
    }
};
