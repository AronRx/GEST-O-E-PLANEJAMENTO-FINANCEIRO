<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= FinPlanWeb::e($tituloPagina) ?> | Nymbrio</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="principal">
        <div class="topo">
            <div>
                <h1><?= FinPlanWeb::e($tituloPagina) ?></h1>
                <span><?= date('d/m/Y') ?></span>
            </div>
            <div class="topo-direita">
                <i class="bi bi-person-circle"></i>
                <span><?= FinPlanWeb::e($user['nome']) ?></span>
                <form method="POST" class="ms-3">
                    <input type="hidden" name="_csrf" value="<?= FinPlanWeb::e(FinPlanWeb::csrfToken()) ?>">
                    <input type="hidden" name="_action" value="logout">
                    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Sair</button>
                </form>
            </div>
        </div>
        <main class="conteudo">
