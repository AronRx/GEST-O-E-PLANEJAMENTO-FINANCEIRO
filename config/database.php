<?php

class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $host = getenv('DB_HOST') ?: 'localhost';
            $port = getenv('DB_PORT') ?: '5432';
            $database = getenv('DB_NAME') ?: 'sistema_financeiro';
            $user = getenv('DB_USER') ?: 'postgres';
            $password = getenv('DB_PASS') ?: '';

            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $host,
                $port,
                $database
            );

            try {
                self::$connection = new PDO(
                    $dsn,
                    $user,
                    $password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_STRINGIFY_FETCHES => false
                    ]
                );
            } catch (PDOException $exception) {
                error_log('[SistemaFinanceiro] Erro PostgreSQL: ' . $exception->getMessage());
                throw new RuntimeException(
                    'Não foi possível conectar ao PostgreSQL. Confira o arquivo .env e o log do PHP.',
                    0,
                    $exception
                );
            }
        }

        return self::$connection;
    }

    public static function legacyUserId(?PDO $pdo = null): string
    {
        $pdo ??= self::connection();
        $configuredId = trim((string) (getenv('APP_USER_ID') ?: ''));

        if ($configuredId !== '') {
            if (!self::isUuid($configuredId)) {
                throw new RuntimeException('APP_USER_ID precisa conter um UUID válido da tabela usuarios.');
            }

            $statement = $pdo->prepare(
                'SELECT id FROM public.usuarios WHERE id = :id AND ativo IS TRUE LIMIT 1'
            );
            $statement->execute(['id' => $configuredId]);
            $userId = $statement->fetchColumn();

            if (!$userId) {
                throw new RuntimeException('O APP_USER_ID informado não corresponde a um usuário ativo em public.usuarios.');
            }

            return (string) $userId;
        }

        $statement = $pdo->query(
            'SELECT id FROM public.usuarios WHERE ativo IS TRUE ORDER BY criado_em LIMIT 2'
        );
        $users = $statement->fetchAll(PDO::FETCH_COLUMN);

        if (count($users) === 1) {
            return (string) $users[0];
        }

        if (count($users) === 0) {
            throw new RuntimeException(
                'Não há usuário ativo em public.usuarios. Cadastre um usuário antes de utilizar as telas financeiras.'
            );
        }

        throw new RuntimeException(
            'Há mais de um usuário ativo. Defina APP_USER_ID no .env para indicar a conta que será usada pelas telas financeiras antigas.'
        );
    }

    public static function isUuid(string $value): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value
        ) === 1;
    }
}
