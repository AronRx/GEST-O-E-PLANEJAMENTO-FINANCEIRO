<?php
$query = trim((string) ($_GET['pesquisa'] ?? ''));
$includeInactive = ($_GET['incluir_inativos'] ?? '') === '1';
$result = (new ClientService())->paginate($user['id'], [
    'q' => $query,
    'page' => max(1, (int) ($_GET['page'] ?? 1)),
    'per_page' => 50,
    'incluir_inativos' => $includeInactive ? 'true' : 'false'
]);
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4"><div><h2 class="mb-1">Clientes</h2><p class="text-muted mb-0">Gerencie os clientes vinculados à sua conta.</p></div><a href="index.php?pagina=novo_cliente" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Novo Cliente</a></div>
<div class="card shadow-sm">
    <form method="GET" class="row g-2 mb-3"><input type="hidden" name="pagina" value="clientes"><div class="col-md-8"><input type="search" name="pesquisa" class="form-control" placeholder="Pesquisar por nome, documento, telefone ou e-mail" value="<?= FinPlanWeb::e($query) ?>"></div><div class="col-md-2"><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="incluir_inativos" value="1" id="inativos" <?= $includeInactive ? 'checked' : '' ?>><label class="form-check-label" for="inativos">Incluir inativos</label></div></div><div class="col-md-2 d-grid"><button class="btn btn-primary"><i class="bi bi-search me-1"></i>Pesquisar</button></div></form>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Cliente</th><th>CPF/CNPJ</th><th>Telefone</th><th>WhatsApp</th><th>Status</th><th class="text-end">Ações</th></tr></thead><tbody>
    <?php foreach ($result['items'] as $client): ?>
        <tr><td><a class="fw-semibold text-decoration-none" href="index.php?pagina=visualizar_cliente&id=<?= FinPlanWeb::e($client['id']) ?>"><?= FinPlanWeb::e($client['nome']) ?></a><div class="small text-muted"><?= FinPlanWeb::e($client['email'] ?: 'Sem e-mail') ?></div></td><td><?= FinPlanWeb::e($client['cpf_cnpj'] ?: '—') ?></td><td><?= FinPlanWeb::e($client['telefone'] ?: '—') ?></td><td><?= FinPlanWeb::e($client['whatsapp'] ?: '—') ?></td><td><?= !empty($client['ativo']) ? '<span class="badge text-bg-success">Ativo</span>' : '<span class="badge text-bg-secondary">Inativo</span>' ?></td><td class="text-end"><a href="index.php?pagina=visualizar_cliente&id=<?= FinPlanWeb::e($client['id']) ?>" class="btn btn-outline-primary btn-sm" title="Visualizar"><i class="bi bi-eye"></i></a><a href="index.php?pagina=editar_cliente&id=<?= FinPlanWeb::e($client['id']) ?>" class="btn btn-outline-warning btn-sm" title="Editar"><i class="bi bi-pencil"></i></a></td></tr>
    <?php endforeach; ?>
    <?php if ($result['items'] === []): ?><tr><td colspan="6" class="text-center text-muted py-4">Nenhum cliente encontrado.</td></tr><?php endif; ?>
    </tbody></table></div>
    <div class="small text-muted mt-3">Exibindo <?= count($result['items']) ?> de <?= (int) $result['pagination']['total'] ?> cliente(s).</div>
</div>
