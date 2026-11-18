<?php

return [
    'name' => 'Sistema Inteligente de Gestão e Planejamento Financeiro',
    'version' => '1.0.0',
    'environment' => getenv('APP_ENV') ?: 'development',
    'debug' => filter_var(
        getenv('APP_DEBUG') ?: true,
        FILTER_VALIDATE_BOOLEAN
    ),
    'timezone' => 'America/Sao_Paulo',
];
