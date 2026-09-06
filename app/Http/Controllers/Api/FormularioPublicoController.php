<?php

namespace App\Http\Controllers\Api;

use App\Models\Atividade;
use App\Services\FormularioInscricaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormularioPublicoController
{
    public function __construct(private readonly FormularioInscricaoService $inscricoes) {}

    /**
     * Estrutura do formulario para que consumidores externos (ex.: WordPress) o renderizem.
     */
    public function mostrar(Atividade $atividade): JsonResponse
    {
        abort_unless($atividade->formulario, 404, 'Esta atividade não possui formulário publicado.');
        abort_unless($atividade->ativo, 404, 'Esta atividade não está ativa.');

        $config = $atividade->formulario;
        $estado = $this->inscricoes->estado($atividade);

        return response()->json([
            'atividade' => [
                'id' => $atividade->id,
                'nome' => $atividade->nome,
                'modalidade' => $atividade->modalidade,
                'data_inicio' => $atividade->data_inicio?->toIso8601String(),
                'data_fim' => $atividade->data_fim?->toIso8601String(),
            ],
            'titulo' => $config['titulo'] ?? $atividade->nome,
            'subtitulo' => $config['subtitulo'] ?? '',
            'editor' => [
                'posicao' => $config['editor']['posicao'] ?? '',
                'conteudo' => $config['editor']['conteudo'] ?? '',
            ],
            'campos' => array_values(array_map(fn (array $campo) => [
                'nome' => $campo['nome'] ?? '',
                'label' => $campo['label'] ?? ($campo['nome'] ?? ''),
                'tipo' => $campo['tipo'] ?? 'text',
                'placeholder' => $campo['placeholder'] ?? '',
                'obrigatorio' => (bool) ($campo['obrigatorio'] ?? false),
                'opcoes' => array_values($campo['opcoes'] ?? []),
                'aceitos' => array_values($campo['aceitos'] ?? []),
                'max_arquivos' => min(10, max(1, (int) ($campo['max_arquivos'] ?? 1))),
                'validacao' => $campo['validacao'] ?? '',
            ], array_filter($config['campos'] ?? [], fn ($campo) => ! empty($campo['nome'])))),
            'estado' => $estado,
        ]);
    }

    public function inscrever(Request $request, Atividade $atividade, FormularioInscricaoService $servico): JsonResponse
    {
        abort_unless($atividade->formulario, 404, 'Esta atividade não possui formulário publicado.');
        abort_unless($atividade->ativo, 404, 'Esta atividade não está ativa.');

        $resultado = $servico->inscrever($request, $atividade);

        return response()->json($resultado, $resultado['sucesso'] ? 201 : 422);
    }
}
