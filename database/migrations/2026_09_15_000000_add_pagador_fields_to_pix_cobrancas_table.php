<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pix_cobrancas', function (Blueprint $table): void {
            $table->string('pagador_nome', 200)->nullable()->after('status')->index();
            $table->string('pagador_documento', 20)->nullable()->after('pagador_nome')->index();
            $table->string('end_to_end_id', 100)->nullable()->after('pagador_documento')->index();
        });

        DB::table('pix_cobrancas')->whereNotNull('resposta_api')->orderBy('id')->chunkById(100, function ($cobrancas): void {
            foreach ($cobrancas as $cobranca) {
                $dados = json_decode((string) $cobranca->resposta_api, true);
                $pix = (array) data_get($dados, 'pix.0', []);
                if ($pix === []) continue;
                $documento = data_get($pix, 'pagador.cpf') ?: data_get($pix, 'pagador.cnpj');
                DB::table('pix_cobrancas')->where('id', $cobranca->id)->update([
                    'pagador_nome' => data_get($pix, 'pagador.nome'),
                    'pagador_documento' => $documento ? preg_replace('/\D+/', '', (string) $documento) : null,
                    'end_to_end_id' => data_get($pix, 'endToEndId'),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('pix_cobrancas', fn (Blueprint $table) => $table->dropColumn(['pagador_nome', 'pagador_documento', 'end_to_end_id']));
    }
};
