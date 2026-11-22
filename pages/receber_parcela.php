<?php
$installmentId = FinPlanId::tryNormalize($_GET['id'] ?? null);
if ($installmentId === null) {
    echo '<div class="alert alert-danger">Identificador de parcela inválido.</div>';
    return;
}
$installment = FinPlanWeb::installment($user['id'], $installmentId);
if (!$installment) {
    echo '<div class="alert alert-danger">Parcela não encontrada.</div>';
    return;
}
$accounts = FinPlanWeb::activeAccounts($user['id']);
?>
<div class="d-flex justify-content-between align-items-center mb-4"><div><h2 class="mb-1">Receber Parcela</h2><p class="text-muted mb-0"><?= FinPlanWeb::e($installment['cliente_nome']) ?> · Empréstimo #<?= FinPlanWeb::e($installment['emprestimo_id']) ?> · Parcela <?= (int) $installment['numero'] ?></p></div><a href="index.php?pagina=visualizar_emprestimo&id=<?= FinPlanWeb::e($installment['emprestimo_id']) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Voltar</a></div>
<?php if ((float) $installment['saldo'] <= 0 || $installment['emprestimo_status'] !== 'ativo'): ?><div class="alert alert-warning">Esta parcela não possui saldo para recebimento ou o contrato não está ativo.</div><?php else: ?>
<div class="row g-3 mb-3"><div class="col-md-4"><div class="card dashboard-card"><div class="dashboard-label">Valor da parcela</div><div class="dashboard-value dashboard-money"><?= FinPlanWeb::money($installment['valor']) ?></div></div></div><div class="col-md-4"><div class="card dashboard-card"><div class="dashboard-label">Já recebido</div><div class="dashboard-value dashboard-money"><?= FinPlanWeb::money($installment['valor_pago']) ?></div></div></div><div class="col-md-4"><div class="card dashboard-card"><div class="dashboard-label">Saldo restante</div><div class="dashboard-value dashboard-money text-primary"><?= FinPlanWeb::money($installment['saldo']) ?></div></div></div></div>
<form method="POST" action="index.php"><input type="hidden" name="_csrf" value="<?= FinPlanWeb::e(FinPlanWeb::csrfToken()) ?>"><input type="hidden" name="_action" value="receipt_create"><input type="hidden" name="parcela_id" value="<?= FinPlanWeb::e($installment['id']) ?>"><div class="card shadow-sm"><div class="card-body"><div class="row g-3">
    <div class="col-md-4"><label class="form-label" for="valor">Valor recebido (R$) *</label><input class="form-control" type="number" id="valor" name="valor" min="0.01" max="<?= FinPlanWeb::e($installment['saldo']) ?>" step="0.01" value="<?= FinPlanWeb::e($installment['saldo']) ?>" required><div class="form-text">O valor não pode ultrapassar o saldo restante.</div></div>
    <div class="col-md-4"><label class="form-label" for="data_recebimento">Data do recebimento *</label><input class="form-control" type="date" id="data_recebimento" name="data_recebimento" value="<?= date('Y-m-d') ?>" required></div>
    <div class="col-md-4"><label class="form-label" for="forma_pagamento">Forma de pagamento *</label><select class="form-select" id="forma_pagamento" name="forma_pagamento" required><?php foreach (['pix' => 'PIX', 'dinheiro' => 'Dinheiro', 'transferencia' => 'Transferência', 'boleto' => 'Boleto', 'cartao' => 'Cartão', 'outro' => 'Outro'] as $value => $label): ?><option value="<?= $value ?>" <?= $value === 'pix' ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label" for="conta_id">Conta de destino</label><select class="form-select" id="conta_id" name="conta_id"><option value="">Carteira (padrão)</option><?php foreach ($accounts as $account): ?><option value="<?= FinPlanWeb::e($account['id']) ?>"><?= FinPlanWeb::e($account['nome']) ?> · <?= FinPlanWeb::e($account['tipo']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label" for="observacoes">Observações</label><input class="form-control" type="text" id="observacoes" name="observacoes" maxlength="255"></div>
</div></div><div class="card-footer bg-white d-flex justify-content-end gap-2"><a href="index.php?pagina=visualizar_emprestimo&id=<?= FinPlanWeb::e($installment['emprestimo_id']) ?>" class="btn btn-outline-secondary">Cancelar</a><button type="submit" class="btn btn-success"><i class="bi bi-cash-coin me-1"></i>Registrar recebimento</button></div></div></form>
<?php endif; ?>
