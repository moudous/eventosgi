<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissao_autor_notificacoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('inscrito_submissao_trabalho_id');
            $table->foreign('inscrito_submissao_trabalho_id', 'sub_autor_notif_trabalho_fk')
                ->references('id')->on('inscritos_submissao_trabalhos')->cascadeOnDelete();
            $table->string('email', 150);
            $table->string('nome');
            $table->boolean('principal')->default(false);
            $table->boolean('ativo')->default(true)->index();
            $table->timestamp('adicionado_em');
            $table->timestamp('adicao_enviada_em')->nullable();
            $table->timestamp('removido_em')->nullable();
            $table->timestamp('remocao_enviada_em')->nullable();
            $table->timestamps();

            $table->unique(['inscrito_submissao_trabalho_id', 'email'], 'submissao_autor_notificacoes_trabalho_email_unique');
        });

        // Autores de trabalhos já existentes não devem receber uma mensagem retroativa
        // na próxima edição. O histórico começa marcado como entregue para eles.
        DB::table('submissao_autores')->orderBy('id')->each(function ($autor): void {
            $email = mb_strtolower(trim((string) $autor->email));
            if ($email === '') return;

            DB::table('submissao_autor_notificacoes')->insertOrIgnore([
                'inscrito_submissao_trabalho_id' => $autor->inscrito_submissao_trabalho_id,
                'email' => $email,
                'nome' => $autor->nome,
                'principal' => (bool) $autor->principal,
                'ativo' => true,
                'adicionado_em' => $autor->created_at ?? now(),
                'adicao_enviada_em' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissao_autor_notificacoes');
    }
};
