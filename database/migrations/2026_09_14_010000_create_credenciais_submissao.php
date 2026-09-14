<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credenciais_submissao', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 150)->unique();
            $table->string('senha')->nullable();
            $table->unsignedInteger('credencial_versao')->default(1);
            $table->string('temporaria_hash')->nullable();
            $table->timestamp('temporaria_expira_em')->nullable();
            $table->string('redefinicao_token_hash', 64)->nullable()->unique();
            $table->timestamp('redefinicao_expira_em')->nullable();
            $table->timestamps();
        });

        // Havendo senhas distintas, conserva a credencial alterada mais recentemente.
        // Os registros antigos ficam preservados, mas deixam de autenticar acessos.
        DB::table('inscritos_submissao')->orderByDesc('updated_at')->orderByDesc('id')->each(function ($inscrito): void {
            DB::table('credenciais_submissao')->insertOrIgnore([
                'email' => mb_strtolower(trim($inscrito->email)),
                'senha' => $inscrito->senha,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        DB::table('submissao_autores')->whereNotNull('email')->orderBy('id')->each(function ($autor): void {
            $email = mb_strtolower(trim($autor->email));
            if ($email !== '') DB::table('credenciais_submissao')->insertOrIgnore([
                'email' => $email, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credenciais_submissao');
    }
};
