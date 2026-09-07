<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CPF com digitos verificadores conferidos.
 *
 * Aceita o numero com ou sem pontuacao; a normalizacao para 11 digitos
 * acontece antes da validacao, em FormularioInscricaoService.
 */
class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $bruto = is_scalar($value) ? trim((string) $value) : '';

        // Campo vazio e assunto das regras required/nullable, nao desta.
        if ($bruto === '') return;

        // Conta os digitos do que foi digitado: "abc" nao vira vazio, vira CPF invalido.
        $digitos = preg_replace('/\D/', '', $bruto) ?? '';

        if (strlen($digitos) !== 11) {
            $fail('O :attribute deve ter 11 dígitos.');

            return;
        }

        // 000.000.000-00, 111.111.111-11 e afins passam no calculo, mas nao existem.
        if (preg_match('/^(\d)\1{10}$/', $digitos)) {
            $fail('O :attribute informado não é válido.');

            return;
        }

        foreach ([9, 10] as $posicao) {
            $soma = 0;
            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $digitos[$i] * ($posicao + 1 - $i);
            }

            $resto = $soma % 11;
            $esperado = $resto < 2 ? 0 : 11 - $resto;

            if ((int) $digitos[$posicao] !== $esperado) {
                $fail('O :attribute informado não é válido.');

                return;
            }
        }
    }
}
