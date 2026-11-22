<?php

class FinPlanWeb
{
    private static bool $userResolved = false;
    private static ?array $resolvedUser = null;

    public static function currentUser(): ?array
    {
        if (self::$userResolved) {
            return self::$resolvedUser;
        }

        self::$userResolved = true;
        $token = (string) ($_SESSION['finplan_web_token'] ?? '');
        if ($token === '') {
            return null;
        }

        $payload = Token::validate($token);
        if ($payload === null) {
            unset($_SESSION['finplan_web_token'], $_SESSION['finplan_web_user']);
            return null;
        }

        $userId = FinPlanId::tryNormalize($payload['sub'] ?? null);
        if ($userId === null) {
            unset($_SESSION['finplan_web_token'], $_SESSION['finplan_web_user']);
            return null;
        }

        try {
            $user = (new UserRepository())->findById($userId);
            if (!$user || !self::boolean($user['ativo'] ?? false)) {
                unset($_SESSION['finplan_web_token'], $_SESSION['finplan_web_user']);
                return null;
            }

            self::$resolvedUser = [
                'id' => FinPlanId::normalize($user['id']),
                'nome' => (string) $user['nome'],
                'email' => (string) $user['email'],
                'perfil' => (string) $user['perfil']
            ];
            return self::$resolvedUser;
        } catch (Throwable $exception) {
            error_log('[Nymbrio Web] Falha ao validar sessão: ' . $exception->getMessage());
            unset($_SESSION['finplan_web_token'], $_SESSION['finplan_web_user']);
            return null;
        }
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['finplan_csrf'])) {
            $_SESSION['finplan_csrf'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['finplan_csrf'];
    }

    public static function verifyCsrf(): void
    {
        $sent = (string) ($_POST['_csrf'] ?? '');
        $expected = (string) ($_SESSION['finplan_csrf'] ?? '');
        if ($sent === '' || $expected === '' || !hash_equals($expected, $sent)) {
            throw new RuntimeException('A sessão do formulário expirou. Atualize a página e tente novamente.');
        }
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['finplan_flash'] = ['type' => $type, 'message' => $message];
    }

    public static function pullFlash(): ?array
    {
        $flash = $_SESSION['finplan_flash'] ?? null;
        unset($_SESSION['finplan_flash']);
        return is_array($flash) ? $flash : null;
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function money(mixed $value): string
    {
        return 'R$ ' . number_format((float) ($value ?? 0), 2, ',', '.');
    }

    public static function date(mixed $value): string
    {
        if (!$value) {
            return '—';
        }
        try {
            return (new DateTimeImmutable((string) $value))->format('d/m/Y');
        } catch (Throwable) {
            return '—';
        }
    }

    public static function dashboard(int|string $userId): array
    {
        $userId = FinPlanId::normalize($userId);

        return FinPlanDatabase::runAsUser($userId, function (PDO $pdo) use ($userId): array {
            $sql = <<<'SQL'
                WITH meus_clientes AS (
                    SELECT id, ativo
                    FROM finplan.clientes
                    WHERE usuario_id = :usuario_id
                ),
                meus_emprestimos AS (
                    SELECT e.*
                    FROM finplan.vw_emprestimos e
                    JOIN meus_clientes c ON c.id = e.cliente_id
                ),
                minhas_parcelas AS (
                    SELECT p.*
                    FROM finplan.vw_parcelas p
                    JOIN finplan.emprestimos e ON e.id = p.emprestimo_id
                    JOIN meus_clientes c ON c.id = e.cliente_id
                )
                SELECT
                    (SELECT COUNT(*) FROM meus_clientes WHERE ativo IS TRUE) AS clientes_ativos,
                    (SELECT COUNT(*) FROM meus_emprestimos) AS emprestimos_total,
                    (SELECT COUNT(*) FROM meus_emprestimos WHERE situacao = 'ativo') AS emprestimos_ativos,
                    (SELECT COUNT(*) FROM meus_emprestimos WHERE situacao = 'quitado') AS emprestimos_quitados,
                    (SELECT COALESCE(SUM(valor_principal), 0) FROM meus_emprestimos WHERE status <> 'cancelado') AS principal_total,
                    (SELECT COALESCE(SUM(total_pago), 0) FROM meus_emprestimos) AS total_recebido,
                    (SELECT COALESCE(SUM(saldo_a_receber), 0) FROM meus_emprestimos WHERE status <> 'cancelado') AS saldo_a_receber,
                    (SELECT COUNT(*) FROM minhas_parcelas WHERE status = 'atrasada') AS parcelas_atrasadas
            SQL;

            $statement = $pdo->prepare($sql);
            $statement->execute(['usuario_id' => $userId]);

            return $statement->fetch() ?: [];
        });
    }

    public static function installment(int|string $userId, int|string $installmentId): ?array
    {
        $userId = FinPlanId::normalize($userId);
        $installmentId = FinPlanId::normalize($installmentId);

        return FinPlanDatabase::runAsUser($userId, function (PDO $pdo) use ($userId, $installmentId): ?array {
            $statement = $pdo->prepare(
                'SELECT p.id, p.emprestimo_id, p.numero, p.vencimento, p.valor,
                        p.valor_pago, p.saldo, p.status, c.nome AS cliente_nome,
                        e.status AS emprestimo_status
                 FROM finplan.vw_parcelas p
                 JOIN finplan.emprestimos e ON e.id = p.emprestimo_id
                 JOIN finplan.clientes c ON c.id = e.cliente_id
                 WHERE p.id = :id AND c.usuario_id = :usuario_id
                 LIMIT 1'
            );
            $statement->execute(['id' => $installmentId, 'usuario_id' => $userId]);
            $row = $statement->fetch();
            return $row ?: null;
        });
    }

    public static function activeAccounts(int|string $userId): array
    {
        $userId = FinPlanId::normalize($userId);

        return FinPlanDatabase::runAsUser($userId, function (PDO $pdo) use ($userId): array {
            $statement = $pdo->prepare(
                "SELECT id, nome, tipo
                 FROM finplan.contas
                 WHERE ativa IS TRUE AND usuario_id = :usuario_id
                 ORDER BY CASE WHEN nome = 'Carteira' THEN 0 ELSE 1 END, nome"
            );
            $statement->execute(['usuario_id' => $userId]);
            return $statement->fetchAll();
        });
    }

    private static function boolean(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }
        return is_string($value) && in_array(strtolower($value), ['t', 'true', 'yes', 'on'], true);
    }
}
