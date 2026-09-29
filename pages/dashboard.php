<?php

// ===============================
// INDICADORES
// ===============================

// Total de clientes
$sql = "SELECT COUNT(*) AS total FROM clientes";
$totalClientes = $conexao->query($sql)->fetch_assoc()['total'];

// Total de empréstimos
$sql = "SELECT COUNT(*) AS total FROM emprestimos";
$totalEmprestimos = $conexao->query($sql)->fetch_assoc()['total'];

// Capital emprestado
$sql = "SELECT SUM(valor_emprestado) AS total FROM emprestimos";
$totalEmprestado = $conexao->query($sql)->fetch_assoc()['total'];
$totalEmprestado = $totalEmprestado ?? 0;

// Valor total dos contratos
$sql = "SELECT SUM(valor_total) AS total FROM emprestimos";
$totalContratos = $conexao->query($sql)->fetch_assoc()['total'];
$totalContratos = $totalContratos ?? 0;

// Valor recebido
$sql = "SELECT SUM(valor_pago) AS total
        FROM parcelas
        WHERE data_pagamento IS NOT NULL";

$totalRecebido = $conexao->query($sql)->fetch_assoc()['total'];
$totalRecebido = $totalRecebido ?? 0;

// Saldo
$saldoReceber = $totalContratos - $totalRecebido;

// Lucro previsto
$lucroPrevisto = $totalContratos - $totalEmprestado;

// Parcelas em atraso
$sql = "SELECT COUNT(*) AS total
        FROM parcelas
        WHERE data_pagamento IS NULL
        AND vencimento < CURDATE()";

$parcelasAtraso = $conexao->query($sql)->fetch_assoc()['total'];

// Parcelas vencendo hoje
$sql = "SELECT COUNT(*) AS total
        FROM parcelas
        WHERE data_pagamento IS NULL
        AND vencimento = CURDATE()";

$vencemHoje = $conexao->query($sql)->fetch_assoc()['total'];

?>

<h2 class="mb-4">Dashboard</h2>

<div class="row">

    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h6>👥 Clientes</h6>
                <h2><?= $totalClientes ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h6>📄 Empréstimos</h6>
                <h2><?= $totalEmprestimos ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h6>💰 Capital Emprestado</h6>
                <h2>R$ <?= number_format($totalEmprestado,2,",",".") ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h6>📈 Valor dos Contratos</h6>
                <h2>R$ <?= number_format($totalContratos,2,",",".") ?></h2>
            </div>
        </div>
    </div>

</div>

<div class="row mt-4">

    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h6>🏦 Saldo a Receber</h6>
                <h2>R$ <?= number_format($saldoReceber,2,",",".") ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h6>💹 Lucro Previsto</h6>
                <h2>R$ <?= number_format($lucroPrevisto,2,",",".") ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h6>🔴 Parcelas em Atraso</h6>
                <h2><?= $parcelasAtraso ?></h2>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h6>🟡 Vencem Hoje</h6>
                <h2><?= $vencemHoje ?></h2>
            </div>
        </div>
    </div>

</div>

<?php

$sql = "

SELECT

    e.id,

    c.nome,

    e.valor_emprestado,

    e.valor_total,

    e.quantidade_parcelas,

    SUM(
        CASE
            WHEN p.data_pagamento IS NOT NULL
            THEN p.valor_pago
            ELSE 0
        END
    ) AS valor_recebido,

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

GROUP BY e.id

ORDER BY e.data_cadastro DESC

";

$emprestimos = $conexao->query($sql);

?>

<div class="card mt-4">

    <div class="card-header">

        <strong>📋 Empréstimos Ativos</strong>

    </div>

    <div class="card-body table-responsive">

        <table class="table table-hover align-middle">

            <thead>

                <tr>

                    <th>Cliente</th>

                    <th>Capital</th>

                    <th>Valor Total</th>

                    <th>Recebido</th>

                    <th>Saldo</th>

                    <th>Parcelas</th>

                    <th width="170">Progresso</th>

                    <th>Status</th>

                    <th width="90">Ações</th>

                </tr>

            </thead>

            <tbody>

            <?php while($emp = $emprestimos->fetch_assoc()){

                $recebido = $emp["valor_recebido"] ?? 0;

                $saldo = $emp["valor_total"] - $recebido;

                $pagas = $emp["parcelas_pagas"];

                $total = $emp["quantidade_parcelas"];

                $percentual = ($total > 0) ? ($pagas / $total) * 100 : 0;

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
                    <strong><?= htmlspecialchars($emp["nome"]) ?></strong>
                </td>

                <td>
                    R$ <?= number_format($emp["valor_emprestado"],2,",",".") ?>
                </td>

                <td>
                    R$ <?= number_format($emp["valor_total"],2,",",".") ?>
                </td>

                <td>
                    R$ <?= number_format($recebido,2,",",".") ?>
                </td>

                <td>
                    R$ <?= number_format($saldo,2,",",".") ?>
                </td>

                <td>

                    <?= $pagas ?>/<?= $total ?>

                </td>

                <td>

                    <div class="progress">

                        <div class="progress-bar"
                             style="width: <?= $percentual ?>%">

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

                    <a href="index.php?pagina=visualizar_emprestimo&id=<?= $emp['id'] ?>"
                       class="btn btn-primary btn-sm">

                        <i class="bi bi-eye"></i>

                    </a>

                </td>

            </tr>

            <?php } ?>

            </tbody>

        </table>

    </div>

</div>