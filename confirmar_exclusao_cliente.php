<?php

include("config/conexao.php");

if (!isset($_GET["id"]) || !Database::isUuid(trim((string) $_GET["id"]))) {

    header("Location: index.php?pagina=clientes");
    exit;

}

$id = trim((string) $_GET["id"]);

$stmt = $conexao->prepare("
SELECT
id,
nome,
cpf,
telefone
FROM clientes
WHERE id = ?
LIMIT 1
");

$stmt->bind_param("s", $id);

$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows == 0) {

    die("Cliente não encontrado.");

}

$cliente = $resultado->fetch_assoc();

$stmt->close();

$stmt = $conexao->prepare("
SELECT COUNT(*) total
FROM emprestimos
WHERE cliente_id = ?
");

$stmt->bind_param("s", $id);

$stmt->execute();

$totalEmprestimos = $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();

$stmt = $conexao->prepare("
SELECT COUNT(*) total

FROM parcelas p

INNER JOIN emprestimos e
ON e.id = p.emprestimo_id

WHERE e.cliente_id = ?
");

$stmt->bind_param("s", $id);

$stmt->execute();

$totalParcelas = $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();

$stmt = $conexao->prepare("
SELECT COUNT(*) total

FROM parcelas p

INNER JOIN emprestimos e
ON e.id = p.emprestimo_id

WHERE e.cliente_id = ?

AND p.data_pagamento IS NOT NULL
");

$stmt->bind_param("s", $id);

$stmt->execute();

$parcelasPagas = $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();
?>

<!DOCTYPE html>

<html lang="pt-br">

<head>

<meta charset="UTF-8">

<title>Excluir Cliente</title>

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

</head>

<body class="bg-light">

<div class="container mt-5">

<div class="row justify-content-center">

<div class="col-lg-8">

<div class="card shadow">

<div class="card-header bg-danger text-white">

<h3 class="mb-0">

⚠ Confirmar Exclusão

</h3>

</div>

<div class="card-body">
    <h4>

Cliente:

<strong>

<?= htmlspecialchars($cliente["nome"]) ?>

</strong>

</h4>

<hr>

<div class="alert alert-danger">

<h5>

Esta ação irá excluir permanentemente:

</h5>

<ul class="mb-0">

<li>

Cliente

</li>

<li>

<?= $totalEmprestimos ?> empréstimo(s)

</li>

<li>

<?= $totalParcelas ?> parcela(s)

</li>

</ul>

</div>

<table class="table table-bordered">

<tr>

<th width="250">

CPF

</th>

<td>

<?= htmlspecialchars($cliente["cpf"]) ?>

</td>

</tr>

<tr>

<th>

Telefone

</th>

<td>

<?= htmlspecialchars($cliente["telefone"]) ?>

</td>

</tr>

<tr>

<th>

Empréstimos

</th>

<td>

<?= $totalEmprestimos ?>

</td>

</tr>

<tr>

<th>

Parcelas

</th>

<td>

<?= $totalParcelas ?>

</td>

</tr>

<tr>

<th>

Parcelas Pagas

</th>

<td>

<?= $parcelasPagas ?>

</td>

</tr>

</table>

<?php

if($parcelasPagas > 0){

?>

<div class="alert alert-warning">

<strong>

ATENÇÃO!

</strong>

<br><br>

Este cliente possui parcelas já pagas.

Ao excluir, todo o histórico financeiro será perdido.

Para confirmar, digite exatamente:

<br><br>

<h3 class="text-danger">

EXCLUIR

</h3>

</div>

<?php

}else{

?>

<div class="alert alert-info">

Este cliente não possui parcelas pagas.

Clique em <strong>Excluir Cliente</strong> para continuar.

</div>

<?php

}

?>

<form method="POST">
    <?php

if($parcelasPagas > 0){

?>

<div class="mb-3">

<label class="form-label">

Digite <strong>EXCLUIR</strong> para confirmar

</label>

<input
type="text"
name="confirmacao"
class="form-control"
autocomplete="off"
required>

</div>

<?php

}

?>

<div class="d-flex justify-content-end gap-2">

<a
href="index.php?pagina=clientes"
class="btn btn-secondary">

Cancelar

</a>

<button
type="submit"
class="btn btn-danger">

Excluir Cliente

</button>

</div>

</form>

<?php

if($_SERVER["REQUEST_METHOD"] == "POST"){

    if(
        $parcelasPagas > 0 &&
        (
            !isset($_POST["confirmacao"]) ||
            strtoupper(trim($_POST["confirmacao"])) != "EXCLUIR"
        )
    ){

        echo "<div class='alert alert-danger mt-4'>
                Você deve digitar <strong>EXCLUIR</strong> corretamente.
              </div>";

    }else{

        $conexao->begin_transaction();

        try{

            $stmt = $conexao->prepare("
            DELETE FROM parcelas
            WHERE emprestimo_id IN (
                SELECT id FROM emprestimos WHERE cliente_id = ?
            )
            ");

            $stmt->bind_param("s",$id);

            $stmt->execute();

            $stmt->close();

            $stmt = $conexao->prepare("
            DELETE
            FROM emprestimos
            WHERE cliente_id = ?
            ");

            $stmt->bind_param("s",$id);

            $stmt->execute();

            $stmt->close();

            $stmt = $conexao->prepare("
            DELETE
            FROM clientes
            WHERE id = ?
            ");

            $stmt->bind_param("s", $id);

            $stmt->execute();

            $stmt->close();

            $conexao->commit();

            header("Location: index.php?pagina=clientes");

            exit;

        }catch(Exception $e){

            $conexao->rollback();

            echo "

            <div class='alert alert-danger mt-4'>

                <strong>Erro ao excluir o cliente.</strong>

                <br><br>

                ".$e->getMessage()."

            </div>

            ";

        }

    }

}

?>

</div>

</div>

</div>

</div>

</div>

</body>

</html>
