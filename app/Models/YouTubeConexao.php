<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YouTubeConexao extends Model
{
    protected $table = 'youtube_conexoes';

    protected $fillable = [
        'canal_id', 'canal_titulo', 'access_token', 'refresh_token', 'expira_em', 'conectado_por',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'expira_em' => 'datetime',
        'conectado_por' => 'integer',
    ];

    public static function atual(): ?self
    {
        return static::query()->latest('id')->first();
    }
}
