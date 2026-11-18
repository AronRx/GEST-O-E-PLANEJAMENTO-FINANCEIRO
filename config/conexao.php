<?php

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/PgMysqliCompat.php';

try {
    $pdo = Database::connection();

    $compatibilitySchemaExists = (bool) $pdo->query(
        "SELECT EXISTS (SELECT 1 FROM pg_namespace WHERE nspname = 'legacy_app')"
    )->fetchColumn();

    if (!$compatibilitySchemaExists) {
        throw new RuntimeException(
            'A camada de compatibilidade ainda não foi instalada. Execute database/postgresql_compat.sql após database/schema.sql.'
        );
    }

    $userId = Database::legacyUserId($pdo);
    $pdo->exec('SET search_path TO legacy_app, public');

    $setUser = $pdo->prepare("SELECT set_config('app.sf_usuario_id', :user_id, false)");
    $setUser->execute(['user_id' => $userId]);

    $conexao = new PgMysqliCompat($pdo);
} catch (Throwable $exception) {
    error_log('[SistemaFinanceiro] Falha ao inicializar a conexão legada: ' . $exception->getMessage());

    http_response_code(500);
    exit(
        'Não foi possível inicializar o banco PostgreSQL. ' .
        htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8')
    );
}
