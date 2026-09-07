<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convidados', function (Blueprint $table): void {
            $table->string('foto_nome', 80)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('convidados', function (Blueprint $table): void {
            $table->dropColumn('foto_nome');
        });
    }
};
