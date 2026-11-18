<?php
$stats = FinPlanWeb::dashboard((int) $user['id']);
$recentLoans = (new LoanService())->paginate((int) $user['id'], ['page' => 1, 'per_page' => 5])['items'];
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div><h2 class="mb-1">Visão geral</h2><p class="text-muted mb-0">Resumo da sua operação financeira.</p></div>
    <div class="d-flex gap-2"><a href="index.php?pagina=novo_cliente" class="btn btn-outline-primary"><i class="bi bi-person-plus me-1"></i>Novo cliente</a><a href="index.php?pagina=novo_emprestimo" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Novo empréstimo</a></div>
</div>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="card dashboard-card h-100"><div class="dashboard-icon text-primary"><i class="bi bi-people"></i></div><div class="dashboard-label">Clientes ativos</div><div class="dashboard-value"><?= (int) ($stats['clientes_ativos'] ?? 0) ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card dashboard-card h-100"><div class="dashboard-icon text-success"><i class="bi bi-cash-stack"></i></div><div class="dashboard-label">Empréstimos ativos</div><div class="dashboard-value"><?= (int) ($stats['emprestimos_ativos'] ?? 0) ?></div><div class="small text-muted">De <?= (int) ($stats['emprestimos_total'] ?? 0) ?> contratos</div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card dashboard-card h-100"><div class="dashboard-icon text-info"><i class="bi bi-wallet2"></i></div><div class="dashboard-label">Total recebido</div><div class="dashboard-value dashboard-money"><?= FinPlanWeb::money($stats['total_recebido'] ?? 0) ?></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card dashboard-card h-100"><div class="dashboard-icon text-warning"><i class="bi bi-hourglass-split"></i></div><div class="dashboard-label">Saldo a receber</div><div class="dashboard-value dashboard-money"><?= FinPlanWeb::money($stats['saldo_a_receber'] ?? 0) ?></div></div></div>
</div>
<div class="row g-3 mb-4">
    <div class="col-lg-4"><div class="card summary-card h-100"><div class="card-body"><div class="text-muted small mb-1">Principal emprestado</div><div class="fs-4 fw-bold"><?= FinPlanWeb::money($stats['principal_total'] ?? 0) ?></div><div class="small text-muted mt-2">Capital originalmente concedido</div></div></div></div>
    <div class="col-lg-4"><div class="card summary-card h-100"><div class="card-body"><div class="text-muted small mb-1">Contratos quitados</div><div class="fs-4 fw-bold text-success"><?= (int) ($stats['emprestimos_quitados'] ?? 0) ?></div><div class="small text-muted mt-2">Empréstimos integralmente recebidos</div></div></div></div>
    <div class="col-lg-4"><div class="card summary-card h-100"><div class="card-body"><div class="text-muted small mb-1">Parcelas atrasadas</div><div class="fs-4 fw-bold <?= (int) ($stats['parcelas_atrasadas'] ?? 0) > 0 ? 'text-danger' : 'text-success' ?>"><?= (int) ($stats['parcelas_atrasadas'] ?? 0) ?></div><div class="small text-muted mt-2">Com vencimento anterior à data atual</div></div></div></div>
</div>
<div class="card shadow-sm">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><div><h5 class="mb-1">Empréstimos recentes</h5><p class="text-muted small mb-0">Últimos contratos registrados.</p></div><a href="index.php?pagina=emprestimos" class="btn btn-outline-primary btn-sm">Ver todos</a></div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Cliente</th><th>Principal</th><th>Total do contrato</th><th>Recebido</th><th>Situação</th><th></th></tr></thead><tbody>
    <?php foreach ($recentLoans as $loan): ?>
        <?php $situation = (string) $loan['situacao']; $badge = ['ativo' => 'primary', 'atrasado' => 'danger', 'quitado' => 'success', 'cancelado' => 'secondary'][$situation] ?? 'secondary'; ?>
        <tr><td class="fw-semibold"><?= FinPlanWeb::e($loan['cliente_nome']) ?></td><td><?= FinPlanWeb::money($loan['valor_principal']) ?></td><td><?= FinPlanWeb::money($loan['valor_total']) ?></td><td><?= FinPlanWeb::money($loan['total_pago']) ?></td><td><span class="badge text-bg-<?= $badge ?>"><?= FinPlanWeb::e(ucfirst($situation)) ?></span></td><td class="text-end"><a href="index.php?pagina=visualizar_emprestimo&id=<?= (int) $loan['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td></tr>
    <?php endforeach; ?>
    <?php if ($recentLoans === []): ?><tr><td colspan="6" class="text-center text-muted py-4">Nenhum empréstimo cadastrado ainda.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
