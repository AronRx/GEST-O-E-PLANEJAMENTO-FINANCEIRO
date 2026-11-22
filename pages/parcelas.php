<?php
$installments = FinPlanDatabase::runAsUser($user['id'], function (PDO $pdo) use ($user): array {
    $statement = $pdo->prepare(
        'SELECT p.id, p.emprestimo_id, p.numero, p.vencimento, p.valor, p.valor_pago, p.saldo, p.status,
                c.nome AS cliente_nome
         FROM finplan.vw_parcelas p
         JOIN finplan.emprestimos e ON e.id = p.emprestimo_id
         JOIN finplan.clientes c ON c.id = e.cliente_id
         WHERE c.usuario_id = :usuario_id
         ORDER BY c.nome, p.vencimento, p.numero
         LIMIT 1000'
    );
    $statement->execute(['usuario_id' => $user['id']]);
    return $statement->fetchAll();
});
?>
<div class="d-flex justify-content-between align-items-center mb-4"><div><h2 class="mb-1">Controle de Parcelas</h2><p class="text-muted mb-0">Acompanhe vencimentos, pagamentos parciais e quitações.</p></div></div>
<div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Cliente</th><th>Empréstimo</th><th>Parcela</th><th>Vencimento</th><th>Valor</th><th>Pago</th><th>Saldo</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($installments as $item): ?>
    <?php $status = (string) $item['status']; $label = ['paga' => 'Paga', 'parcial' => 'Parcial', 'atrasada' => 'Atrasada', 'pendente' => 'Pendente'][$status] ?? $status; $color = ['paga' => 'success', 'parcial' => 'warning', 'atrasada' => 'danger', 'pendente' => 'secondary'][$status] ?? 'secondary'; ?>
    <tr><td class="fw-semibold"><?= FinPlanWeb::e($item['cliente_nome']) ?></td><td><a href="index.php?pagina=visualizar_emprestimo&id=<?= FinPlanWeb::e($item['emprestimo_id']) ?>">#<?= FinPlanWeb::e($item['emprestimo_id']) ?></a></td><td><?= (int) $item['numero'] ?></td><td><?= FinPlanWeb::date($item['vencimento']) ?></td><td><?= FinPlanWeb::money($item['valor']) ?></td><td><?= FinPlanWeb::money($item['valor_pago']) ?></td><td><?= FinPlanWeb::money($item['saldo']) ?></td><td><span class="badge text-bg-<?= $color ?>"><?= FinPlanWeb::e($label) ?></span></td><td class="text-end"><?php if ((float) $item['saldo'] > 0): ?><a href="index.php?pagina=receber_parcela&id=<?= FinPlanWeb::e($item['id']) ?>" class="btn btn-success btn-sm">Receber</a><?php endif; ?></td></tr>
<?php endforeach; ?>
<?php if ($installments === []): ?><tr><td colspan="9" class="text-center text-muted py-4">Nenhuma parcela cadastrada.</td></tr><?php endif; ?>
</tbody></table></div><div class="small text-muted mt-3">Exibindo até 1.000 parcelas.</div></div>
