<?php

namespace App\Services;

use App\Models\InscricaoAtividade;
use App\Models\Participante;
use Illuminate\Support\Str;

class ComprovanteInscricaoService
{
    /** @return list<array{label: string, valor: string}> */
    public function respostas(InscricaoAtividade $inscricao): array
    {
        $atividade = $inscricao->atividade;
        $respostas = $inscricao->resposta ?? [];

        return array_values(array_map(function (array $campo) use ($respostas): array {
            $nome = (string) ($campo['nome'] ?? '');
            $valor = $respostas[$nome] ?? null;
            $opcoes = collect($campo['opcoes'] ?? [])->mapWithKeys(function ($opcao): array {
                return is_array($opcao)
                    ? [(string) ($opcao['valor'] ?? '') => (string) ($opcao['texto'] ?? $opcao['valor'] ?? '')]
                    : [(string) $opcao => (string) $opcao];
            });
            $valores = is_array($valor) ? $valor : [$valor];
            $checkboxSimples = ($campo['tipo'] ?? '') === 'checkbox' && $opcoes->isEmpty();
            $texto = collect($valores)->map(function ($item) use ($opcoes, $checkboxSimples): string {
                if (is_string($item) && str_starts_with($item, FormularioInscricaoService::PASTA_ANEXOS.'/')) {
                    return 'Arquivo enviado: '.basename($item);
                }
                if ($checkboxSimples && (string) $item === '1') return 'Sim';
                return $opcoes->get((string) $item, is_scalar($item) ? (string) $item : '');
            })->filter(fn ($item) => $item !== '')->implode(', ');

            return ['label' => (string) ($campo['label'] ?? $nome), 'valor' => $texto ?: 'Não informado'];
        }, array_filter($atividade->formulario['campos'] ?? [], fn ($campo) => ! empty($campo['nome']))));
    }

    /** @return list<array{label: string, valor: string}> */
    public function participante(InscricaoAtividade $inscricao): array
    {
        $participante = $inscricao->participante_id
            ? Participante::query()->where('id', $inscricao->participante_id)->first()
            : null;

        return [
            ['label' => 'Nome', 'valor' => $participante?->nome ?: 'Não informado'],
            ['label' => 'E-mail', 'valor' => $inscricao->participante_email ?: $participante?->email ?: 'Não informado'],
            ['label' => 'CPF', 'valor' => $participante?->cpf ?: 'Não informado'],
            ['label' => 'Instituição de ensino', 'valor' => $participante?->instituicao_ensino ?: 'Não informado'],
        ];
    }

    public function nomeArquivo(InscricaoAtividade $inscricao): string
    {
        return 'comprovante-'.(Str::slug($inscricao->atividade->nome) ?: 'inscricao').'.pdf';
    }

    /** @return array{codigo: string, imagem: string}|null */
    public function qrPresenca(InscricaoAtividade $inscricao): ?array
    {
        return app(PresencaQrService::class)->dados($inscricao);
    }

    /** @return array{data: string, usuario: string}|null */
    public function presenca(InscricaoAtividade $inscricao): ?array
    {
        if (! $inscricao->presente) return null;

        $usuario = $inscricao->validadorPresenca;

        return [
            'data' => $inscricao->data_presenca?->format('d/m/Y H:i:s') ?? 'Não informada',
            'usuario' => $usuario?->nome
                ?: ($inscricao->presenca_validada_por ? 'Usuário GI #'.$inscricao->presenca_validada_por : 'Não informado'),
        ];
    }
}
