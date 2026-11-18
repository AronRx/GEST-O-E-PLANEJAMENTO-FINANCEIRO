<?php

class Request
{
    public static function method(): string
    {
        return strtoupper(
            $_SERVER['REQUEST_METHOD'] ?? 'GET'
        );
    }

    public static function input(
        ?string $key = null,
        mixed $default = null
    ): mixed {
        $data = self::all();

        if ($key === null) {
            return $data;
        }

        return $data[$key] ?? $default;
    }

    public static function all(): array
    {
        $method = self::method();

        if ($method === 'GET') {
            return $_GET;
        }

        if (!empty($_POST)) {
            return $_POST;
        }

        return self::json();
    }

    public static function has(string $key): bool
    {
        return array_key_exists(
            $key,
            self::all()
        );
    }

    public static function header(
        string $name,
        ?string $default = null
    ): ?string {
        $normalizedName = strtoupper(
            str_replace('-', '_', $name)
        );

        $serverKey = 'HTTP_' . $normalizedName;

        if (isset($_SERVER[$serverKey])) {
            return trim(
                (string) $_SERVER[$serverKey]
            );
        }

        if ($normalizedName === 'CONTENT_TYPE') {
            if (isset($_SERVER['CONTENT_TYPE'])) {
                return trim(
                    (string) $_SERVER['CONTENT_TYPE']
                );
            }
        }

        if ($normalizedName === 'CONTENT_LENGTH') {
            if (isset($_SERVER['CONTENT_LENGTH'])) {
                return trim(
                    (string) $_SERVER['CONTENT_LENGTH']
                );
            }
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();

            foreach ($headers as $headerName => $value) {
                if (
                    strtolower($headerName) ===
                    strtolower($name)
                ) {
                    return trim(
                        (string) $value
                    );
                }
            }
        }

        return $default;
    }

    public static function contentType(): string
    {
        return strtolower(
            trim(
                self::header(
                    'Content-Type',
                    ''
                )
            )
        );
    }

    public static function isJson(): bool
    {
        return str_contains(
            self::contentType(),
            'application/json'
        );
    }

    private static function json(): array
    {
        if (!self::isJson()) {
            return [];
        }

        $rawInput = file_get_contents(
            'php://input'
        );

        if (
            $rawInput === false ||
            trim($rawInput) === ''
        ) {
            return [];
        }

        $data = json_decode(
            $rawInput,
            true
        );

        if (
            json_last_error() !==
            JSON_ERROR_NONE
        ) {
            Response::error(
                'O corpo da requisição contém um JSON inválido.',
                400
            );
        }

        return is_array($data)
            ? $data
            : [];
    }
}
