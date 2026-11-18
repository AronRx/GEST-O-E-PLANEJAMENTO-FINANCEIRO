<?php

require_once dirname(__DIR__) . '/config/autoload.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$pdo = Database::connection();

function perguntar(string $mensagem): string
{
    fwrite(STDOUT, $mensagem);
    return trim((string) fgets(STDIN));
}

$nome = perguntar('Nome do usuário: ');
$email = strtolower(perguntar('E-mail: '));
$senha = perguntar('Senha (mínimo 8 caracteres): ');

if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 8) {
    fwrite(STDERR, "Dados inválidos. Confira nome, e-mail e senha.\n");
    exit(1);
}

$exists = $pdo->prepare('SELECT 1 FROM public.usuarios WHERE lower(email) = lower(:email) LIMIT 1');
$exists->execute(['email' => $email]);
if ($exists->fetchColumn()) {
    fwrite(STDERR, "Já existe um usuário com esse e-mail.\n");
    exit(1);
}

$insert = $pdo->prepare(
    'INSERT INTO public.usuarios (nome, email, senha_hash) VALUES (:nome, :email, :senha_hash) RETURNING id'
);
$insert->execute([
    'nome' => $nome,
    'email' => $email,
    'senha_hash' => password_hash($senha, PASSWORD_DEFAULT)
]);

$id = (string) $insert->fetchColumn();
fwrite(STDOUT, "Usuário criado com sucesso. UUID: {$id}\n");
fwrite(STDOUT, "Com apenas um usuário ativo, APP_USER_ID pode permanecer vazio no .env.\n");
