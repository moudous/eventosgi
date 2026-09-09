<?php

use App\Models\Atividade;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const ANTERIOR = 'Informe o seu e-mail e confirme o código que enviaremos para ele. Assim conseguimos localizar o seu cadastro e emitir o certificado no nome certo.';

    private const NOVA = 'Informe seu e-mail e sua senha para entrar. Caso ainda não possua uma senha, solicite um código temporário por e-mail.';

    public function up(): void
    {
        Atividade::query()->whereNotNull('formulario')->eachById(function (Atividade $atividade): void {
            $formulario = $atividade->formulario;
            if (trim((string) ($formulario['mensagem_identificacao'] ?? '')) !== self::ANTERIOR) return;
            $formulario['mensagem_identificacao'] = self::NOVA;
            $atividade->update(['formulario' => $formulario]);
        });
    }

    public function down(): void
    {
        Atividade::query()->whereNotNull('formulario')->eachById(function (Atividade $atividade): void {
            $formulario = $atividade->formulario;
            if (trim((string) ($formulario['mensagem_identificacao'] ?? '')) !== self::NOVA) return;
            $formulario['mensagem_identificacao'] = self::ANTERIOR;
            $atividade->update(['formulario' => $formulario]);
        });
    }
};
