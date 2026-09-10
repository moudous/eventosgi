<?php

namespace App\Services;

use App\Models\InscricaoAtividade;
use App\Models\Participante;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;

class ComprovanteInscricaoService
{
    /** @return list<array{label: string, valor: string, arquivos?: list<array{nome: string, url: string, imagem: bool, icone: string}>}> */
    public function respostas(InscricaoAtividade $inscricao): array
    {
        $atividade = $inscricao->atividade;
        $respostas = $inscricao->resposta ?? [];

        return array_values(array_map(function (array $campo) use ($respostas, $inscricao): array {
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
                    return $this->nomeDoArquivo($item);
                }
                if ($checkboxSimples && (string) $item === '1') return 'Sim';
                return $opcoes->get((string) $item, is_scalar($item) ? (string) $item : '');
            })->filter(fn ($item) => $item !== '')->implode(', ');

            $resultado = ['label' => (string) ($campo['label'] ?? $nome), 'valor' => $texto ?: 'Não informado'];
            if (($campo['tipo'] ?? '') === 'file') {
                $resultado['arquivos'] = collect($valores)->values()->map(function ($caminho, int $indice) use ($inscricao, $nome): ?array {
                    if (! is_string($caminho) || ! str_starts_with($caminho, FormularioInscricaoService::PASTA_ANEXOS.'/')) return null;
                    $arquivo = $this->nomeDoArquivo($caminho);
                    $extensao = mb_strtolower((string) pathinfo($arquivo, PATHINFO_EXTENSION));
                    $imagem = in_array($extensao, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif'], true);
                    $icone = match (true) {
                        $extensao === 'pdf' => 'bi-file-earmark-pdf-fill text-danger',
                        in_array($extensao, ['doc', 'docx', 'odt', 'rtf'], true) => 'bi-file-earmark-word-fill text-primary',
                        in_array($extensao, ['xls', 'xlsx', 'ods', 'csv'], true) => 'bi-file-earmark-excel-fill text-success',
                        in_array($extensao, ['ppt', 'pptx', 'odp'], true) => 'bi-file-earmark-ppt-fill text-warning',
                        in_array($extensao, ['zip', 'rar', '7z', 'tar', 'gz'], true) => 'bi-file-earmark-zip-fill text-secondary',
                        in_array($extensao, ['mp3', 'wav', 'ogg', 'm4a'], true) => 'bi-file-earmark-music-fill text-info',
                        in_array($extensao, ['mp4', 'webm', 'mov', 'avi'], true) => 'bi-file-earmark-play-fill text-primary',
                        in_array($extensao, ['txt', 'md'], true) => 'bi-file-earmark-text-fill text-secondary',
                        default => 'bi-file-earmark-fill text-secondary',
                    };

                    return [
                        'nome' => $arquivo,
                        'url' => URL::temporarySignedRoute('inscricoes.arquivo', now()->addHours(2), [
                            'inscricao' => $inscricao->id,
                            'campo' => $nome,
                            'indice' => $indice,
                            'modo' => 'visualizar',
                        ]),
                        'imagem' => $imagem,
                        'icone' => $icone,
                    ];
                })->filter()->values()->all();
            }

            return $resultado;
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

    private function nomeDoArquivo(string $caminho): string
    {
        return (string) preg_replace('/^[0-9a-f-]{36}-/i', '', basename($caminho));
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
