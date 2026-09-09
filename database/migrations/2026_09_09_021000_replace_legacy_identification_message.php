<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ANTERIOR = 'Informe o seu e-mail e confirme o código que enviaremos para ele. Assim conseguimos localizar o seu cadastro e emitir o certificado no nome certo.';

    private const NOVA = 'Informe seu e-mail e sua senha para entrar. Caso ainda não possua uma senha, solicite um código temporário por e-mail.';

    public function up(): void
    {
        DB::table('atividades')->whereNotNull('formulario')->select(['id', 'formulario'])->orderBy('id')->chunkById(100, function ($atividades): void {
            foreach ($atividades as $atividade) {
                $formulario = json_decode($atividade->formulario, true);
                if (json_last_error() !== JSON_ERROR_NONE || ! is_array($formulario)) continue;
                if (trim((string) ($formulario['mensagem_identificacao'] ?? '')) !== self::ANTERIOR) continue;
                $formulario['mensagem_identificacao'] = self::NOVA;
                DB::table('atividades')->where('id', $atividade->id)->update([
                    'formulario' => json_encode($formulario, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Mensagens já exibidas ao público não são revertidas para uma orientação desatualizada.
    }
};
