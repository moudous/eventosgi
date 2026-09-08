<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissoes', function (Blueprint $table): void {
            $table->unsignedInteger('qtde_resumo')->default(1600)->after('modelo_trabalho');
        });
    }

    public function down(): void
    {
        Schema::table('submissoes', function (Blueprint $table): void {
            $table->dropColumn('qtde_resumo');
        });
    }
};
