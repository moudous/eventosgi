<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaginaPadraoEvento extends Model
{
    protected $table = 'pagina_padrao_evento';

    protected $fillable = ['evento_id', 'configuracoes'];

    protected $casts = ['evento_id' => 'integer', 'configuracoes' => 'array'];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public static function padrao(): array
    {
        return [
            'layout' => 'fixo',
            'cabecalho' => ['tipo' => 'degrade', 'inicio' => '#102a43', 'fim' => '#176b87', 'solida' => '#102a43', 'fonte' => '#ffffff', 'imagem' => null],
            'card' => ['tipo' => 'solida', 'inicio' => '#ffffff', 'fim' => '#f3f6fa', 'solida' => '#ffffff'],
            'mostrar_imagem_evento' => true,
            'altura_imagem_evento' => 220,
            'imagem_evento' => null,
            'conteudo' => ['html' => '', 'altura' => 'auto', 'altura_px' => 420],
            'cronograma' => ['mostrar' => true, 'etapas' => []],
            'participantes' => ['mostrar' => true, 'foto' => true, 'redes' => true, 'curriculo' => true, 'colunas' => 3],
            'atividades' => ['mostrar' => true, 'agrupar_categoria' => true, 'mostrar_inscricao' => true],
            'cards' => [],
            'submissoes' => [],
            'faq' => ['mostrar' => false, 'itens' => []],
        ];
    }

    public static function submissaoPadrao(int $submissaoId, string $titulo = ''): array
    {
        return [
            'submissao_id' => $submissaoId,
            'titulo_html' => '<h2>Submissão de trabalhos acadêmicos</h2>',
            'descricao_html' => '<p>Envie seu resumo para participar do evento.</p>',
            'fundo_tipo' => 'solida',
            'fundo_inicio' => '#041740',
            'fundo_fim' => '#041740',
            'fundo_solida' => '#041740',
            'fonte' => '#ffffff',
            'botao_texto' => 'Submeter resumo',
            'botao_fundo' => '#34C0D0',
            'botao_fonte' => '#000000',
        ];
    }

    public function configuracaoCompleta(): array
    {
        return array_replace_recursive(self::padrao(), (array) $this->configuracoes);
    }
}
