<?php

$sql = "SELECT id, nome FROM clientes ORDER BY nome";
$resultado = $conexao->query($sql);

?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <h2>Novo Empréstimo</h2>

    <a href="index.php?pagina=dashboard" class="btn btn-secondary">
        Voltar
    </a>

</div>

<form action="salvar_emprestimo.php" method="POST">

<div class="card shadow-sm">

<div class="card-body">

<div class="row">

<div class="col-md-6 mb-3">

<label class="form-label">Cliente</label>

<select name="cliente_id" class="form-select" required>

<option value="">Selecione um cliente</option>

<?php while($cliente = $resultado->fetch_assoc()){ ?>

<option value="<?= $cliente['id'] ?>">

<?= htmlspecialchars($cliente['nome']) ?>

</option>

<?php } ?>

</select>

</div>

<div class="col-md-3 mb-3">

<label class="form-label">Valor Emprestado</label>

<input
type="number"
step="0.01"
name="valor"
class="form-control"
required>

</div>

<div class="col-md-3 mb-3">

<label class="form-label">Juros (%)</label>

<input
type="number"
step="0.01"
name="juros"
class="form-control"
value="0"
required>

</div>

<div class="col-md-3 mb-3">

<label class="form-label">Quantidade de Parcelas</label>

<input
type="number"
name="parcelas"
class="form-control"
required>

</div>

<div class="col-md-3 mb-3">

<label class="form-label">Primeiro Vencimento</label>

<input
type="date"
name="primeiro_vencimento"
class="form-control"
required>

</div>

<div class="col-md-6 mb-3">

<label class="form-label">Observações</label>

<textarea
name="observacoes"
class="form-control"
rows="3"></textarea>

</div>

</div>

</div>

<div class="card-footer text-end">

<button class="btn btn-success">

Salvar Empréstimo

</button>

</div>

</div>

</form>