<?php

require_once __DIR__ . '/config/autoload.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = Database::connection();

    echo "CONEXAO COM POSTGRESQL: OK\n\n";

    echo "Banco conectado: ";
    echo $pdo->query("SELECT current_database()")->fetchColumn();

    echo "\nVersao: ";
    echo $pdo->query("SELECT version()")->fetchColumn();

    $tabelas = $pdo->query("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = 'public'
        AND table_type = 'BASE TABLE'
    ")->fetchColumn();

    echo "\n\nTotal de tabelas: " . $tabelas;

} catch (Throwable $e) {
    http_response_code(500);
    echo "ERRO NA CONEXAO:\n";
    echo $e->getMessage();
}