<?php

require_once dirname(__DIR__) . '/config/autoload.php';

require_once dirname(__DIR__) . '/routes/api.php';

try {
    $method = Request::method();

    $path = parse_url(
        $_SERVER['REQUEST_URI'] ?? '/',
        PHP_URL_PATH
    );

    $basePath = '/SistemaFinanceiro';

    if (str_starts_with($path, $basePath)) {
        $path = substr(
            $path,
            strlen($basePath)
        );
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
