<?php

class TokenTestController
{
    public function generate(): void
    {
        $token = Token::generate([
            'sub' => 1,
            'nome' => 'Kaio'
        ]);

        Response::success([
            'token' => $token
        ]);
    }

    public function validate(): void
    {
        $token = Request::input('token');

        if (!$token) {
            Response::error(
                'Token não informado.',
                400
            );
        }

        $payload = Token::validate($token);

        if ($payload === null) {
            Response::error(
                'Token inválido.',
                401
            );
        }

        Response::success([
            'valido' => true,
            'payload' => $payload
        ]);
    }
}
