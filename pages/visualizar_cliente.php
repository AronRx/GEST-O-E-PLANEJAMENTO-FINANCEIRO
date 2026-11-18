<?php
$clientId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($clientId === false) {
    echo '<div class="alert alert-danger">Identificador de cliente inválido.</div>';
    return;
}
$client = (new ClientService())->find((int) $user['id'], (int) $clientId);
$fields = [
    'CPF/CNPJ' => $client['cpf_cnpj'], 'RG' => $client['rg'], 'Telefone' => $client['telefone'],
    'WhatsApp' => $client['whatsapp'], 'E-mail' => $client['email'], 'CEP' => $client['cep'],
    'Rua' => $client['rua'], 'Número' => $client['numero'], 'Bairro' => $client['bairro'],
    'Cidade' => $client['cidade'], 'Estado' => $client['estado'], 'Endereço completo' => $client['endereco']
];
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4"><div><h2 class="mb-1"><?= FinPlanWeb::e($client['nome']) ?></h2><span class="badge text-bg-<?= !empty($client['ativo']) ? 'success' : 'secondary' ?>"><?= !empty($client['ativo']) ? 'Ativo' : 'Inativo' ?></span></div><div class="d-flex gap-2"><a href="index.php?pagina=clientes" class="btn btn-outline-secondary">Voltar</a><a href="index.php?pagina=editar_cliente&id=<?= (int) $client['id'] ?>" class="btn btn-warning"><i class="bi bi-pencil me-1"></i>Editar</a><?php if (!empty($client['ativo'])): ?><form method="POST" action="index.php" onsubmit="return confirm('Deseja desativar este cliente? Os contratos e registros financeiros serão preservados.');"><input type="hidden" name="_csrf" value="<?= FinPlanWeb::e(FinPlanWeb::csrfToken()) ?>"><input type="hidden" name="_action" value="client_deactivate"><input type="hidden" name="id" value="<?= (int) $client['id'] ?>"><button class="btn btn-outline-danger" type="submit">Desativar</button></form><?php endif; ?></div></div>
<div class="card shadow-sm"><div class="card-body"><div class="row g-4">
    <?php foreach ($fields as $label => $value): ?><div class="col-md-4"><div class="detail-label"><?= FinPlanWeb::e($label) ?></div><div class="detail-value"><?= FinPlanWeb::e($value ?: '—') ?></div></div><?php endforeach; ?>
    <div class="col-12"><div class="detail-label">Observações</div><div class="detail-value text-break"><?= nl2br(FinPlanWeb::e($client['observacoes'] ?: '—')) ?></div></div>
    <div class="col-md-4"><div class="detail-label">Cadastrado em</div><div class="detail-value"><?= FinPlanWeb::e(FinPlanWeb::date($client['criado_em'])) ?></div></div>
</div></div></div>
