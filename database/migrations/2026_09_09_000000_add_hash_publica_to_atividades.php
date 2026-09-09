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
            $table->string('hash_publica', 64)->nullable()->unique();
        });
        DB::table('atividades')->select('id')->orderBy('id')->chunkById(100, function ($atividades): void {
            foreach ($atividades as $atividade) {
                DB::table('atividades')->where('id', $atividade->id)->update(['hash_publica' => bin2hex(random_bytes(32))]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('atividades', function (Blueprint $table): void {
            $table->dropColumn('hash_publica');
        });
    }
};
