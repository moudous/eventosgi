<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArquivoBiblioteca extends Model
{
    protected $table = 'arquivos_biblioteca';

    protected $fillable = [
        'nome', 'arquivo', 'mime', 'formato', 'tipo', 'tamanho', 'largura', 'altura',
        'tags', 'categoria', 'enviado_por',
    ];

    protected $casts = [
        'tamanho' => 'integer',
        'largura' => 'integer',
        'altura' => 'integer',
        'tags' => 'array',
        'enviado_por' => 'integer',
    ];

    public const CATEGORIAS = [
        'logo' => 'Logo',
        'fundo' => 'Fundo',
        'foto_evento' => 'Foto de evento',
        'imagem_decorativa' => 'Imagem decorativa',
        'foto_convidado' => 'Foto de convidado',
    ];

    public const TIPOS = [
        'imagem' => 'Imagem',
        'pdf' => 'PDF',
        'documento' => 'Documento',
        'planilha' => 'Planilha',
        'apresentacao' => 'Apresentação',
        'texto' => 'Texto',
    ];

    public function tamanhoFormatado(): string
    {
        if ($this->tamanho < 1024) return $this->tamanho.' B';
        if ($this->tamanho < 1024 * 1024) return number_format($this->tamanho / 1024, 1, ',', '.').' KB';

        return number_format($this->tamanho / 1024 / 1024, 1, ',', '.').' MB';
    }

    public function icone(): string
    {
        return match ($this->tipo) {
            'pdf' => 'bi-file-earmark-pdf text-danger',
            'documento' => 'bi-file-earmark-word text-primary',
            'planilha' => 'bi-file-earmark-spreadsheet text-success',
            'apresentacao' => 'bi-file-earmark-slides text-warning',
            'texto' => 'bi-file-earmark-text text-secondary',
            default => 'bi-file-earmark text-secondary',
        };
    }
}
