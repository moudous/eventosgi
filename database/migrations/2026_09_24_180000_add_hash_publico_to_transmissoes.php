<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transmissoes', function (Blueprint $table): void {
            $table->string('hash_publico', 64)->nullable()->unique()->after('id');
        });

        DB::table('transmissoes')->whereNull('hash_publico')->orderBy('id')->each(function (object $transmissao): void {
            DB::table('transmissoes')->where('id', $transmissao->id)->update(['hash_publico' => bin2hex(random_bytes(32))]);
        });
    }

    public function down(): void
    {
        Schema::table('transmissoes', function (Blueprint $table): void {
            $table->dropUnique(['hash_publico']);
            $table->dropColumn('hash_publico');
        });
    }
};
