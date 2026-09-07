<?php

return [
    'name' => env('GI_NAME', 'Aplicação externa do GI'),
    'gi_url' => env('GI_URL', 'http://localhost:8000'),
    'client_id' => env('GI_CLIENT_ID'),
    'client_secret' => env('GI_CLIENT_SECRET'),
    'frame_ancestors' => env('GI_FRAME_ANCESTORS', env('GI_URL', 'http://localhost:8000')),
    'allow_outside_iframe' => env('GI_ALLOW_OUTSIDE_IFRAME', false),

    /*
     * Disparo de e-mail pela API do GI (POST {gi_url}/api/v1/email-disparos).
     * O token e o da API GI (Bearer), gerado no GI para um usuario que administre o sistema informado.
     * O visitante do formulario publico nao possui sessao no GI, por isso o envio usa este token fixo
     * em vez do access_token da sessao.
     */
    'sistema_id' => env('GI_SISTEMA_ID'),
    'api_token' => env('GI_API_TOKEN'),
    'smtp_configuracao_id' => env('GI_SMTP_CONFIGURACAO_ID'),
    'email_remetente_nome' => env('GI_EMAIL_REMETENTE_NOME', env('APP_NAME', 'Gestão de Eventos')),
    'email_remetente_email' => env('GI_EMAIL_REMETENTE_EMAIL'),
];
