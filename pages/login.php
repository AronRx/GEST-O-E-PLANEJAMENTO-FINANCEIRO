<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar | Nymbrio</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
<div class="login-shell">
    <div class="login-brand"><div class="login-brand-icon"><i class="bi bi-wallet2"></i></div><h1>Nymbrio</h1><p>Gestão e planejamento financeiro</p></div>
    <div class="login-card">
        <h2>Bem-vindo de volta</h2>
        <p class="text-muted mb-4">Entre com sua conta Nymbrio.</p>
        <?php if ($flash): ?>
            <div class="alert alert-<?= FinPlanWeb::e($flash['type']) ?>"><?= FinPlanWeb::e($flash['message']) ?></div>
        <?php endif; ?>
        <form method="POST" action="index.php">
            <input type="hidden" name="_csrf" value="<?= FinPlanWeb::e(FinPlanWeb::csrfToken()) ?>">
            <input type="hidden" name="_action" value="login">
            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" id="email" name="email" class="form-control" autocomplete="username" required autofocus>
            </div>
            <div class="mb-4">
                <label for="senha" class="form-label">Senha</label>
                <input type="password" id="senha" name="senha" class="form-control" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-box-arrow-in-right me-2"></i>Entrar</button>
        </form>
    </div>
    <div class="login-foot">Acesso protegido por autenticação e isolamento de dados por usuário.</div>
</div>
</body>
</html>
