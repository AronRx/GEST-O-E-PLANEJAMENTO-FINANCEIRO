<?php

class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function register(): void
    {
        try {
            $data = Request::all();
            $validator = new AuthValidator($data);
            $validator->validateRegister();

            if ($validator->fails()) {
                Response::error('Dados de cadastro inválidos.', 422, $validator->errors());
            }

            Response::success($this->authService->register($data), 201);
        } catch (DomainException $exception) {
            Response::error($exception->getMessage(), 409);
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        }
    }

    public function login(): void
    {
        try {
            $data = Request::all();
            $validator = new AuthValidator($data);
            $validator->validateLogin();

            if ($validator->fails()) {
                Response::error('Dados de login inválidos.', 422, $validator->errors());
            }

            Response::success($this->authService->login($data));
        } catch (UnexpectedValueException $exception) {
            Response::error($exception->getMessage(), 401);
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        }
    }

    public function logout(): void
    {
        AuthMiddleware::authenticate();
        Response::success($this->authService->logout());
    }

    public function me(): void
    {
        try {
            $user = AuthMiddleware::authenticate();
            Response::success($this->authService->me((int) $user['sub']));
        } catch (UnexpectedValueException $exception) {
            Response::error($exception->getMessage(), 401);
        }
    }
}
