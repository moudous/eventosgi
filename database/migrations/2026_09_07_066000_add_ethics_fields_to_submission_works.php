<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscritos_submissao_trabalhos', function (Blueprint $table): void {
            $table->boolean('aprovacao_comite_etica')->default(false)->after('apresentacao');
            $table->string('protocolo_comite_etica', 500)->nullable()->after('aprovacao_comite_etica');
        });
    }

    public function down(): void
    {
        Schema::table('inscritos_submissao_trabalhos', function (Blueprint $table): void {
            $table->dropColumn(['aprovacao_comite_etica', 'protocolo_comite_etica']);
        });
    }
};
