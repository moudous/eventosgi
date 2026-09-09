<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscricoes_atividade', function (Blueprint $table): void {
            $table->string('comprovante_hash', 64)->nullable()->unique();
        });

        DB::table('inscricoes_atividade')->select('id')->orderBy('id')->chunkById(100, function ($inscricoes): void {
            foreach ($inscricoes as $inscricao) {
                DB::table('inscricoes_atividade')->where('id', $inscricao->id)
                    ->update(['comprovante_hash' => bin2hex(random_bytes(32))]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('inscricoes_atividade', function (Blueprint $table): void {
            $table->dropColumn('comprovante_hash');
        });
    }
};
