<?php

$id = isset($_GET["id"]) ? intval($_GET["id"]) : 0;

// ================================
// BUSCA DA PARCELA
// ================================

$sql = "
SELECT
    parcelas.*,
    clientes.nome
FROM parcelas
INNER JOIN emprestimos
    ON parcelas.emprestimo_id = emprestimos.id
INNER JOIN clientes
    ON emprestimos.cliente_id = clientes.id
WHERE parcelas.id = ?
";

$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows == 0) {

    echo "<div class='alert alert-danger'>Parcela não encontrada.</div>";
    return;

}

$parcela = $resultado->fetch_assoc();

// ================================
// VERIFICA SE JÁ FOI PAGA
// ================================

if ($parcela["data_pagamento"] != NULL) {

    echo "
    <div class='alert alert-success'>
        Esta parcela já foi paga.
    </div>

    <a href='index.php?pagina=visualizar_emprestimo&id=".$parcela["emprestimo_id"]."' class='btn btn-primary'>
        Voltar
    </a>
    ";

    return;
}

// ================================
// RESUMO DO EMPRÉSTIMO
// ================================

$sqlResumo = "
SELECT

    e.id,
    e.valor_emprestado,
    e.valor_total,
    e.quantidade_parcelas,

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
    ) AS pagas

FROM emprestimos e

LEFT JOIN parcelas p
ON p.emprestimo_id = e.id

WHERE e.id = ?

GROUP BY e.id
";

$stmt = $conexao->prepare($sqlResumo);
$stmt->bind_param("i", $parcela["emprestimo_id"]);
$stmt->execute();

$resumo = $stmt->get_result()->fetch_assoc();

$recebido = $resumo["recebido"] ?? 0;

$saldo = $resumo["valor_total"] - $recebido;

$percentual = 0;

if ($resumo["quantidade_parcelas"] > 0) {

    $percentual =
        ($resumo["pagas"] / $resumo["quantidade_parcelas"]) * 100;

}

?>

<h2 class="mb-4">
    <i class="bi bi-cash-stack"></i>
    Receber Parcela
</h2>

<div class="row mb-4">

    <div class="col-md-3">

        <div class="card border-0 shadow-sm">

            <div class="card-body text-center">

                <small class="text-muted">
                    Valor do Contrato
                </small>

                <h5 class="mt-2">

                    R$ <?= number_format($resumo["valor_total"],2,",",".") ?>

                </h5>

            </div>

        </div>

    </div>

    <div class="col-md-3">

        <div class="card border-0 shadow-sm">

            <div class="card-body text-center">

                <small class="text-muted">

                    Recebido

                </small>

                <h5 class="mt-2 text-success">

                    R$ <?= number_format($recebido,2,",",".") ?>

                </h5>

            </div>

        </div>

    </div>

    <div class="col-md-3">

        <div class="card border-0 shadow-sm">

            <div class="card-body text-center">

                <small class="text-muted">

                    Saldo Devedor

                </small>

                <h5 class="mt-2 text-danger">

                    R$ <?= number_format($saldo,2,",",".") ?>

                </h5>

            </div>

        </div>

    </div>

    <div class="col-md-3">

        <div class="card border-0 shadow-sm">

            <div class="card-body text-center">

                <small class="text-muted">

                    Parcelas Pagas

                </small>

                <h5 class="mt-2">

                    <?= $resumo["pagas"] ?>/<?= $resumo["quantidade_parcelas"] ?>

                </h5>

            </div>

        </div>

    </div>

</div>

<div class="card shadow-sm mb-4">

    <div class="card-body">

        <div class="d-flex justify-content-between mb-2">

            <strong>

                Progresso do Empréstimo

            </strong>

            <strong>

                <?= round($percentual) ?>%

            </strong>

        </div>

        <div class="progress" style="height:25px;">

            <div
                class="progress-bar bg-success progress-bar-striped progress-bar-animated"
                style="width: <?= $percentual ?>%;">

                <?= $resumo["pagas"] ?>/<?= $resumo["quantidade_parcelas"] ?>

                Parcelas

            </div>

        </div>

    </div>

</div>

<div class="card shadow">

    <div class="card-body">

        <form action="salvar_pagamento.php" method="POST">

            <input
                type="hidden"
                name="id"
                value="<?= $parcela["id"] ?>">

            <div class="row">

                <div class="col-md-6 mb-3">

                    <label class="form-label">

                        Cliente

                    </label>

                    <input
                        type="text"
                        class="form-control"
                        value="<?= htmlspecialchars($parcela["nome"]) ?>"
                        disabled>

                </div>

                <div class="col-md-3 mb-3">

                    <label class="form-label">

                        Parcela

                    </label>

                    <input
                        type="text"
                        class="form-control"
                        value="<?= $parcela["numero_parcela"] ?>"
                        disabled>

                </div>

                <div class="col-md-3 mb-3">

                    <label class="form-label">

                        Valor da Parcela

                    </label>

                    <input
                        type="text"
                        class="form-control"
                        value="R$ <?= number_format($parcela["valor_parcela"],2,",",".") ?>"
                        disabled>

                </div>

                <div class="col-md-6 mb-3">

                    <label class="form-label">

                        Data do Pagamento

                    </label>

                    <input
                        type="date"
                        name="data_pagamento"
                        class="form-control"
                        value="<?= date("Y-m-d") ?>"
                        required>

                </div>

                <div class="col-md-6 mb-3">

                    <label class="form-label">

                        Valor Recebido

                    </label>

                    <input
                        type="number"
                        step="0.01"
                        name="valor_pago"
                        class="form-control"
                        value="<?= $parcela["valor_parcela"] ?>"
                        required>

                </div>

                <div class="col-12 mb-3">

                    <label class="form-label">

                        Observações

                    </label>

                    <textarea
                        name="observacoes"
                        class="form-control"
                        rows="4"></textarea>

                </div>

            </div>

            <div class="d-flex justify-content-between">

                <a
                    href="index.php?pagina=visualizar_emprestimo&id=<?= $parcela["emprestimo_id"] ?>"
                    class="btn btn-secondary">

                    <i class="bi bi-arrow-left"></i>

                    Voltar

                </a>

                <button class="btn btn-success">

                    <i class="bi bi-check-circle"></i>

                    Confirmar Recebimento

                </button>

            </div>

        </form>

    </div>

</div>