<?php

include("config/conexao.php");

$cliente_id = trim((string) ($_POST["cliente_id"] ?? ""));
$valor = (float) ($_POST["valor"] ?? 0);
$juros = (float) ($_POST["juros"] ?? 0);
$parcelas = (int) ($_POST["parcelas"] ?? 0);
$primeiro_vencimento = trim((string) ($_POST["primeiro_vencimento"] ?? ""));
$observacoes = trim((string) ($_POST["observacoes"] ?? ""));

$dataValida = DateTime::createFromFormat('!Y-m-d', $primeiro_vencimento);

if (
    !Database::isUuid($cliente_id) ||
    $valor <= 0 ||
    $juros < 0 ||
    $parcelas < 1 ||
    $parcelas > 600 ||
    !$dataValida ||
    $dataValida->format('Y-m-d') !== $primeiro_vencimento
) {
    http_response_code(422);
    exit('Dados do empréstimo inválidos.');
}

$valor_total = round($valor + ($valor * ($juros / 100)), 2);
$valor_parcela = round($valor_total / $parcelas, 2);

$conexao->begin_transaction();

try {
    $sql = "INSERT INTO emprestimos
    (cliente_id, valor_emprestado, juros, quantidade_parcelas, valor_total,
     valor_parcela, primeiro_vencimento, observacoes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conexao->prepare($sql);
    $stmt->bind_param(
        "sddiddss",
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
    if (!$emprestimo_id) {
        throw new RuntimeException('O PostgreSQL não retornou o identificador do empréstimo.');
    }

    $data = new DateTime($primeiro_vencimento);
    $sqlParcela = "INSERT INTO parcelas
        (emprestimo_id, numero_parcela, valor_parcela, vencimento)
        VALUES (?, ?, ?, ?)";
    $stmtParcela = $conexao->prepare($sqlParcela);

    for ($i = 1; $i <= $parcelas; $i++) {
        $vencimento = $data->format('Y-m-d');
        $valorAtual = ($i === $parcelas)
            ? round($valor_total - ($valor_parcela * ($parcelas - 1)), 2)
            : $valor_parcela;

        $stmtParcela->bind_param(
            "sids",
            $emprestimo_id,
            $i,
            $valorAtual,
            $vencimento
        );
        $stmtParcela->execute();
        $data->modify('+1 month');
    }

    $conexao->commit();
    header("Location: index.php?pagina=dashboard");
    exit;
} catch (Throwable $exception) {
    $conexao->rollback();
    error_log('[SistemaFinanceiro] Erro ao salvar empréstimo: ' . $exception->getMessage());
    http_response_code(500);
    exit('Não foi possível salvar o empréstimo. Consulte o log do PHP.');
}
