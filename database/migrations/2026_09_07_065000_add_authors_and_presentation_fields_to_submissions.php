<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissoes', function (Blueprint $table): void {
            $table->unsignedSmallInteger('qtde_autores')->default(8)->after('qtde_resumo');
        });

        Schema::table('submissao_autores', function (Blueprint $table): void {
            $table->string('email', 150)->nullable()->after('nome');
            $table->text('afiliacao')->nullable()->after('email');
            $table->unsignedSmallInteger('numero')->default(1)->after('ordem');
        });
        DB::table('submissao_autores')->update(['numero' => DB::raw('ordem')]);

        Schema::table('inscritos_submissao_trabalhos', function (Blueprint $table): void {
            $table->boolean('tem_apoio_financeiro')->default(false)->after('conteudo');
            $table->text('apoiador')->nullable()->after('tem_apoio_financeiro');
            $table->string('apresentacao', 20)->default('presencial')->after('apoiador');
        });
    }

    public function down(): void
    {
        Schema::table('inscritos_submissao_trabalhos', function (Blueprint $table): void {
            $table->dropColumn(['tem_apoio_financeiro', 'apoiador', 'apresentacao']);
        });
        Schema::table('submissao_autores', function (Blueprint $table): void {
            $table->dropColumn(['email', 'afiliacao', 'numero']);
        });
        Schema::table('submissoes', function (Blueprint $table): void {
            $table->dropColumn('qtde_autores');
        });
    }
};
