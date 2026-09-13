<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atividades', function (Blueprint $table): void {
            $table->string('url', 180)->nullable()->unique()->after('hash_publica');
        });
    }

    public function down(): void
    {
        Schema::table('atividades', function (Blueprint $table): void {
            $table->dropUnique(['url']);
            $table->dropColumn('url');
        });
    }
};
