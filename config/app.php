<?php

$environment = getenv('APP_ENV') ?: 'production';
$debugValue = getenv('APP_DEBUG');

return [
    'name' => getenv('APP_NAME') ?: 'Nymbrio',
    'version' => '1.0.0',
    'environment' => $environment,
    'debug' => $debugValue === false
        ? $environment !== 'production'
        : filter_var($debugValue, FILTER_VALIDATE_BOOLEAN),
    'timezone' => 'America/Sao_Paulo',
];
