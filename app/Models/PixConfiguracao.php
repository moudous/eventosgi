<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PixConfiguracao extends Model
{
    protected $table = 'pix_configuracoes';

    protected $fillable = ['ativo', 'ambiente', 'client_id', 'client_secret', 'sandbox_token', 'chave_pix', 'certificado_pem', 'chave_privada_pem', 'senha_chave', 'token_url', 'api_url'];

    protected $casts = [
        'ativo' => 'boolean',
        'client_id' => 'encrypted',
        'client_secret' => 'encrypted',
        'sandbox_token' => 'encrypted',
        'chave_pix' => 'encrypted',
        'certificado_pem' => 'encrypted',
        'chave_privada_pem' => 'encrypted',
        'senha_chave' => 'encrypted',
    ];

    public const TOKEN_URL = 'https://auth.sicoob.com.br/auth/realms/cooperado/protocol/openid-connect/token';
    public const API_URL = 'https://api.sicoob.com.br/pix/api/v2';
    public const SANDBOX_API_URL = 'https://sandbox.sicoob.com.br/sicoob/sandbox/pix/api/v2';

    public static function atual(): ?self
    {
        return static::query()->first();
    }
}
