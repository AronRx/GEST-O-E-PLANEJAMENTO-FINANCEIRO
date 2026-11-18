<?php

$id = trim((string) ($_GET["id"] ?? ""));

if (!Database::isUuid($id)) {
    echo "<div class='alert alert-danger'>Empréstimo não encontrado.</div>";
    return;
}

$sql = "
SELECT *
FROM emprestimos
WHERE id = ?
";

$stmt = $conexao->prepare($sql);
$stmt->bind_param("s", $id);
$stmt->execute();

$resultado = $stmt->get_result();

if($resultado->num_rows == 0){

    echo "<div class='alert alert-danger'>
            Empréstimo não encontrado.
          </div>";

    return;
}

$emprestimo = $resultado->fetch_assoc();

$sqlClientes = "
SELECT id, nome
FROM clientes
ORDER BY nome
";

$clientes = $conexao->query($sqlClientes);

$sql = "
SELECT COUNT(*) AS total
FROM parcelas
WHERE emprestimo_id = ?
AND data_pagamento IS NOT NULL
";

$stmt = $conexao->prepare($sql);
$stmt->bind_param("s",$id);
$stmt->execute();

$parcelasPagas = $stmt->get_result()->fetch_assoc()["total"];

$bloquear = ($parcelasPagas > 0);

?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <h2>Editar Empréstimo</h2>

    <a href="index.php?pagina=visualizar_emprestimo&id=<?= $id ?>"
       class="btn btn-secondary">

        Voltar

    </a>

</div>

<?php if($bloquear){ ?>

<div class="alert alert-warning">

    <strong>Atenção!</strong><br>

    Este empréstimo possui parcelas já pagas.

    Os campos financeiros foram bloqueados para preservar o histórico.

</div>

<?php } ?>

<form action="actions/salvar_edicao_emprestimo.php" method="POST">

<input type="hidden" name="id" value="<?= $emprestimo["id"] ?>">

<div class="card shadow-sm">

<div class="card-body">

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label">

Cliente

</label>

<select
name="cliente_id"
class="form-select"
<?= $bloquear ? "disabled" : "" ?>>

<?php while($cliente = $clientes->fetch_assoc()){ ?>

<option
value="<?= $cliente["id"] ?>"
<?= $cliente["id"] == $emprestimo["cliente_id"] ? "selected" : "" ?>>

<?= htmlspecialchars($cliente["nome"]) ?>

</option>

<?php } ?>

</select>

<?php if($bloquear){ ?>

<input
type="hidden"
name="cliente_id"
value="<?= $emprestimo["cliente_id"] ?>">

<?php } ?>

</div>

<div class="col-md-3 mb-3">

<label class="form-label">

Valor Emprestado

</label>

<input
type="number"
step="0.01"
name="valor_emprestado"
class="form-control"
value="<?= $emprestimo["valor_emprestado"] ?>"
<?= $bloquear ? "readonly" : "" ?>>

</div>

<div class="col-md-3 mb-3">

<label class="form-label">

Juros (%)

</label>

<input
type="number"
step="0.01"
name="juros"
class="form-control"
value="<?= $emprestimo["juros"] ?>"
<?= $bloquear ? "readonly" : "" ?>>

</div>

<div class="col-md-4 mb-3">

<label class="form-label">

Quantidade de Parcelas

</label>

<input
type="number"
name="quantidade_parcelas"
class="form-control"
value="<?= $emprestimo["quantidade_parcelas"] ?>"
<?= $bloquear ? "readonly" : "" ?>>

</div>

<div class="col-md-4 mb-3">

<label class="form-label">

Primeiro Vencimento

</label>

<input
type="date"
name="primeiro_vencimento"
class="form-control"
value="<?= $emprestimo["primeiro_vencimento"] ?>"
<?= $bloquear ? "readonly" : "" ?>>

</div>

<div class="col-md-4 mb-3">

<label class="form-label">

Valor Total

</label>

<input
type="text"
class="form-control"
value="R$ <?= number_format($emprestimo["valor_total"],2,",",".") ?>"
readonly>

</div>

<div class="col-12 mb-3">

<label class="form-label">

Observações

</label>

<textarea
name="observacoes"
rows="5"
class="form-control"><?= htmlspecialchars($emprestimo["observacoes"]) ?></textarea>

</div>

</div>

</div>

<div class="card-footer text-end">

<button
class="btn btn-success">

<i class="bi bi-check-circle"></i>

Salvar Alterações

</button>

</div>

</div>

</form>
