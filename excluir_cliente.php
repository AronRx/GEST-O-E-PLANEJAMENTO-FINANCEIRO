<?php

include("config/conexao.php");

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {

    header("Location: index.php?pagina=clientes");
    exit;

}

$id = (int) $_GET["id"];

// Verifica se existem empréstimos

$stmt = $conexao->prepare("
SELECT COUNT(*) AS total
FROM emprestimos
WHERE cliente_id = ?
");

$stmt->bind_param("i", $id);

$stmt->execute();

$totalEmprestimos = $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();

// Se existir empréstimo, envia para confirmação

if ($totalEmprestimos > 0) {

    header("Location: confirmar_exclusao_cliente.php?id=".$id);
    exit;

}

// Caso contrário, exclui normalmente

$stmt = $conexao->prepare("
DELETE FROM clientes
WHERE id = ?
");

$stmt->bind_param("i", $id);

$stmt->execute();

$stmt->close();

header("Location: index.php?pagina=clientes");

exit;