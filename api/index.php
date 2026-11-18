<?php

require_once dirname(__DIR__) . '/config/autoload.php';

require_once dirname(__DIR__) . '/routes/api.php';

try {
    $method = Request::method();

    $path = parse_url(
        $_SERVER['REQUEST_URI'] ?? '/',
        PHP_URL_PATH
    );

    $scriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/api/index.php'));
    $scriptDirectory = rtrim(dirname($scriptPath), '/.');
    $basePath = preg_replace('~/api$~', '', $scriptDirectory) ?: '';

    if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
        $path = substr($path, strlen($basePath));
    }

    $router->dispatch(
        $method,
        $path
    );
} catch (Throwable $exception) {

    error_log(
        sprintf(
            '[SistemaFinanceiro] %s em %s:%d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        )
    );

    Response::error(
        'Ocorreu um erro interno no servidor.',
        500
    );
}
