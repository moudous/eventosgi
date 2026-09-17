<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pix_cobrancas', function (Blueprint $table): void {
            $table->string('ambiente', 20)->nullable()->after('campo')->index();
        });

        // Em produção o TXID é definido pela aplicação e começa com EVGI.
        // O simulador devolve seu próprio TXID aleatório.
        DB::table('pix_cobrancas')->where('txid', 'like', 'EVGI%')->update(['ambiente' => 'producao']);
        DB::table('pix_cobrancas')->whereNull('ambiente')->update(['ambiente' => 'sandbox']);
    }

    public function down(): void
    {
        Schema::table('pix_cobrancas', fn (Blueprint $table) => $table->dropColumn('ambiente'));
    }
};
