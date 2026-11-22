<?php

class AuthService
{
    private UserRepository $userRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
    }

    public function register(array $data): array
    {
        $name = trim((string) ($data['nome'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['senha'] ?? '');

        if ($name === '' || $email === '' || $password === '') {
            throw new InvalidArgumentException('Nome, e-mail e senha são obrigatórios.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Informe um e-mail válido.');
        }

        if (strlen($name) > 120 || strlen($email) > 160) {
            throw new InvalidArgumentException('Nome ou e-mail excede o limite permitido.');
        }

        if (strlen($password) < 8) {
            throw new InvalidArgumentException('A senha deve possuir pelo menos 8 caracteres.');
        }

        if (strlen($password) > 72) {
            throw new InvalidArgumentException('A senha deve possuir no máximo 72 bytes nesta versão.');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if ($passwordHash === false) {
            throw new RuntimeException('Não foi possível proteger a senha informada.');
        }

        try {
            $id = $this->userRepository->create([
                'nome' => $name,
                'email' => $email,
                'senha_hash' => $passwordHash
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23505') {
                throw new DomainException('Este e-mail já está cadastrado.');
            }

            throw $exception;
        }

        return [
            'id' => $id,
            'nome' => $name,
            'email' => $email,
            'message' => 'Cadastro realizado com sucesso.'
        ];
    }

    public function login(array $data): array
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['senha'] ?? '');

        if ($email === '' || $password === '') {
            throw new InvalidArgumentException('E-mail e senha são obrigatórios.');
        }

        $user = $this->userRepository->findByEmail($email);

        if (
            !$user ||
            !password_verify($password, (string) ($user['senha_hash'] ?? '')) ||
            !self::databaseBoolean($user['ativo'] ?? false)
        ) {
            throw new UnexpectedValueException('E-mail ou senha inválidos.');
        }

        $userId = FinPlanId::normalize($user['id']);
        $this->userRepository->registerLogin($userId);

        $token = Token::generate([
            'sub' => $userId,
            'perfil' => (string) $user['perfil']
        ]);

        return [
            'token_type' => 'Bearer',
            'access_token' => $token,
            'expires_in' => self::tokenTtl(),
            'usuario' => [
                'id' => $userId,
                'nome' => (string) $user['nome'],
                'email' => $email,
                'perfil' => (string) $user['perfil']
            ]
        ];
    }

    public function logout(): array
    {
        return [
            'logged_out' => true,
            'token_revoked' => false,
            'message' => 'Remova o token do armazenamento do cliente. O token atual continuará válido até expirar.'
        ];
    }

    private static function databaseBoolean(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        return is_string($value) && in_array(strtolower($value), ['t', 'true', 'yes', 'on'], true);
    }

    private static function tokenTtl(): int
    {
        $ttl = (int) (getenv('TOKEN_TTL') ?: 7200);
        return $ttl > 0 ? $ttl : 7200;
    }

    public function me(int|string $userId): array
    {
        $user = $this->userRepository->findById($userId);

        if (!$user || !self::databaseBoolean($user['ativo'] ?? false)) {
            throw new UnexpectedValueException('Sessão inválida ou usuário inativo.');
        }

        return [
            'id' => FinPlanId::normalize($user['id']),
            'nome' => (string) $user['nome'],
            'email' => (string) $user['email'],
            'perfil' => (string) $user['perfil'],
            'ultimo_login' => $user['ultimo_login'],
            'criado_em' => $user['criado_em']
        ];
    }
}
