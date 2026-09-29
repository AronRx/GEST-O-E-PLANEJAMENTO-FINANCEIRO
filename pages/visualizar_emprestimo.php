<?php

$id = isset($_GET["id"]) ? intval($_GET["id"]) : 0;

$sql = "
SELECT
    e.*,
    c.nome
FROM emprestimos e
INNER JOIN clientes c
ON c.id = e.cliente_id
WHERE e.id = ?
";

$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();

$resultado = $stmt->get_result();

if($resultado->num_rows == 0){

    echo "<div class='alert alert-danger'>Empréstimo não encontrado.</div>";
    return;

}

$emprestimo = $resultado->fetch_assoc();


//====================================
// RESUMO FINANCEIRO
//====================================

$sqlResumo = "
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
WHEN data_pagamento IS NOT NULL
THEN valor_pago
ELSE 0
END
) AS valor_recebido,

SUM(
CASE
WHEN data_pagamento IS NULL
AND vencimento < CURDATE()
THEN 1
ELSE 0
END
) AS parcelas_atrasadas

FROM parcelas

WHERE emprestimo_id = ?
";

$stmtResumo = $conexao->prepare($sqlResumo);
$stmtResumo->bind_param("i",$id);
$stmtResumo->execute();

$resumo = $stmtResumo->get_result()->fetch_assoc();

$totalParcelas      = $resumo["total_parcelas"] ?? 0;
$parcelasPagas      = $resumo["parcelas_pagas"] ?? 0;
$valorRecebido      = $resumo["valor_recebido"] ?? 0;
$parcelasAtrasadas  = $resumo["parcelas_atrasadas"] ?? 0;

$parcelasPendentes = $totalParcelas - $parcelasPagas;

$saldoDevedor = $emprestimo["valor_total"] - $valorRecebido;

$lucroPrevisto = $emprestimo["valor_total"] - $emprestimo["valor_emprestado"];

$percentual = 0;

if($totalParcelas > 0){
    $percentual = ($parcelasPagas / $totalParcelas) * 100;
}


//====================================
// STATUS DO CONTRATO
//====================================

$status = "Ativo";
$badge = "primary";

if($parcelasPagas == $totalParcelas){

    $status = "Quitado";
    $badge = "success";

}elseif($parcelasAtrasadas > 0){

    $status = "Em atraso";
    $badge = "danger";

}elseif($parcelasPendentes == 1){

    $status = "Finalizando";
    $badge = "warning";

}


//====================================
// PARCELAS
//====================================

$sqlParcelas = "
SELECT *
FROM parcelas
WHERE emprestimo_id = ?
ORDER BY numero_parcela
";

$stmtParcelas = $conexao->prepare($sqlParcelas);
$stmtParcelas->bind_param("i",$id);
$stmtParcelas->execute();

$parcelas = $stmtParcelas->get_result();

?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <h2>
        Empréstimo #<?= $emprestimo["id"] ?>
    </h2>

    <div>

        <a
        href="index.php?pagina=emprestimos"
        class="btn btn-secondary">

            Voltar

        </a>

    </div>

</div>



<div class="card mb-4">

<div class="card-body">

<div class="row">

<div class="col-md-6">

<p>

<strong>Cliente:</strong>

<?= htmlspecialchars($emprestimo["nome"]) ?>

</p>

<p>

<strong>Capital Emprestado:</strong>

R$ <?= number_format($emprestimo["valor_emprestado"],2,",",".") ?>

</p>

<p>

<strong>Juros:</strong>

<?= number_format($emprestimo["juros"],2,",",".") ?>%

</p>

<p>

<strong>Valor Total:</strong>

R$ <?= number_format($emprestimo["valor_total"],2,",",".") ?>

</p>

</div>


<div class="col-md-6">

<p>

<strong>Valor da Parcela:</strong>

R$ <?= number_format($emprestimo["valor_parcela"],2,",",".") ?>

</p>

<p>

<strong>Quantidade:</strong>

<?= $emprestimo["quantidade_parcelas"] ?>

</p>

<p>

<strong>Primeiro Vencimento:</strong>

<?= date("d/m/Y",strtotime($emprestimo["primeiro_vencimento"])) ?>

</p>

<p>

<strong>Status:</strong>

<span class="badge bg-<?= $badge ?>">

<?= $status ?>

</span>

</p>

</div>

</div>

</div>

</div>



<div class="row mb-4">

<div class="col-md-3">

<div class="card">

<div class="card-body text-center">

<h6>💵 Recebido</h6>

<h4>

R$ <?= number_format($valorRecebido,2,",",".") ?>

</h4>

</div>

</div>

</div>



<div class="col-md-3">

<div class="card">

<div class="card-body text-center">

<h6>🏦 Saldo</h6>

<h4>

R$ <?= number_format($saldoDevedor,2,",",".") ?>

</h4>

</div>

</div>

</div>



<div class="col-md-3">

<div class="card">

<div class="card-body text-center">

<h6>💹 Lucro</h6>

<h4>

R$ <?= number_format($lucroPrevisto,2,",",".") ?>

</h4>

</div>

</div>

</div>



<div class="col-md-3">

<div class="card">

<div class="card-body text-center">

<h6>📄 Parcelas</h6>

<h4>

<?= $parcelasPagas ?>/<?= $totalParcelas ?>

</h4>

</div>

</div>

</div>

</div>



<div class="card mb-4">

<div class="card-body">

<h6 class="mb-3">

Progresso do Contrato

</h6>

<div class="progress">

<div
class="progress-bar bg-success"
style="width: <?= $percentual ?>%">

<?= round($percentual) ?>%

</div>

</div>

</div>

</div>



<div class="card">

<div class="card-header">

<strong>Parcelas</strong>

</div>

<div class="card-body table-responsive">

<table class="table table-hover align-middle">

<thead>

<tr>

<th>Nº</th>
<th>Vencimento</th>
<th>Valor</th>
<th>Pagamento</th>
<th>Status</th>
<th width="120">Ações</th>

</tr>

</thead>

<tbody>

<?php

$dataHoje = date("Y-m-d");

while($parcela = $parcelas->fetch_assoc()){

    if($parcela["data_pagamento"] != NULL){

        $statusParcela = "Paga";
        $cor = "success";

    }else{

        if($parcela["vencimento"] < $dataHoje){

            $statusParcela = "Atrasada";
            $cor = "danger";

        }else{

            $dias = (strtotime($parcela["vencimento"]) - strtotime($dataHoje))/86400;

            if($dias <= 5){

                $statusParcela = "Vence em breve";
                $cor = "warning";

            }else{

                $statusParcela = "Em dia";
                $cor = "secondary";

            }

        }

    }

?>

<tr>

<td>

<?= $parcela["numero_parcela"] ?>

</td>

<td>

<?= date("d/m/Y",strtotime($parcela["vencimento"])) ?>

</td>

<td>

R$ <?= number_format($parcela["valor_parcela"],2,",",".") ?>

</td>

<td>

<?php

if($parcela["data_pagamento"]){

    echo date("d/m/Y",strtotime($parcela["data_pagamento"]));

}else{

    echo "-";

}

?>

</td>

<td>

<span class="badge bg-<?= $cor ?>">

<?= $statusParcela ?>

</span>

</td>

<td>

<?php if($parcela["data_pagamento"] == NULL){ ?>

<a

href="index.php?pagina=receber_parcela&id=<?= $parcela["id"] ?>"

class="btn btn-success btn-sm">

<i class="bi bi-cash"></i>

</a>

<?php }else{ ?>

<button
class="btn btn-secondary btn-sm"
disabled>

<i class="bi bi-check-lg"></i>

</button>

<?php } ?>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>

<?php if(!empty($emprestimo["observacoes"])){ ?>

<div class="card mt-4">

<div class="card-header">

<strong>Observações</strong>

</div>

<div class="card-body">

<?= nl2br(htmlspecialchars($emprestimo["observacoes"])) ?>

</div>

</div>

<?php } ?>