<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table): void {
            $table->string('foto_url', 2048)->nullable()->after('email');
        });

        Schema::create('avaliadores', function (Blueprint $table): void {
            $table->id();
            $table->string('nome');
            $table->unsignedBigInteger('usuario_id')->unique();
            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('usuarios')->restrictOnDelete();
        });

        Schema::table('inscritos_submissao_trabalhos', function (Blueprint $table): void {
            $table->foreignId('avaliador_id')->nullable()->after('inscrito_submissao_id')
                ->constrained('avaliadores')->nullOnDelete();
            $table->index(['avaliador_id', 'updated_at'], 'trabalhos_avaliador_data_idx');
        });
    }

    public function down(): void
    {
        Schema::table('inscritos_submissao_trabalhos', function (Blueprint $table): void {
            $table->dropIndex('trabalhos_avaliador_data_idx');
            $table->dropConstrainedForeignId('avaliador_id');
        });
        Schema::dropIfExists('avaliadores');
        Schema::table('usuarios', fn (Blueprint $table) => $table->dropColumn('foto_url'));
    }
};
