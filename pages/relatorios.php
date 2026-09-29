<?php

// =======================================
// RELATÓRIOS
// =======================================

// ======================================
// FILTROS
// ======================================

$clienteFiltro = isset($_GET["cliente"])
    ? intval($_GET["cliente"])
    : 0;

$dataInicial = isset($_GET["inicio"])
    ? $_GET["inicio"]
    : "";

$dataFinal = isset($_GET["fim"])
    ? $_GET["fim"]
    : "";

$whereEmprestimos = " WHERE 1=1 ";

if($clienteFiltro > 0){

    $whereEmprestimos .= " AND cliente_id = $clienteFiltro";

}

if($dataInicial != ""){

    $whereEmprestimos .= " AND primeiro_vencimento >= '$dataInicial'";

}

if($dataFinal != ""){

    $whereEmprestimos .= " AND primeiro_vencimento <= '$dataFinal'";

}

// Total de clientes

if($clienteFiltro > 0){

    $sql = "
    SELECT COUNT(*) AS total
    FROM clientes
    WHERE id = $clienteFiltro
    ";

}else{

    $sql = "
    SELECT COUNT(*) AS total
    FROM clientes
    ";

}

$totalClientes = $conexao->query($sql)->fetch_assoc()["total"];

// Total de empréstimos

$sql = "
SELECT COUNT(*) AS total
FROM emprestimos
$whereEmprestimos
";

$totalEmprestimos = $conexao->query($sql)->fetch_assoc()["total"];

$sql = "
SELECT SUM(valor_emprestado) AS total
FROM emprestimos
$whereEmprestimos
";
$capital = $conexao->query($sql)->fetch_assoc()["total"];

if($capital == NULL){
    $capital = 0;
}

$sql = "
SELECT SUM(valor_total) AS total
FROM emprestimos
$whereEmprestimos
";
$totalContratos = $conexao->query($sql)->fetch_assoc()["total"];

if($totalContratos == NULL){
    $totalContratos = 0;
}

// Total recebido

$sql = "

SELECT

SUM(p.valor_pago) AS total

FROM parcelas p

INNER JOIN emprestimos e

ON p.emprestimo_id = e.id

WHERE p.data_pagamento IS NOT NULL

";

if($clienteFiltro > 0){

    $sql .= " AND e.cliente_id = $clienteFiltro";

}

if($dataInicial != ""){

    $sql .= " AND p.data_pagamento >= '$dataInicial'";

}

if($dataFinal != ""){

    $sql .= " AND p.data_pagamento <= '$dataFinal'";

}

$totalRecebido = $conexao->query($sql)->fetch_assoc()["total"];

if($totalRecebido == NULL){
    $totalRecebido = 0;
}

// Resumo das parcelas

$sql = "
SELECT

COUNT(*) AS total_parcelas,

SUM(
CASE
WHEN data_pagamento IS NOT NULL
THEN 1
ELSE 0
END
) AS parcelas_pagas,

SUM(
CASE
WHEN data_pagamento IS NULL
THEN 1
ELSE 0
END
) AS parcelas_pendentes,

SUM(
CASE
WHEN data_pagamento IS NULL
AND vencimento < CURDATE()
THEN 1
ELSE 0
END
) AS parcelas_atrasadas

FROM parcelas p

INNER JOIN emprestimos e

ON p.emprestimo_id = e.id

WHERE 1=1
";

if($clienteFiltro > 0){

    $sql .= " AND e.cliente_id = $clienteFiltro";

}

if($dataInicial != ""){

    $sql .= " AND p.vencimento >= '$dataInicial'";

}

if($dataFinal != ""){

    $sql .= " AND p.vencimento <= '$dataFinal'";

}

$resumoParcelas = $conexao->query($sql)->fetch_assoc();

$lucroPrevisto = $totalContratos - $capital;

// Clientes

$sqlClientes = "
SELECT
id,
nome
FROM clientes
ORDER BY nome
";

$clientes = $conexao->query($sqlClientes);

?>

<h2 class="mb-4">

    Relatórios

</h2>

<div class="row mb-4">

<div class="col-md-3">

<div class="card shadow-sm">

<div class="card-body text-center">

<h6>

👥 Clientes

</h6>

<h3>

<?= $totalClientes ?>

</h3>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card shadow-sm">

<div class="card-body text-center">

<h6>

📄 Empréstimos

</h6>

<h3>

<?= $totalEmprestimos ?>

</h3>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card shadow-sm">

<div class="card-body text-center">

<h6>

💰 Capital

</h6>

<h4>

R$

<?= number_format($capital,2,",",".") ?>

</h4>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card shadow-sm">

<div class="card-body text-center">

<h6>

💵 Recebido

</h6>

<h4 class="text-success">

R$

<?= number_format($totalRecebido,2,",",".") ?>

</h4>

</div>

</div>

</div>

</div>

<div class="card shadow-sm">

<div class="card-header">

<strong>

Filtros

</strong>

</div>

<div class="card-body">

<form method="GET" action="index.php">

<input
type="hidden"
name="pagina"
value="relatorios">

<div class="row">

<div class="col-md-3 mb-3">

<label class="form-label">

Tipo do Relatório

</label>

<select
name="tipo"
class="form-select">

<option value="geral"

<?php

if(isset($_GET["tipo"]) && $_GET["tipo"] == "geral"){
    echo "selected";
}

?>

>

Relatório Geral

</option>

<option value="clientes"

<?php

if(isset($_GET["tipo"]) && $_GET["tipo"] == "clientes"){
    echo "selected";
}

?>

>

Clientes

</option>

<option value="emprestimos"

<?php

if(isset($_GET["tipo"]) && $_GET["tipo"] == "emprestimos"){
    echo "selected";
}

?>

>

Empréstimos

</option>

<option value="parcelas"

<?php

if(isset($_GET["tipo"]) && $_GET["tipo"] == "parcelas"){
    echo "selected";
}

?>

>

Parcelas

</option>

</select>

</div>

<div class="col-md-3 mb-3">

<label class="form-label">

Cliente

</label>

<select
name="cliente"
class="form-select">

<option value="">

Todos os Clientes

</option>

<?php while($cliente = $clientes->fetch_assoc()){ ?>

<option

value="<?= $cliente["id"] ?>"

<?php

if(
isset($_GET["cliente"])
&&
$_GET["cliente"] == $cliente["id"]
){
    echo "selected";
}

?>

>

<?= htmlspecialchars($cliente["nome"]) ?>

</option>

<?php } ?>

</select>

</div>

<div class="col-md-3 mb-3">

<label class="form-label">

Data Inicial

</label>

<input

type="date"

name="inicio"

class="form-control"

value="<?= $_GET["inicio"] ?? "" ?>">

</div>

<div class="col-md-3 mb-3">

<label class="form-label">

Data Final

</label>

<input

type="date"

name="fim"

class="form-control"

value="<?= $_GET["fim"] ?? "" ?>">

</div>

</div>

<div class="text-end">

<button
class="btn btn-primary">

<i class="bi bi-search"></i>

Gerar Relatório

</button>

<button

type="button"

class="btn btn-secondary"

onclick="window.print()">

<i class="bi bi-printer"></i>

Imprimir

</button>

</div>

</form>

</div>

</div>

<div class="card shadow-sm mt-4">

<div class="card-header">

<strong>

Resultado

</strong>

</div>

<div class="card-body">

<?php

if(!isset($_GET["tipo"])){

?>

<div class="alert alert-info">

    Selecione os filtros acima e clique em

    <strong>Gerar Relatório</strong>.

</div>

<?php

}else{

$tipo = $_GET["tipo"];

if($tipo == "geral"){

?>

<h4 class="mb-3">

Resumo Financeiro

</h4>

<table class="table table-bordered">

<tr>

<th width="300">

Total de Clientes

</th>

<td>

<?= $totalClientes ?>

</td>

</tr>

<tr>

<th>

Total de Empréstimos

</th>

<td>

<?= $totalEmprestimos ?>

</td>

</tr>

<tr>

<th>

Capital Emprestado

</th>

<td>

R$

<?= number_format($capital,2,",",".") ?>

</td>

</tr>

<tr>

<th>

Valor dos Contratos

</th>

<td>

R$

<?= number_format($totalContratos,2,",",".") ?>

</td>

</tr>

<tr>

<th>

Total Recebido

</th>

<td class="text-success">

R$

<?= number_format($totalRecebido,2,",",".") ?>

</td>

</tr>

<tr>

<th>

Saldo a Receber

</th>

<td class="text-danger">

R$

<?= number_format(
$totalContratos - $totalRecebido,
2,
",",
"."
) ?>

</td>

</tr>

<tr>

<th>

Lucro Previsto

</th>

<td class="text-primary">

R$

<?= number_format($lucroPrevisto,2,",",".") ?>

</td>

</tr>

<tr>

<th>

Total de Parcelas

</th>

<td>

<?= $resumoParcelas["total_parcelas"] ?>

</td>

</tr>

<tr>

<th>

Parcelas Pagas

</th>

<td>

<?= $resumoParcelas["parcelas_pagas"] ?>

</td>

</tr>

<tr>

<th>

Parcelas Pendentes

</th>

<td>

<?= $resumoParcelas["parcelas_pendentes"] ?>

</td>

</tr>

<tr>

<th>

Parcelas Atrasadas

</th>

<td class="text-danger">

<?= $resumoParcelas["parcelas_atrasadas"] ?>

</td>

</tr>

</table>

<?php

}elseif($tipo == "clientes"){

$sql = "

SELECT

id,
nome,
telefone

FROM clientes

";

if (!empty($clienteFiltro)) {

    $sql .= " WHERE id = " . (int)$clienteFiltro;

}

$sql .= " ORDER BY nome";

$resultado = $conexao->query($sql);

?>

<h4 class="mb-3">

Relatório de Clientes

</h4>

<table class="table table-hover">

<thead>

<tr>

<th>ID</th>

<th>Nome</th>

<th>Telefone</th>

</tr>

</thead>

<tbody>

<?php while($cliente = $resultado->fetch_assoc()){ ?>

<tr>

<td>

<?= $cliente["id"] ?>

</td>

<td>

<?= htmlspecialchars($cliente["nome"]) ?>

</td>

<td>

<?= htmlspecialchars($cliente["telefone"]) ?>

</td>

</tr>

<?php } ?>

</tbody>

</table>

<?php

}elseif($tipo == "emprestimos"){

$sql = "

SELECT

e.id,
c.nome,
e.valor_emprestado,
e.juros,
e.quantidade_parcelas,
e.valor_total,
e.primeiro_vencimento

FROM emprestimos e

INNER JOIN clientes c

ON e.cliente_id = c.id

WHERE 1=1

";

if($clienteFiltro > 0){

    $sql .= " AND e.cliente_id = $clienteFiltro";

}

if($dataInicial != ""){

    $sql .= " AND e.primeiro_vencimento >= '$dataInicial'";

}

if($dataFinal != ""){

    $sql .= " AND e.primeiro_vencimento <= '$dataFinal'";

}

$sql .= " ORDER BY e.id DESC";

$resultado = $conexao->query($sql);

?>

<h4 class="mb-3">

Relatório de Empréstimos

</h4>

<table class="table table-hover table-bordered">

<thead>

<tr>

<th>ID</th>

<th>Cliente</th>

<th>Valor</th>

<th>Juros</th>

<th>Parcelas</th>

<th>Total</th>

<th>Primeiro Vencimento</th>

</tr>

</thead>

<tbody>

<?php while($linha = $resultado->fetch_assoc()){ ?>

<tr>

<td>

<?= $linha["id"] ?>

</td>

<td>

<?= htmlspecialchars($linha["nome"]) ?>

</td>

<td>

R$ <?= number_format($linha["valor_emprestado"],2,",",".") ?>

</td>

<td>

<?= number_format($linha["juros"],2,",",".") ?>%

</td>

<td>

<?= $linha["quantidade_parcelas"] ?>

</td>

<td>

R$ <?= number_format($linha["valor_total"],2,",",".") ?>

</td>

<td>

<?= date("d/m/Y",strtotime($linha["primeiro_vencimento"])) ?>

</td>

</tr>

<?php } ?>

</tbody>

</table>

<?php

}elseif($tipo == "parcelas"){

$sql = "

SELECT

p.numero_parcela,
p.valor_parcela,
p.vencimento,
p.data_pagamento,
p.status,
c.nome

FROM parcelas p

INNER JOIN emprestimos e

ON p.emprestimo_id = e.id

INNER JOIN clientes c

ON e.cliente_id = c.id

WHERE 1=1

";

if($clienteFiltro > 0){

    $sql .= " AND e.cliente_id = $clienteFiltro";

}

if($dataInicial != ""){

    $sql .= " AND p.vencimento >= '$dataInicial'";

}

if($dataFinal != ""){

    $sql .= " AND p.vencimento <= '$dataFinal'";

}

$sql .= " ORDER BY p.vencimento";

$resultado = $conexao->query($sql);

?>

<h4 class="mb-3">

Relatório de Parcelas

</h4>

<table class="table table-hover table-bordered">

<thead>

<tr>

<th>Cliente</th>

<th>Parcela</th>

<th>Valor</th>

<th>Vencimento</th>

<th>Pagamento</th>

<th>Status</th>

</tr>

</thead>

<tbody>

<?php while($linha = $resultado->fetch_assoc()){ ?>

<tr>

<td>

<?= htmlspecialchars($linha["nome"]) ?>

</td>

<td>

<?= $linha["numero_parcela"] ?>

</td>

<td>

R$ <?= number_format($linha["valor_parcela"],2,",",".") ?>

</td>

<td>

<?= date("d/m/Y",strtotime($linha["vencimento"])) ?>

</td>

<td>

<?php

if($linha["data_pagamento"]){

    echo date("d/m/Y",strtotime($linha["data_pagamento"]));

}else{

    echo "-";

}

?>

</td>

<td>

<?php

switch($linha["status"]){

    case "PAGA":

        echo "<span class='badge bg-success'>PAGA</span>";

    break;

    case "ATRASADA":

        echo "<span class='badge bg-danger'>ATRASADA</span>";

    break;

    case "VENCENDO":

        echo "<span class='badge bg-warning text-dark'>VENCENDO</span>";

    break;

    default:

        echo "<span class='badge bg-primary'>EM DIA</span>";

    break;

}

?>

</td>

</tr>

<?php } ?>

</tbody>

</table>

<?php

}else{

?>

<div class="alert alert-warning">

Tipo de relatório inválido.

</div>

<?php

}

}

?>

</div>

</div>

<style>

@media print{

    .sidebar,
    .topbar,
    .btn,
    form,
    nav,
    footer{

        display:none !important;

    }

    .card{

        border:none !important;

        box-shadow:none !important;

    }

    body{

        background:#fff;

    }

}

.table th{

    background:#f8f9fa;

}

.card{

    border:none;

}

.card-header{

    background:#f8f9fa;

    font-weight:600;

}

</style>