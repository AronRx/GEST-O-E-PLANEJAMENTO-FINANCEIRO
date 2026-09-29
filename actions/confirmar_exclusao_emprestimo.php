<?php

session_start();

require_once("../config/conexao.php");

// ======================================
// VERIFICA O ID
// ======================================

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {

    header("Location: ../index.php?pagina=emprestimos");
    exit;

}

$id = (int) $_GET["id"];

// ======================================
// BUSCA O EMPRÉSTIMO
// ======================================

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

$stmt->bind_param("i",$id);

$stmt->execute();

$resultado = $stmt->get_result();

if($resultado->num_rows == 0){

    die("Empréstimo não encontrado.");

}

$emprestimo = $resultado->fetch_assoc();

$stmt->close();

// ======================================
// AINDA NÃO CONFIRMOU
// ======================================

if($_SERVER["REQUEST_METHOD"] != "POST"){

?>

<!DOCTYPE html>

<html lang="pt-br">

<head>

<meta charset="UTF-8">

<title>Exclusão Permanente</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<style>

body{

background:#f8fafc;

}

.card{

margin-top:60px;

border:none;

border-radius:15px;

}

.codigo{

font-size:30px;

font-weight:bold;

letter-spacing:6px;

color:#dc3545;

}

</style>

</head>

<body>

<div class="container">

<div class="row justify-content-center">

<div class="col-lg-8">

<div class="card shadow">

<div class="card-body p-5">

<h2 class="text-danger">

⚠ Exclusão Permanente

</h2>

<hr>

<div class="alert alert-danger">

<strong>ATENÇÃO!</strong>

Este empréstimo possui parcelas já pagas.

Ao continuar você removerá permanentemente:

<ul class="mt-3">

<li>Empréstimo</li>

<li>Parcelas pagas</li>

<li>Parcelas pendentes</li>

<li>Histórico de pagamentos</li>

</ul>

Esta ação é irreversível.

</div>

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

<hr>

<p>

Para confirmar, digite exatamente:

</p>

<div class="codigo">

EXCLUIR

</div>

<br>

<form method="POST">

<input
type="text"
name="confirmacao"
class="form-control"
autocomplete="off"
required>

<input
type="hidden"
name="id"
value="<?= $id ?>">

<br>

<div class="d-flex justify-content-end gap-2">

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

// ======================================
// VALIDA A CONFIRMAÇÃO
// ======================================

if(
!isset($_POST["confirmacao"]) ||
strtoupper(trim($_POST["confirmacao"])) != "EXCLUIR"
){

?>

<!DOCTYPE html>

<html lang="pt-br">

<head>

<meta charset="UTF-8">

<title>Confirmação Inválida</title>

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

<div class="col-lg-6">

<div class="card shadow">

<div class="card-body p-5 text-center">

<h2 class="text-danger">

Confirmação inválida

</h2>

<hr>

<p>

Você precisa digitar exatamente:

</p>

<h3 class="text-danger">

EXCLUIR

</h3>

<br>

<a
href="confirmar_exclusao_emprestimo.php?id=<?= $id ?>"
class="btn btn-primary">

Tentar Novamente

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

// ======================================
// EXECUTA A EXCLUSÃO
// ======================================

$conexao->begin_transaction();

try{

$stmt = $conexao->prepare("
DELETE
FROM parcelas
WHERE emprestimo_id = ?
");

$stmt->bind_param("i",$id);

$stmt->execute();

$stmt->close();

$stmt = $conexao->prepare("
DELETE
FROM emprestimos
WHERE id = ?
");

$stmt->bind_param("i",$id);

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