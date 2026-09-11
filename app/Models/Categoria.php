<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    protected $table = 'categorias';

    protected $fillable = ['nome', 'ativo'];

    protected $casts = ['ativo' => 'boolean'];

    public static function iconeParaNome(string $nome): string
    {
        $nome = \Illuminate\Support\Str::slug($nome);

        return match ($nome) {
            'palestra', 'palestras', 'conferencia', 'conferencias' => 'bi-mic',
            'minicurso', 'minicursos', 'mini-curso', 'mini-cursos', 'curso', 'cursos' => 'bi-book',
            'mesa-redonda', 'mesas-redondas', 'mesa-rendoda', 'debate', 'debates' => 'bi-people',
            'oficina', 'oficinas', 'workshop', 'workshops' => 'bi-tools',
            'hands-on', 'handson', 'hands-on-pratico' => 'bi-hand-index-thumb',
            'seminario', 'seminarios', 'simposio', 'simposios' => 'bi-easel',
            'congresso', 'congressos', 'jornada', 'jornadas', 'encontro', 'encontros' => 'bi-calendar-event',
            'painel', 'paineis', 'forum', 'foruns' => 'bi-chat-square-text',
            'exposicao', 'exposicoes', 'mostra', 'mostras' => 'bi-images',
            default => 'bi-tags',
        };
    }

    public function atividades(): HasMany
    {
        return $this->hasMany(Atividade::class);
    }

    /**
     * Ha atividade usando esta categoria?
     *
     * Conta tambem as apagadas: elas podem ser restauradas, e voltariam apontando para
     * uma categoria que nao existe mais.
     */
    public function temAtividades(): bool
    {
        return $this->atividades()->withTrashed()->exists();
    }
}
