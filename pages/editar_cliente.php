<?php
$clientId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($clientId === false) {
    echo '<div class="alert alert-danger">Identificador de cliente inválido.</div>';
    return;
}
$client = (new ClientService())->find((int) $user['id'], (int) $clientId);
?>
<div class="d-flex justify-content-between align-items-center mb-4"><div><h2 class="mb-1">Editar Cliente</h2><p class="text-muted mb-0">Atualize as informações cadastrais.</p></div><a href="index.php?pagina=visualizar_cliente&id=<?= (int) $client['id'] ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Voltar</a></div>
<form method="POST" action="index.php"><input type="hidden" name="_csrf" value="<?= FinPlanWeb::e(FinPlanWeb::csrfToken()) ?>"><input type="hidden" name="_action" value="client_update"><input type="hidden" name="id" value="<?= (int) $client['id'] ?>"><?php include __DIR__ . '/_form_cliente.php'; ?></form>
