<?php

class LoanController
{
    private LoanService $loanService;

    public function __construct()
    {
        $this->loanService = new LoanService();
    }

    public function index(): void
    {
        try {
            $user = AuthMiddleware::authenticate();
            Response::success($this->loanService->paginate((int) $user['sub'], Request::all()));
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }

    public function show(string $id): void
    {
        try {
            $user = AuthMiddleware::authenticate();
            Response::success($this->loanService->find((int) $user['sub'], $this->parseId($id)));
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }

    public function store(): void
    {
        try {
            $user = AuthMiddleware::authenticate();
            Response::success($this->loanService->create((int) $user['sub'], Request::all()), 201);
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }

    public function simulate(): void
    {
        try {
            AuthMiddleware::authenticate();
            Response::success($this->loanService->simulate(Request::all()));
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }

    public function installments(string $id): void
    {
        try {
            $user = AuthMiddleware::authenticate();
            Response::success($this->loanService->installments((int) $user['sub'], $this->parseId($id)));
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }

    public function receive(string $id): void
    {
        try {
            $user = AuthMiddleware::authenticate();
            Response::success($this->loanService->registerReceipt((int) $user['sub'], $this->parseId($id), Request::all()), 201);
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }

    public function cancel(string $id): void
    {
        try {
            $user = AuthMiddleware::authenticate();
            Response::success($this->loanService->cancel((int) $user['sub'], $this->parseId($id)));
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }

    private function parseId(string $id): int
    {
        $validated = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($validated === false) {
            throw new InvalidArgumentException('Identificador inválido.');
        }
        return (int) $validated;
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

        error_log('[Nymbrio API] Erro na operação de empréstimos: ' . $exception->getMessage());
        Response::error('Não foi possível concluir a operação de empréstimos.', 500);
    }
}
