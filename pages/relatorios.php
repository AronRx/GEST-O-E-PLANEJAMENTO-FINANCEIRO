<?php $stats = FinPlanWeb::dashboard((int) $user['id']); ?>
<div class="mb-4"><h2 class="mb-1">Relatórios</h2><p class="text-muted mb-0">Resumo consolidado dos contratos e recebimentos da sua conta.</p></div>
<div class="card shadow-sm"><h5 class="mb-3">Resumo financeiro</h5><div class="table-responsive"><table class="table align-middle mb-0"><tbody>
<tr><th>Clientes ativos</th><td class="text-end"><?= (int) ($stats['clientes_ativos'] ?? 0) ?></td></tr>
<tr><th>Empréstimos cadastrados</th><td class="text-end"><?= (int) ($stats['emprestimos_total'] ?? 0) ?></td></tr>
<tr><th>Empréstimos ativos</th><td class="text-end"><?= (int) ($stats['emprestimos_ativos'] ?? 0) ?></td></tr>
<tr><th>Empréstimos quitados</th><td class="text-end"><?= (int) ($stats['emprestimos_quitados'] ?? 0) ?></td></tr>
<tr><th>Principal concedido</th><td class="text-end fw-semibold"><?= FinPlanWeb::money($stats['principal_total'] ?? 0) ?></td></tr>
<tr><th>Total recebido</th><td class="text-end fw-semibold text-success"><?= FinPlanWeb::money($stats['total_recebido'] ?? 0) ?></td></tr>
<tr><th>Saldo a receber</th><td class="text-end fw-semibold"><?= FinPlanWeb::money($stats['saldo_a_receber'] ?? 0) ?></td></tr>
<tr><th>Parcelas atrasadas</th><td class="text-end <?= (int) ($stats['parcelas_atrasadas'] ?? 0) > 0 ? 'text-danger fw-semibold' : '' ?>"><?= (int) ($stats['parcelas_atrasadas'] ?? 0) ?></td></tr>
</tbody></table></div><p class="small text-muted mt-3 mb-0">Os valores são calculados pelas views do PostgreSQL. Os saldos consideram os recebimentos registrados no sistema.</p></div>
