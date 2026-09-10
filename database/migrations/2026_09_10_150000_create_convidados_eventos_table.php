<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convidados_eventos', function (Blueprint $table) {
            $table->foreignId('convidado_id')->constrained('convidados')->cascadeOnDelete();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->primary(['convidado_id', 'evento_id']);
        });
        DB::table('convidados')->whereNotNull('evento_id')->orderBy('id')->chunkById(500, function ($convidados) {
            DB::table('convidados_eventos')->insert($convidados->map(fn ($convidado) => [
                'convidado_id' => $convidado->id, 'evento_id' => $convidado->evento_id,
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convidados_eventos');
    }
};
