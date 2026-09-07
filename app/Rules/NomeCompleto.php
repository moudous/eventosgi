<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Nome com pelo menos um sobrenome.
 *
 * O certificado sai com o nome informado aqui, entao "Maria" nao serve.
 * Particulas ("de", "da", "dos") nao contam como sobrenome, senao "Maria de"
 * passaria; iniciais soltas ("J.") tambem nao.
 */
class NomeCompleto implements ValidationRule
{
    private const PARTICULAS = ['de', 'da', 'do', 'das', 'dos', 'del', 'della', 'di', 'du', 'e', 'la', 'le', 'van', 'von', 'y'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $nome = preg_replace('/\s+/u', ' ', trim(is_scalar($value) ? (string) $value : '')) ?? '';

        // Campo vazio e assunto da regra required, nao desta.
        if ($nome === '') return;

        if (! preg_match("/^[\p{L}\p{M}'\-. ]+$/u", $nome)) {
            $fail('O :attribute deve conter apenas letras, sem números ou símbolos.');

            return;
        }

        $partes = array_filter(
            explode(' ', $nome),
            fn (string $parte): bool => ! in_array(mb_strtolower($parte), self::PARTICULAS, true)
                && mb_strlen(trim($parte, ".'-")) >= 2,
        );

        if (count($partes) < 2) {
            $fail('Informe o nome completo, com pelo menos um sobrenome.');
        }
    }
}
