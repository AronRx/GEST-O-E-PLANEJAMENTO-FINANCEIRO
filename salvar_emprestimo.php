<?php

include("config/conexao.php");

$cliente_id = intval($_POST["cliente_id"]);
$valor = floatval($_POST["valor"]);
$juros = floatval($_POST["juros"]);
$parcelas = intval($_POST["parcelas"]);
$primeiro_vencimento = $_POST["primeiro_vencimento"];
$observacoes = trim($_POST["observacoes"]);

$valor_total = $valor + ($valor * ($juros / 100));
$valor_parcela = round($valor_total / $parcelas, 2);

$sql = "INSERT INTO emprestimos
(
cliente_id,
valor_emprestado,
juros,
quantidade_parcelas,
valor_total,
valor_parcela,
primeiro_vencimento,
observacoes
)
VALUES
(?,?,?,?,?,?,?,?)";

$stmt = $conexao->prepare($sql);

$stmt->bind_param(
"idiiddss",
$cliente_id,
$valor,
$juros,
$parcelas,
$valor_total,
$valor_parcela,
$primeiro_vencimento,
$observacoes
);

$stmt->execute();

$emprestimo_id = $conexao->insert_id;

$data = new DateTime($primeiro_vencimento);

$sqlParcela = "INSERT INTO parcelas
(
emprestimo_id,
numero_parcela,
valor_parcela,
vencimento
)
VALUES
(?,?,?,?)";

$stmtParcela = $conexao->prepare($sqlParcela);

for($i = 1; $i <= $parcelas; $i++){

    $vencimento = $data->format("Y-m-d");

    $stmtParcela->bind_param(
        "iids",
        $emprestimo_id,
        $i,
        $valor_parcela,
        $vencimento
    );

    $stmtParcela->execute();

    $data->modify("+1 month");

}

header("Location: index.php?pagina=dashboard");

exit;