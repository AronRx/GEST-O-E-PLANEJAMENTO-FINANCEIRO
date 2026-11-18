<?php

class FinPlanDatabase
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = trim((string) (getenv('FINPLAN_DB_HOST') ?: '127.0.0.1'));
        $port = trim((string) (getenv('FINPLAN_DB_PORT') ?: '5432'));
        $database = trim((string) (getenv('FINPLAN_DB_NAME') ?: 'finplan_homologacao'));
        $user = trim((string) (getenv('FINPLAN_DB_USER') ?: 'finplan_app'));
        $password = (string) (getenv('FINPLAN_DB_PASS') ?: '');
        $sslMode = strtolower(trim((string) (getenv('FINPLAN_DB_SSLMODE') ?: 'require')));
        $allowedSslModes = ['disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'];

        if (!in_array($sslMode, $allowedSslModes, true)) {
            throw new RuntimeException('FINPLAN_DB_SSLMODE contém um valor inválido.');
        }

        if ($password === '') {
            throw new RuntimeException('FINPLAN_DB_PASS não está configurada no arquivo .env.');
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s;connect_timeout=10',
            $host,
            $port,
            $database,
            $sslMode
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
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                    PDO::ATTR_TIMEOUT => 10
                ]
            );
        } catch (PDOException $exception) {
            error_log('[Nymbrio API] Erro de conexão PostgreSQL: ' . $exception->getMessage());
            throw new RuntimeException(
                'Não foi possível conectar ao banco Nymbrio. Confira FINPLAN_DB_* no arquivo .env.',
                0,
                $exception
            );
        }

        return self::$connection;
    }

    public static function runAsUser(int $userId, callable $callback): mixed
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Identificador de usuário inválido.');
        }

        $pdo = self::connection();

        if ($pdo->inTransaction()) {
            throw new LogicException('Não é permitido iniciar um contexto Nymbrio dentro de outra transação.');
        }

        $pdo->beginTransaction();

        try {
            $context = $pdo->prepare('SELECT finplan.fn_iniciar_contexto(:user_id)');
            $context->execute(['user_id' => $userId]);

            $result = $callback($pdo);

            $pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
}
