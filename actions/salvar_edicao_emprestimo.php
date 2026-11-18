<?php

require_once __DIR__ . '/../config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?pagina=emprestimos');
    exit;
}

$id = trim((string) ($_POST['id'] ?? ''));
$clienteId = trim((string) ($_POST['cliente_id'] ?? ''));
$valorEmprestado = (float) ($_POST['valor_emprestado'] ?? 0);
$juros = (float) ($_POST['juros'] ?? 0);
$quantidadeParcelas = (int) ($_POST['quantidade_parcelas'] ?? 0);
$primeiroVencimento = trim((string) ($_POST['primeiro_vencimento'] ?? ''));
$observacoes = trim((string) ($_POST['observacoes'] ?? ''));

$dataValida = DateTime::createFromFormat('!Y-m-d', $primeiroVencimento);

if (
    !Database::isUuid($id) ||
    !Database::isUuid($clienteId) ||
    $valorEmprestado <= 0 ||
    $juros < 0 ||
    $quantidadeParcelas < 1 ||
    $quantidadeParcelas > 600 ||
    !$dataValida ||
    $dataValida->format('Y-m-d') !== $primeiroVencimento
) {
    http_response_code(422);
    exit('Dados inválidos.');
}

$valorTotal = round($valorEmprestado + ($valorEmprestado * ($juros / 100)), 2);
$valorParcela = round($valorTotal / $quantidadeParcelas, 2);

$conexao->begin_transaction();

try {
    $stmt = $conexao->prepare(
        'SELECT COUNT(*) AS total FROM parcelas WHERE emprestimo_id = ? AND data_pagamento IS NOT NULL'
    );
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $parcelasPagas = (int) $stmt->get_result()->fetch_assoc()['total'];

    if ($parcelasPagas > 0) {
        $stmt = $conexao->prepare('UPDATE emprestimos SET observacoes = ? WHERE id = ?');
        $stmt->bind_param('ss', $observacoes, $id);
        $stmt->execute();

        $conexao->commit();
        header('Location: ../index.php?pagina=visualizar_emprestimo&id=' . rawurlencode($id));
        exit;
    }

    $stmt = $conexao->prepare(
        'UPDATE emprestimos
         SET cliente_id = ?, valor_emprestado = ?, juros = ?, quantidade_parcelas = ?,
             valor_total = ?, valor_parcela = ?, primeiro_vencimento = ?, observacoes = ?
         WHERE id = ?'
    );
    $stmt->bind_param(
        'sddiddsss',
        $clienteId,
        $valorEmprestado,
        $juros,
        $quantidadeParcelas,
        $valorTotal,
        $valorParcela,
        $primeiroVencimento,
        $observacoes,
        $id
    );
    $stmt->execute();

    $stmt = $conexao->prepare(
        'SELECT id FROM parcelas WHERE emprestimo_id = ? ORDER BY numero_parcela'
    );
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $resultado = $stmt->get_result();

    $idsExistentes = [];
    while ($parcela = $resultado->fetch_assoc()) {
        $idsExistentes[] = (string) $parcela['id'];
    }

    $data = new DateTime($primeiroVencimento);

    for ($numero = 1; $numero <= $quantidadeParcelas; $numero++) {
        $vencimento = $data->format('Y-m-d');
        $valorAtual = $numero === $quantidadeParcelas
            ? round($valorTotal - ($valorParcela * ($quantidadeParcelas - 1)), 2)
            : $valorParcela;

        if (isset($idsExistentes[$numero - 1])) {
            $parcelaId = $idsExistentes[$numero - 1];
            $stmtUpdate = $conexao->prepare(
                'UPDATE parcelas SET numero_parcela = ?, valor_parcela = ?, vencimento = ? WHERE id = ?'
            );
            $stmtUpdate->bind_param('idss', $numero, $valorAtual, $vencimento, $parcelaId);
            $stmtUpdate->execute();
        } else {
            $stmtInsert = $conexao->prepare(
                'INSERT INTO parcelas (emprestimo_id, numero_parcela, valor_parcela, vencimento) VALUES (?, ?, ?, ?)'
            );
            $stmtInsert->bind_param('sids', $id, $numero, $valorAtual, $vencimento);
            $stmtInsert->execute();
        }

        $data->modify('+1 month');
    }

    if (count($idsExistentes) > $quantidadeParcelas) {
        for ($i = $quantidadeParcelas; $i < count($idsExistentes); $i++) {
            $stmtDelete = $conexao->prepare('DELETE FROM parcelas WHERE id = ?');
            $stmtDelete->bind_param('s', $idsExistentes[$i]);
            $stmtDelete->execute();
        }
    }

    $conexao->commit();
    header('Location: ../index.php?pagina=visualizar_emprestimo&id=' . rawurlencode($id));
    exit;
} catch (Throwable $exception) {
    $conexao->rollback();
    error_log('[SistemaFinanceiro] Erro ao editar empréstimo: ' . $exception->getMessage());
    http_response_code(500);
    exit('Não foi possível atualizar o empréstimo. Consulte o log do PHP.');
}
