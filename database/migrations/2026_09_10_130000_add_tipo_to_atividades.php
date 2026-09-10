<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atividades', fn (Blueprint $table) => $table->string('tipo', 30)->default('somente_inscricao'));
        DB::table('atividades')->update(['tipo' => 'atividade_evento']);
    }

    public function down(): void
    {
        Schema::table('atividades', fn (Blueprint $table) => $table->dropColumn('tipo'));
    }
};
