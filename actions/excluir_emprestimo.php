<?php

session_start();

require_once("../config/conexao.php");

if (!isset($_GET["id"]) || !Database::isUuid(trim((string) $_GET["id"]))) {

    header("Location: ../index.php?pagina=emprestimos");
    exit;

}

$id = trim((string) $_GET["id"]);

$stmt = $conexao->prepare("
SELECT

e.id,
e.valor_emprestado,
e.valor_total,
e.quantidade_parcelas,

c.nome

FROM emprestimos e

INNER JOIN clientes c
ON c.id = e.cliente_id

WHERE e.id = ?

LIMIT 1
");

$stmt->bind_param("s", $id);

$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows == 0) {

    $stmt->close();

    die("Empréstimo não encontrado.");

}

$emprestimo = $resultado->fetch_assoc();

$stmt->close();

$stmt = $conexao->prepare("
SELECT

COUNT(*) AS total

FROM parcelas

WHERE emprestimo_id = ?
AND data_pagamento IS NOT NULL
");

$stmt->bind_param("s", $id);

$stmt->execute();

$totalParcelasPagas = $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();

if ($totalParcelasPagas > 0) {

    header("Location: confirmar_exclusao_emprestimo.php?id=".$id);

    exit;

}

if ($_SERVER["REQUEST_METHOD"] != "POST") {

?>

<!DOCTYPE html>

<html lang="pt-br">

<head>

<meta charset="UTF-8">

<title>Excluir Empréstimo</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{

background:#f1f5f9;

}

.card{

margin-top:70px;

border:none;

border-radius:15px;

}

</style>

</head>

<body>

<div class="container">

<div class="row justify-content-center">

<div class="col-lg-7">

<div class="card shadow">

<div class="card-body p-5">

<h2 class="text-danger">

Excluir Empréstimo

</h2>

<hr>

<p>

Você está prestes a excluir permanentemente o seguinte empréstimo:

</p>

<table class="table">

<tr>

<th>Cliente</th>

<td><?= htmlspecialchars($emprestimo["nome"]) ?></td>

</tr>

<tr>

<th>Valor Emprestado</th>

<td>

R$ <?= number_format($emprestimo["valor_emprestado"],2,",",".") ?>

</td>

</tr>

<tr>

<th>Valor Total</th>

<td>

R$ <?= number_format($emprestimo["valor_total"],2,",",".") ?>

</td>

</tr>

<tr>

<th>Parcelas</th>

<td>

<?= $emprestimo["quantidade_parcelas"] ?>

</td>

</tr>

</table>

<div class="alert alert-warning">

<strong>Atenção!</strong>

Todas as parcelas deste empréstimo também serão excluídas.

Esta ação não poderá ser desfeita.

</div>

<form method="POST">

<input
type="hidden"
name="confirmar"
value="1">

<div class="d-flex justify-content-end gap-2">

<a
href="../index.php?pagina=emprestimos"
class="btn btn-secondary">

Cancelar

</a>

<a
href="../index.php?pagina=emprestimos"
class="btn btn-secondary">

Cancelar

</a>

<button
type="submit"
class="btn btn-danger">

Excluir Definitivamente

</button>

</div>

</form>

</div>

</div>

</div>

</div>

</div>

</body>

</html>

<?php

exit;

}

$conexao->begin_transaction();

try{

$stmt = $conexao->prepare("
DELETE
FROM parcelas
WHERE emprestimo_id = ?
");

$stmt->bind_param("s",$id);

$stmt->execute();

$stmt->close();

$stmt = $conexao->prepare("
DELETE
FROM emprestimos
WHERE id = ?
");

$stmt->bind_param("s",$id);

$stmt->execute();

$stmt->close();

$conexao->commit();

header("Location: ../index.php?pagina=emprestimos&sucesso=emprestimo_excluido");

exit;

}catch(Exception $e){

$conexao->rollback();

?>

<!DOCTYPE html>

<html lang="pt-br">

<head>

<meta charset="UTF-8">

<title>Erro</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{

background:#f8fafc;

}

.card{

margin-top:80px;

border:none;

border-radius:15px;

}

</style>

</head>

<body>

<div class="container">

<div class="row justify-content-center">

<div class="col-lg-7">

<div class="card shadow">

<div class="card-body p-5 text-center">

<h2 class="text-danger">

Erro ao excluir empréstimo

</h2>

<hr>

<p>

Ocorreu um erro durante a exclusão.

</p>

<div class="alert alert-danger">

<?= htmlspecialchars($e->getMessage()) ?>

</div>

<a
href="../index.php?pagina=emprestimos"
class="btn btn-primary">

Voltar para Empréstimos

</a>

</div>

</div>

</div>

</div>

</div>

</body>

</html>

<?php

exit;

}
