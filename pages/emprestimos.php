<?php
$search = trim((string) ($_GET['pesquisa'] ?? ''));
$situation = trim((string) ($_GET['situacao'] ?? ''));
$result = (new LoanService())->paginate((int) $user['id'], ['q' => $search, 'situacao' => $situation, 'page' => 1, 'per_page' => 100]);
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4"><div><h2 class="mb-1">Empréstimos</h2><p class="text-muted mb-0">Contratos, valores recebidos e saldos em aberto.</p></div><a href="index.php?pagina=novo_emprestimo" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Novo Empréstimo</a></div>
<div class="card shadow-sm">
    <form method="GET" class="row g-2 mb-3"><input type="hidden" name="pagina" value="emprestimos"><div class="col-md-7"><input type="search" name="pesquisa" class="form-control" placeholder="Pesquisar cliente..." value="<?= FinPlanWeb::e($search) ?>"></div><div class="col-md-3"><select name="situacao" class="form-select"><option value="">Todas as situações</option><?php foreach (['ativo' => 'Ativo', 'atrasado' => 'Atrasado', 'quitado' => 'Quitado', 'cancelado' => 'Cancelado'] as $value => $label): ?><option value="<?= $value ?>" <?= $situation === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div><div class="col-md-2 d-grid"><button class="btn btn-primary"><i class="bi bi-search me-1"></i>Filtrar</button></div></form>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Cliente</th><th>Principal</th><th>Juros a.m.</th><th>Parcelas</th><th>Total</th><th>Recebido</th><th>Saldo a receber</th><th>Situação</th><th></th></tr></thead><tbody>
    <?php foreach ($result['items'] as $loan): ?>
        <?php $s = (string) $loan['situacao']; $badge = ['ativo' => 'primary', 'atrasado' => 'danger', 'quitado' => 'success', 'cancelado' => 'secondary'][$s] ?? 'secondary'; ?>
        <tr><td class="fw-semibold"><?= FinPlanWeb::e($loan['cliente_nome']) ?><div class="small text-muted">Contrato #<?= (int) $loan['id'] ?></div></td><td><?= FinPlanWeb::money($loan['valor_principal']) ?></td><td><?= number_format((float) $loan['taxa_juros_mensal'], 2, ',', '.') ?>%</td><td><?= (int) $loan['qtd_parcelas'] ?></td><td><?= FinPlanWeb::money($loan['valor_total']) ?></td><td><?= FinPlanWeb::money($loan['total_pago']) ?></td><td><?= FinPlanWeb::money($loan['saldo_a_receber']) ?></td><td><span class="badge text-bg-<?= $badge ?>"><?= FinPlanWeb::e(ucfirst($s)) ?></span></td><td class="text-end"><a href="index.php?pagina=visualizar_emprestimo&id=<?= (int) $loan['id'] ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-eye"></i></a></td></tr>
    <?php endforeach; ?>
    <?php if ($result['items'] === []): ?><tr><td colspan="9" class="text-center text-muted py-4">Nenhum empréstimo encontrado.</td></tr><?php endif; ?>
    </tbody></table></div>
    <div class="small text-muted mt-3">Exibindo <?= count($result['items']) ?> de <?= (int) $result['pagination']['total'] ?> contrato(s).</div>
</div>
