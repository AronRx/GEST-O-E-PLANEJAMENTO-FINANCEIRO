<?php

class Response
{
    public static function json(
        mixed $data = null,
        int $statusCode = 200,
        array $headers = []
    ): never {
        http_response_code($statusCode);

        header(
            'Content-Type: application/json; charset=utf-8'
        );

        foreach ($headers as $name => $value) {
            header(
                "{$name}: {$value}"
            );
        }

        $json = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            http_response_code(500);

            echo json_encode(
                [
                    'success' => false,
                    'message' => 'Não foi possível gerar a resposta JSON.'
                ],
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );

            exit;
        }

        echo $json;

        exit;
    }

    public static function success(
        mixed $data = null,
        int $statusCode = 200
    ): never {
        self::json(
            [
                'success' => true,
                'data' => $data
            ],
            $statusCode
        );
    }

    public static function error(
        string $message,
        int $statusCode = 400,
        mixed $errors = null
    ): never {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        self::json(
            $response,
            $statusCode
        );
    }

    public static function noContent(): never
    {
        http_response_code(204);

        exit;
    }
}
