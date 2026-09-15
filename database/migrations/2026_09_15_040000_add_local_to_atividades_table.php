<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atividades', function (Blueprint $table): void {
            $table->string('local')->nullable()->after('modalidade');
        });

        DB::table('atividades')->select(['id', 'formulario'])->orderBy('id')->chunkById(100, function ($atividades): void {
            foreach ($atividades as $atividade) {
                $formulario = json_decode((string) $atividade->formulario, true);
                $local = trim((string) ($formulario['local'] ?? ''));
                if ($local !== '') DB::table('atividades')->where('id', $atividade->id)->update(['local' => $local]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('atividades', function (Blueprint $table): void {
            $table->dropColumn('local');
        });
    }
};
