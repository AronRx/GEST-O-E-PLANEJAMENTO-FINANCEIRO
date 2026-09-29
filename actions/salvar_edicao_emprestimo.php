<?php

include("../config/conexao.php");

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: ../index.php");
    exit;
}

$id = intval($_POST["id"]);
$cliente_id = intval($_POST["cliente_id"]);
$valor_emprestado = floatval($_POST["valor_emprestado"]);
$juros = floatval($_POST["juros"]);
$quantidade_parcelas = intval($_POST["quantidade_parcelas"]);
$primeiro_vencimento = $_POST["primeiro_vencimento"];
$observacoes = trim($_POST["observacoes"]);

// ===========================
// Validações
// ===========================

if (
    $cliente_id <= 0 ||
    $valor_emprestado <= 0 ||
    $juros < 0 ||
    $quantidade_parcelas <= 0 ||
    empty($primeiro_vencimento)
) {

    die("Dados inválidos.");

}

// ===========================
// Inicia Transação
// ===========================

$conexao->begin_transaction();

try{

    // ===========================
    // Verifica parcelas pagas
    // ===========================

    $sql = "
    SELECT COUNT(*) AS total
    FROM parcelas
    WHERE emprestimo_id = ?
    AND data_pagamento IS NOT NULL
    ";

    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i",$id);
    $stmt->execute();

    $parcelasPagas =
    $stmt
    ->get_result()
    ->fetch_assoc()["total"];

    // ===========================
    // Caso existam parcelas pagas
    // Apenas observações
    // ===========================

    if($parcelasPagas > 0){

        $sql = "
        UPDATE emprestimos
        SET observacoes = ?
        WHERE id = ?
        ";

        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("si",$observacoes,$id);
        $stmt->execute();

        $conexao->commit();

        header("Location: ../index.php?pagina=visualizar_emprestimo&id=".$id);

        exit;

    }

    // ===========================
    // Recalcula contrato
    // ===========================

    $valor_total =
    $valor_emprestado +
    ($valor_emprestado * ($juros / 100));

    $valor_total =
    round($valor_total,2);

    $valor_parcela =
    round(
        $valor_total /
        $quantidade_parcelas,
        2
    );

    // ===========================
    // Atualiza empréstimo
    // ===========================

    $sql = "
    UPDATE emprestimos
    SET

    cliente_id=?,
    valor_emprestado=?,
    juros=?,
    quantidade_parcelas=?,
    valor_total=?,
    valor_parcela=?,
    primeiro_vencimento=?,
    observacoes=?

    WHERE id=?
    ";

    $stmt = $conexao->prepare($sql);

    $stmt->bind_param(

        "iddiddssi",

        $cliente_id,
        $valor_emprestado,
        $juros,
        $quantidade_parcelas,
        $valor_total,
        $valor_parcela,
        $primeiro_vencimento,
        $observacoes,
        $id

    );

    $stmt->execute();

    // ===========================
    // Descobre quantas parcelas
    // já foram pagas
    // ===========================

    $sql = "
    SELECT COUNT(*) AS total
    FROM parcelas
    WHERE emprestimo_id = ?
    AND data_pagamento IS NOT NULL
    ";

    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i",$id);
    $stmt->execute();

    $numero =
    $stmt
    ->get_result()
    ->fetch_assoc()["total"] + 1;

    // ===========================
    // Ajusta data inicial
    // ===========================

    $data = new DateTime($primeiro_vencimento);

    for($i = 1; $i < $numero; $i++){

        $data->modify("+1 month");

    }

    // ===========================
    // Busca parcelas pendentes
    // ===========================

    $sql = "
    SELECT id
    FROM parcelas
    WHERE emprestimo_id = ?
    AND data_pagamento IS NULL
    ORDER BY numero_parcela
    ";

    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("i",$id);
    $stmt->execute();

    $resultado = $stmt->get_result();

    while($parcela = $resultado->fetch_assoc()){

        $vencimento =
        $data->format("Y-m-d");

        $sqlUpdate = "
        UPDATE parcelas

        SET

        numero_parcela=?,
        valor_parcela=?,
        vencimento=?

        WHERE id=?
        ";

        $stmtUpdate =
        $conexao->prepare($sqlUpdate);

        $stmtUpdate->bind_param(

            "idsi",

            $numero,
            $valor_parcela,
            $vencimento,
            $parcela["id"]

        );

        $stmtUpdate->execute();

        $numero++;

        $data->modify("+1 month");

    }

    // ===========================
    // Finaliza
    // ===========================

    $conexao->commit();

    header("Location: ../index.php?pagina=visualizar_emprestimo&id=".$id);

    exit;

}catch(Exception $e){

    $conexao->rollback();

    die("Erro ao atualizar empréstimo: ".$e->getMessage());

}

?>