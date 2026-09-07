<?php

namespace App\Rules;

use Closure;
use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\RFCValidation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Cache;

/**
 * E-mail com formato conferido e dominio que realmente recebe mensagens.
 *
 * Separa as duas falhas para que o visitante saiba o que corrigir: escrever
 * "fulano.com" e diferente de escrever "fulano@dominio-que-nao-existe.com".
 */
class EmailValido implements ValidationRule
{
    /** Consultas de DNS ficam em cache para nao repetir a espera a cada tentativa de envio. */
    private const MINUTOS_CACHE = 60;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = trim((string) $value);

        // Campo vazio e assunto das regras required/nullable, nao desta.
        if ($email === '') return;

        if (substr_count($email, '@') !== 1) {
            $fail('O :attribute deve conter um @, no formato nome@dominio.com.');

            return;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! (new EmailValidator)->isValid($email, new RFCValidation)) {
            $fail('O :attribute informado não é um endereço válido.');

            return;
        }

        $dominio = substr((string) strrchr($email, '@'), 1);

        if (! $this->dominioRecebeEmail($dominio)) {
            $fail("O domínio {$dominio} não existe ou não recebe e-mails. Confira o que vem depois do @.");
        }
    }

    /**
     * O dominio precisa de um servidor de e-mail (MX) ou, na falta dele, de um
     * endereco proprio (A/AAAA), que o SMTP usa como destino alternativo.
     */
    private function dominioRecebeEmail(string $dominio): bool
    {
        if ($dominio === '' || strlen($dominio) > 253 || ! str_contains($dominio, '.')) {
            return false;
        }

        return Cache::remember(
            'dominio-email:'.strtolower($dominio),
            now()->addMinutes(self::MINUTOS_CACHE),
            fn (): bool => checkdnsrr($dominio, 'MX') || checkdnsrr($dominio, 'A') || checkdnsrr($dominio, 'AAAA'),
        );
    }
}
