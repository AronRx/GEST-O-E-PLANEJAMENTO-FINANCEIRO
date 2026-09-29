<?php

$id = intval($_GET['id']);

$sql = "SELECT * FROM clientes WHERE id = $id";
$resultado = $conexao->query($sql);

if($resultado->num_rows == 0){
    echo "<div class='alert alert-danger'>Cliente não encontrado.</div>";
    return;
}

$cliente = $resultado->fetch_assoc();

?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <h2>Editar Cliente</h2>

    <a href="index.php?pagina=clientes" class="btn btn-secondary">
        Voltar
    </a>

</div>

<form action="atualizar_cliente.php" method="POST">

<input type="hidden" name="id" value="<?= $cliente['id'] ?>">

<div class="card">

<div class="card-body">

<div class="row">

<div class="col-md-6 mb-3">
<label>Nome</label>
<input type="text" name="nome" class="form-control" value="<?= $cliente['nome'] ?>" required>
</div>

<div class="col-md-3 mb-3">
<label>CPF</label>
<input type="text" name="cpf" class="form-control" value="<?= $cliente['cpf'] ?>">
</div>

<div class="col-md-3 mb-3">
<label>RG</label>
<input type="text" name="rg" class="form-control" value="<?= $cliente['rg'] ?>">
</div>

<div class="col-md-4 mb-3">
<label>Telefone</label>
<input type="text" name="telefone" class="form-control" value="<?= $cliente['telefone'] ?>">
</div>

<div class="col-md-4 mb-3">
<label>WhatsApp</label>
<input type="text" name="whatsapp" class="form-control" value="<?= $cliente['whatsapp'] ?>">
</div>

<div class="col-md-4 mb-3">
<label>E-mail</label>
<input type="email" name="email" class="form-control" value="<?= $cliente['email'] ?>">
</div>

<div class="col-md-3 mb-3">
<label>CEP</label>
<input type="text" name="cep" class="form-control" value="<?= $cliente['cep'] ?>">
</div>

<div class="col-md-5 mb-3">
<label>Rua</label>
<input type="text" name="rua" class="form-control" value="<?= $cliente['rua'] ?>">
</div>

<div class="col-md-2 mb-3">
<label>Número</label>
<input type="text" name="numero" class="form-control" value="<?= $cliente['numero'] ?>">
</div>

<div class="col-md-2 mb-3">
<label>Estado</label>
<input type="text" name="estado" maxlength="2" class="form-control" value="<?= $cliente['estado'] ?>">
</div>

<div class="col-md-6 mb-3">
<label>Bairro</label>
<input type="text" name="bairro" class="form-control" value="<?= $cliente['bairro'] ?>">
</div>

<div class="col-md-6 mb-3">
<label>Cidade</label>
<input type="text" name="cidade" class="form-control" value="<?= $cliente['cidade'] ?>">
</div>

<div class="col-12 mb-3">
<label>Observações</label>
<textarea name="observacoes" rows="4" class="form-control"><?= $cliente['observacoes'] ?></textarea>
</div>

</div>

</div>

<div class="card-footer text-end">

<button class="btn btn-success">
Salvar Alterações
</button>

</div>

</div>

</form>