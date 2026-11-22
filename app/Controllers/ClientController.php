<?php

class ClientController
{
    private ClientService $clientService;

    public function __construct()
    {
        $this->clientService = new ClientService();
    }

    public function index(): void
    {
        try {
            $user = AuthMiddleware::authenticate();
            Response::success($this->clientService->paginate($user['sub'], Request::all()));
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }

    public function show(string $id): void
    {
        try {
            $user = AuthMiddleware::authenticate();
            Response::success($this->clientService->find($user['sub'], $this->parseId($id)));
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }

    public function store(): void
    {
        try {
            $user = AuthMiddleware::authenticate();
            Response::success($this->clientService->create($user['sub'], Request::all()), 201);
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }

    public function update(string $id): void
    {
        try {
            $user = AuthMiddleware::authenticate();
            Response::success($this->clientService->update($user['sub'], $this->parseId($id), Request::all()));
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }

    public function destroy(string $id): void
    {
        try {
            $user = AuthMiddleware::authenticate();
            Response::success($this->clientService->deactivate($user['sub'], $this->parseId($id)));
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }

    private function parseId(string $id): int|string
    {
        $validated = FinPlanId::tryNormalize($id);
        if ($validated === null) {
            throw new InvalidArgumentException('Identificador de cliente inválido.');
        }

        return $validated;
    }

    private function handleException(Throwable $exception): void
    {
        if ($exception instanceof InvalidArgumentException) {
            Response::error($exception->getMessage(), 422);
        }

        if ($exception instanceof OutOfBoundsException) {
            Response::error($exception->getMessage(), 404);
        }

        if ($exception instanceof DomainException) {
            Response::error($exception->getMessage(), 409);
        }

        error_log('[Nymbrio API] Erro na operação de clientes: ' . $exception->getMessage());
        Response::error('Não foi possível concluir a operação de clientes.', 500);
    }
}
