<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscritos_submissao_trabalhos', function (Blueprint $table): void {
            $table->softDeletes()->index();
        });
    }

    public function down(): void
    {
        Schema::table('inscritos_submissao_trabalhos', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
