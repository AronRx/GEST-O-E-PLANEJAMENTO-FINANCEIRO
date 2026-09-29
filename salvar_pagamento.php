<?php

include("config/conexao.php");

$id = intval($_POST["id"]);
$data_pagamento = $_POST["data_pagamento"];
$valor_pago = floatval($_POST["valor_pago"]);
$observacoes = trim($_POST["observacoes"]);

$sql = "UPDATE parcelas
SET
    data_pagamento = ?,
    valor_pago = ?,
    status = 'PAGA',
    observacoes = ?
WHERE id = ?";

$stmt = $conexao->prepare($sql);

$stmt->bind_param(
    "sdsi",
    $data_pagamento,
    $valor_pago,
    $observacoes,
    $id
);

if($stmt->execute()){

    header("Location: index.php?pagina=parcelas");
    exit;

}else{

    echo "Erro ao registrar pagamento.";

}