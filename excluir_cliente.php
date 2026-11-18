<?php

include("config/conexao.php");

if (!isset($_GET["id"]) || !Database::isUuid(trim((string) $_GET["id"]))) {

    header("Location: index.php?pagina=clientes");
    exit;

}

$id = trim((string) $_GET["id"]);

$stmt = $conexao->prepare("
SELECT COUNT(*) AS total
FROM emprestimos
WHERE cliente_id = ?
");

$stmt->bind_param("s", $id);

$stmt->execute();

$totalEmprestimos = $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();

if ($totalEmprestimos > 0) {

    header("Location: confirmar_exclusao_cliente.php?id=".$id);
    exit;

}

$stmt = $conexao->prepare("
DELETE FROM clientes
WHERE id = ?
");

$stmt->bind_param("s", $id);

$stmt->execute();

$stmt->close();

header("Location: index.php?pagina=clientes");

exit;
