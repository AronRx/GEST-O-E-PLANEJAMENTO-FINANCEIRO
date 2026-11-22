<?php
$clientResult = (new ClientService())->paginate($user['id'], ['page' => 1, 'per_page' => 100]);
$clients = $clientResult['items'];
$today = date('Y-m-d');
$firstDue = (new DateTimeImmutable('today +1 month'))->format('Y-m-d');
?>
<div class="d-flex justify-content-between align-items-center mb-4"><div><h2 class="mb-1">Novo Empréstimo</h2><p class="text-muted mb-0">O cronograma será calculado pelo PostgreSQL usando a Tabela Price.</p></div><a href="index.php?pagina=emprestimos" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Voltar</a></div>
<?php if ($clients === []): ?><div class="alert alert-warning">Você precisa cadastrar um cliente ativo antes de criar um empréstimo. <a href="index.php?pagina=novo_cliente" class="alert-link">Cadastrar cliente</a>.</div><?php else: ?>
<form method="POST" action="index.php"><input type="hidden" name="_csrf" value="<?= FinPlanWeb::e(FinPlanWeb::csrfToken()) ?>"><input type="hidden" name="_action" value="loan_create">
<div class="card shadow-sm"><div class="card-body"><div class="row g-3">
    <div class="col-md-6"><label class="form-label" for="cliente_id">Cliente *</label><select class="form-select" id="cliente_id" name="cliente_id" required><option value="">Selecione um cliente</option><?php foreach ($clients as $client): ?><option value="<?= FinPlanWeb::e($client['id']) ?>"><?= FinPlanWeb::e($client['nome']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label" for="valor_principal">Valor emprestado (R$) *</label><input class="form-control" type="number" id="valor_principal" name="valor_principal" min="0.01" step="0.01" required></div>
    <div class="col-md-3"><label class="form-label" for="taxa_juros_mensal">Juros ao mês (%) *</label><input class="form-control" type="number" id="taxa_juros_mensal" name="taxa_juros_mensal" min="0" step="0.0001" value="0" required></div>
    <div class="col-md-3"><label class="form-label" for="qtd_parcelas">Quantidade de parcelas *</label><input class="form-control" type="number" id="qtd_parcelas" name="qtd_parcelas" min="1" max="600" step="1" required></div>
    <div class="col-md-3"><label class="form-label" for="data_emprestimo">Data do empréstimo *</label><input class="form-control" type="date" id="data_emprestimo" name="data_emprestimo" value="<?= FinPlanWeb::e($today) ?>" required></div>
    <div class="col-md-3"><label class="form-label" for="primeiro_vencimento">Primeiro vencimento *</label><input class="form-control" type="date" id="primeiro_vencimento" name="primeiro_vencimento" value="<?= FinPlanWeb::e($firstDue) ?>" required></div>
    <div class="col-md-3"><label class="form-label">Sistema de amortização</label><input class="form-control" value="Tabela Price" readonly><input type="hidden" name="sistema_amortizacao" value="price"></div>
    <div class="col-12"><label class="form-label" for="observacoes">Observações</label><textarea class="form-control" id="observacoes" name="observacoes" rows="3" maxlength="5000"></textarea></div>
</div></div><div class="card-footer bg-white d-flex justify-content-end gap-2"><a href="index.php?pagina=emprestimos" class="btn btn-outline-secondary">Cancelar</a><button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Criar Empréstimo</button></div></div>
</form>
<div class="alert alert-info mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>O contrato e as parcelas serão gravados juntos. A última parcela pode receber ajuste de centavos para zerar exatamente o saldo devedor.</div>
<?php endif; ?>
