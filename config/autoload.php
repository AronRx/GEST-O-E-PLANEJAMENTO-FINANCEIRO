<?php

$envFile = dirname(__DIR__) . '/.env';

if (file_exists($envFile)) {
    $lines = file(
        $envFile,
        FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
    );

    foreach ($lines as $line) {
        $line = trim($line);

        if (
            $line === '' ||
            str_starts_with($line, '#')
        ) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode(
            '=',
            $line,
            2
        );

        $name = trim($name);
        $value = trim($value);

        if (
            strlen($value) >= 2 &&
            (
                ($value[0] === '"' && $value[-1] === '"') ||
                ($value[0] === "'" && $value[-1] === "'")
            )
        ) {
            $value = substr(
                $value,
                1,
                -1
            );
        }

        putenv(
            "{$name}={$value}"
        );
    }
}

$configFiles = [
    dirname(__DIR__) . '/config/database.php'
];

foreach ($configFiles as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

spl_autoload_register(function (string $class): void {
    $basePath = dirname(__DIR__) . '/';

    $directories = [
        'app/Controllers/',
        'app/Services/',
        'app/Repositories/',
        'app/Models/',
        'app/Validators/',
        'app/Middleware/',
        'app/Helpers/'
    ];

    foreach ($directories as $directory) {
        $file = $basePath .
            $directory .
            $class .
            '.php';

        if (file_exists($file)) {
            require_once $file;

            return;
        }
    }
});
