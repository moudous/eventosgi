<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveKitConfiguracao extends Model
{
    protected $table = 'livekit_configuracoes';

    protected $fillable = ['url', 'api_key', 'api_secret'];

    protected $casts = ['api_secret' => 'encrypted'];

    public static function atual(): ?self
    {
        return static::query()->latest('id')->first();
    }
}
