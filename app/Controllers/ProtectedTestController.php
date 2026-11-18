<?php

class ProtectedTestController
{
    public function index(): void
    {
        $user = AuthMiddleware::authenticate();

        Response::success([
            'mensagem' => 'Você acessou uma rota protegida.',
            'usuario' => $user
        ]);
    }
}
