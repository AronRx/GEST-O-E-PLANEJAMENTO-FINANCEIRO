<?php
$client = $client ?? [];
$fields = [
    ['nome', 'Nome completo', 'text', 'col-md-6', true],
    ['cpf_cnpj', 'CPF/CNPJ', 'text', 'col-md-3', false],
    ['rg', 'RG', 'text', 'col-md-3', false],
    ['telefone', 'Telefone', 'text', 'col-md-4', false],
    ['whatsapp', 'WhatsApp', 'text', 'col-md-4', false],
    ['email', 'E-mail', 'email', 'col-md-4', false],
    ['cep', 'CEP', 'text', 'col-md-3', false],
    ['rua', 'Rua', 'text', 'col-md-5', false],
    ['numero', 'Número', 'text', 'col-md-2', false],
    ['bairro', 'Bairro', 'text', 'col-md-4', false],
    ['cidade', 'Cidade', 'text', 'col-md-4', false],
    ['estado', 'Estado (UF)', 'text', 'col-md-2', false]
];
?>
<div class="card shadow-sm"><div class="card-body"><div class="row g-3">
    <?php foreach ($fields as [$name, $label, $type, $width, $required]): ?>
        <div class="col-12 <?= FinPlanWeb::e($width) ?>"><label class="form-label" for="<?= FinPlanWeb::e($name) ?>"><?= FinPlanWeb::e($label) ?><?= $required ? ' *' : '' ?></label><input type="<?= FinPlanWeb::e($type) ?>" id="<?= FinPlanWeb::e($name) ?>" name="<?= FinPlanWeb::e($name) ?>" class="form-control" value="<?= FinPlanWeb::e($client[$name] ?? '') ?>" <?= $required ? 'required' : '' ?>></div>
    <?php endforeach; ?>
    <div class="col-12"><label class="form-label" for="observacoes">Observações</label><textarea id="observacoes" name="observacoes" class="form-control" rows="4"><?= FinPlanWeb::e($client['observacoes'] ?? '') ?></textarea></div>
</div></div><div class="card-footer bg-white d-flex justify-content-end gap-2"><a href="index.php?pagina=clientes" class="btn btn-outline-secondary">Cancelar</a><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar Cliente</button></div></div>
