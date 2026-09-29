<?php

// =====================================================
// PESQUISA
// =====================================================

$pesquisa = "";

if(isset($_GET["pesquisa"])){
    $pesquisa = trim($_GET["pesquisa"]);
}

// =====================================================
// CARDS SUPERIORES
// =====================================================

$sql = "SELECT COUNT(*) AS total FROM emprestimos";
$totalEmprestimos = $conexao->query($sql)->fetch_assoc()["total"];

$sql = "
SELECT COUNT(*) AS total
FROM emprestimos e
WHERE EXISTS(
    SELECT 1
    FROM parcelas p
    WHERE p.emprestimo_id = e.id
    AND p.data_pagamento IS NULL
)
";
$ativos = $conexao->query($sql)->fetch_assoc()["total"];

$sql = "
SELECT COUNT(*) AS total
FROM emprestimos e
WHERE NOT EXISTS(
    SELECT 1
    FROM parcelas p
    WHERE p.emprestimo_id = e.id
    AND p.data_pagamento IS NULL
)
";
$quitados = $conexao->query($sql)->fetch_assoc()["total"];

$sql = "
SELECT
SUM(valor_total) AS total
FROM emprestimos
";

$valorCarteira = $conexao->query($sql)->fetch_assoc()["total"];

if($valorCarteira == NULL){
    $valorCarteira = 0;
}

// =====================================================
// LISTAGEM
// =====================================================

$sql = "
SELECT

e.*,

c.nome,

SUM(
CASE
WHEN p.data_pagamento IS NOT NULL
THEN p.valor_pago
ELSE 0
END
) AS recebido,

SUM(
CASE
WHEN p.data_pagamento IS NOT NULL
THEN 1
ELSE 0
END
) AS parcelas_pagas

FROM emprestimos e

INNER JOIN clientes c
ON c.id = e.cliente_id

LEFT JOIN parcelas p
ON p.emprestimo_id = e.id

";

if($pesquisa != ""){

    $sql .= "
    WHERE c.nome LIKE ?
    ";

}

$sql .= "

GROUP BY e.id

ORDER BY e.id DESC

";

$stmt = $conexao->prepare($sql);

if($pesquisa != ""){

    $like = "%".$pesquisa."%";

    $stmt->bind_param("s",$like);

}

$stmt->execute();

$resultado = $stmt->get_result();

?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <h2>

        Empréstimos

    </h2>

    <a
    href="index.php?pagina=novo_emprestimo"
    class="btn btn-primary">

        <i class="bi bi-plus-circle"></i>

        Novo Empréstimo

    </a>

</div>

<div class="row mb-4">

<div class="col-md-3">

<div class="card shadow-sm">

<div class="card-body text-center">

<small class="text-muted">

Total de Empréstimos

</small>

<h3>

<?= $totalEmprestimos ?>

</h3>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card shadow-sm">

<div class="card-body text-center">

<small class="text-muted">

Ativos

</small>

<h3 class="text-primary">

<?= $ativos ?>

</h3>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card shadow-sm">

<div class="card-body text-center">

<small class="text-muted">

Quitados

</small>

<h3 class="text-success">

<?= $quitados ?>

</h3>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card shadow-sm">

<div class="card-body text-center">

<small class="text-muted">

Carteira

</small>

<h5 class="mt-2">

R$ <?= number_format($valorCarteira,2,",",".") ?>

</h5>

</div>

</div>

</div>

</div>

<div class="card shadow-sm">

<div class="card-body">

<form method="GET">

<input
type="hidden"
name="pagina"
value="emprestimos">

<div class="row">

<div class="col-md-10">

<input
type="text"
name="pesquisa"
class="form-control"
placeholder="Pesquisar cliente..."
value="<?= htmlspecialchars($pesquisa) ?>">

</div>

<div class="col-md-2 d-grid">

<button class="btn btn-primary">

<i class="bi bi-search"></i>

Pesquisar

</button>

</div>

</div>

</form>

</div>

</div>

<div class="card shadow-sm mt-4">

<div class="card-body table-responsive">

<table class="table table-hover align-middle">

<thead class="table-dark">

<tr>

<th>Cliente</th>

<th>Capital</th>

<th>Total</th>

<th>Recebido</th>

<th>Saldo</th>

<th>Parcelas</th>

<th width="180">

Progresso

</th>

<th>Status</th>

<th width="190">

Ações

</th>

</tr>

</thead>

<tbody>

<?php

while($emp = $resultado->fetch_assoc()){

    $recebido = $emp["recebido"] ?? 0;

    $saldo = $emp["valor_total"] - $recebido;

    $pagas = $emp["parcelas_pagas"] ?? 0;

    $total = $emp["quantidade_parcelas"];

    $percentual = 0;

    if($total > 0){

        $percentual =
        ($pagas/$total)*100;

    }

    if($pagas >= $total){

        $status = "Quitado";
        $badge = "success";

    }else{

        $status = "Ativo";
        $badge = "primary";

    }

?>

    <tr>

        <td>

            <strong>

                <?= htmlspecialchars($emp["nome"]) ?>

            </strong>

        </td>

        <td>

            R$ <?= number_format($emp["valor_emprestado"],2,",",".") ?>

        </td>

        <td>

            <strong class="text-success">

                R$ <?= number_format($emp["valor_total"],2,",",".") ?>

            </strong>

        </td>

        <td>

            <span class="text-primary">

                R$ <?= number_format($recebido,2,",",".") ?>

            </span>

        </td>

        <td>

            <span class="text-danger">

                R$ <?= number_format($saldo,2,",",".") ?>

            </span>

        </td>

        <td>

            <?= $pagas ?>/<?= $total ?>

        </td>

        <td>

            <div class="progress" style="height:22px;">

                <div

                    class="progress-bar bg-success"

                    role="progressbar"

                    style="width: <?= $percentual ?>%;"

                    aria-valuenow="<?= $percentual ?>"

                    aria-valuemin="0"

                    aria-valuemax="100">

                    <?= round($percentual) ?>%

                </div>

            </div>

        </td>

        <td>

            <span class="badge bg-<?= $badge ?>">

                <?= $status ?>

            </span>

        </td>

        <td>

            <div class="btn-group" role="group">

                <a

                href="index.php?pagina=visualizar_emprestimo&id=<?= $emp["id"] ?>"

                class="btn btn-primary btn-sm"

                title="Visualizar">

                    <i class="bi bi-eye"></i>

                </a>

                <a

                href="index.php?pagina=editar_emprestimo&id=<?= $emp["id"] ?>"

                class="btn btn-warning btn-sm"

                title="Editar">

                    <i class="bi bi-pencil"></i>

                </a>

<?php

// Procura a primeira parcela em aberto

$sqlParcela = "

SELECT id

FROM parcelas

WHERE emprestimo_id = ?

AND data_pagamento IS NULL

ORDER BY numero_parcela

LIMIT 1

";

$stmtParcela = $conexao->prepare($sqlParcela);

$stmtParcela->bind_param("i",$emp["id"]);

$stmtParcela->execute();

$parcelaAberta = $stmtParcela->get_result()->fetch_assoc();

if($parcelaAberta){

?>

                <a

                href="index.php?pagina=receber_parcela&id=<?= $parcelaAberta["id"] ?>"

                class="btn btn-success btn-sm"

                title="Receber">

                    <i class="bi bi-cash-stack"></i>

                </a>

<?php

}

?>

                <a

                href="actions/excluir_emprestimo.php?id=<?= $emp["id"] ?>"

                class="btn btn-danger btn-sm"

                onclick="return confirm('Deseja realmente excluir este empréstimo?')"

                title="Excluir">

                <i class="bi bi-trash"></i>

                </a>

            </div>

        </td>

    </tr>

<?php

}

?>

<?php

// Caso não existam empréstimos

if($resultado->num_rows == 0){

?>

<tr>

    <td colspan="9" class="text-center py-5">

        <i class="bi bi-search fs-1 text-secondary"></i>

        <br><br>

        <strong>

            Nenhum empréstimo encontrado.

        </strong>

    </td>

</tr>

<?php

}

?>

</tbody>

</table>

</div>

</div>

<?php

// =====================================================
// RESUMO DA CARTEIRA
// =====================================================

$sqlResumo = "

SELECT

SUM(valor_emprestado) AS capital,

SUM(valor_total) AS contratos

FROM emprestimos

";

$resumo = $conexao->query($sqlResumo)->fetch_assoc();

$capital = $resumo["capital"] ?? 0;

$contratos = $resumo["contratos"] ?? 0;

$sql = "

SELECT

SUM(valor_pago) AS recebido

FROM parcelas

WHERE data_pagamento IS NOT NULL

";

$recebido = $conexao->query($sql)->fetch_assoc()["recebido"] ?? 0;

$saldo = $contratos - $recebido;

$lucro = $contratos - $capital;

?>

<div class="row mt-4">

<div class="col-md-3">

<div class="card shadow-sm border-0">

<div class="card-body text-center">

<small class="text-muted">

Capital Investido

</small>

<h5 class="mt-2">

R$

<?= number_format($capital,2,",",".") ?>

</h5>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card shadow-sm border-0">

<div class="card-body text-center">

<small class="text-muted">

Recebido

</small>

<h5 class="mt-2 text-success">

R$

<?= number_format($recebido,2,",",".") ?>

</h5>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card shadow-sm border-0">

<div class="card-body text-center">

<small class="text-muted">

Saldo a Receber

</small>

<h5 class="mt-2 text-danger">

R$

<?= number_format($saldo,2,",",".") ?>

</h5>

</div>

</div>

</div>

<div class="col-md-3">

<div class="card shadow-sm border-0">

<div class="card-body text-center">

<small class="text-muted">

Lucro Previsto

</small>

<h5 class="mt-2 text-primary">

R$

<?= number_format($lucro,2,",",".") ?>

</h5>

</div>

</div>

</div>

</div>

<?php

// =====================================================
// FIM DA PÁGINA
// =====================================================

if($totalEmprestimos > 0){

?>

<div class="card shadow-sm mt-4">

    <div class="card-header bg-light">

        <strong>

            <i class="bi bi-info-circle"></i>

            Resumo da Carteira

        </strong>

    </div>

    <div class="card-body">

        <div class="row text-center">

            <div class="col-md-3">

                <h6 class="text-muted">

                    Média por Empréstimo

                </h6>

                <h5>

                    R$

                    <?= number_format($contratos / $totalEmprestimos,2,",",".") ?>

                </h5>

            </div>

            <div class="col-md-3">

                <h6 class="text-muted">

                    Percentual Recebido

                </h6>

                <h5 class="text-success">

                    <?php

                    if($contratos > 0){

                        echo number_format(($recebido/$contratos)*100,1,",",".");

                    }else{

                        echo "0";

                    }

                    ?>%

                </h5>

            </div>

            <div class="col-md-3">

                <h6 class="text-muted">

                    Percentual em Aberto

                </h6>

                <h5 class="text-danger">

                    <?php

                    if($contratos > 0){

                        echo number_format(($saldo/$contratos)*100,1,",",".");

                    }else{

                        echo "0";

                    }

                    ?>%

                </h5>

            </div>

            <div class="col-md-3">

                <h6 class="text-muted">

                    Juros Totais

                </h6>

                <h5 class="text-primary">

                    R$

                    <?= number_format($lucro,2,",",".") ?>

                </h5>

            </div>

        </div>

    </div>

</div>

<?php

}

?>

<style>

.progress{

    height:22px;

}

.progress-bar{

    font-size:12px;

    font-weight:600;

}

.table td{

    vertical-align:middle;

}

.card{

    border:none;

}

.btn-group .btn{

    min-width:38px;

}

</style>