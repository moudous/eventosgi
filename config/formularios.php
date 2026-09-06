<?php

return [
    // Token exigido no cabecalho X-Formulario-Token para consumir a API publica de formularios.
    'token' => env('FORMULARIOS_API_TOKEN'),

    // Origens autorizadas a chamar a API pelo navegador. Deixe vazio para permitir apenas chamadas servidor a servidor.
    'origens' => array_values(array_filter(array_map('trim', explode(' ', (string) env('FORMULARIOS_API_ORIGENS', ''))))),
];
