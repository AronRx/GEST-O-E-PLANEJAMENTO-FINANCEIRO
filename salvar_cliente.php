<?php

include("config/conexao.php");

$nome = trim((string) ($_POST["nome"] ?? ""));
$cpf = trim((string) ($_POST["cpf"] ?? ""));
$rg = trim((string) ($_POST["rg"] ?? ""));
$telefone = trim((string) ($_POST["telefone"] ?? ""));
$whatsapp = trim((string) ($_POST["whatsapp"] ?? ""));
$email = trim((string) ($_POST["email"] ?? ""));
$cep = trim((string) ($_POST["cep"] ?? ""));
$rua = trim((string) ($_POST["rua"] ?? ""));
$numero = trim((string) ($_POST["numero"] ?? ""));
$bairro = trim((string) ($_POST["bairro"] ?? ""));
$cidade = trim((string) ($_POST["cidade"] ?? ""));
$estado = trim((string) ($_POST["estado"] ?? ""));
$observacoes = trim((string) ($_POST["observacoes"] ?? ""));

if ($nome === "") {
    http_response_code(422);
    exit('O nome do cliente é obrigatório.');
}

if ($cpf !== "") {
    $stmt = $conexao->prepare("SELECT id FROM clientes WHERE cpf = ? LIMIT 1");
    $stmt->bind_param("s", $cpf);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        echo "<div style='max-width:600px;margin:50px auto;padding:20px;background:#ffe5e5;border:1px solid #ff4d4d;border-radius:8px;font-family:Arial'>
            <h2>CPF já cadastrado!</h2>
            <p>Já existe um cliente utilizando este CPF.</p>
            <br><a href='javascript:history.back()'>Voltar</a>
        </div>";
        exit;
    }
}

$sql = "INSERT INTO clientes
(nome, cpf, rg, telefone, whatsapp, email, cep, rua, numero, bairro, cidade, estado, observacoes)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

try {
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param(
        "sssssssssssss",
        $nome,
        $cpf,
        $rg,
        $telefone,
        $whatsapp,
        $email,
        $cep,
        $rua,
        $numero,
        $bairro,
        $cidade,
        $estado,
        $observacoes
    );
    $stmt->execute();

    header("Location: index.php?pagina=clientes");
    exit;
} catch (PDOException $exception) {
    if (($exception->errorInfo[0] ?? $exception->getCode()) === '23505') {
        echo "<div style='max-width:600px;margin:50px auto;padding:20px;border-radius:10px;background:#ffe5e5;border:1px solid #ff6b6b;font-family:Arial'>
            <h3>CPF já cadastrado.</h3>
            <p>Já existe um cliente utilizando esse CPF.</p>
            <br><a href='javascript:history.back()'>Voltar</a>
        </div>";
        exit;
    }

    error_log('[SistemaFinanceiro] Erro ao cadastrar cliente: ' . $exception->getMessage());
    http_response_code(500);
    exit('Não foi possível cadastrar o cliente. Consulte o log do PHP.');
}
