<?php
$mode = (string) ($_GET['modo'] ?? 'login');
$isRegister = $mode === 'register';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $isRegister ? 'Criar conta' : 'Entrar' ?> | Nymbrio</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
<div class="login-shell">
    <div class="login-brand">
        <div class="login-brand-icon"><i class="bi bi-wallet2"></i></div>
        <h1>Nymbrio</h1>
        <p>Gestão e planejamento financeiro</p>
    </div>
    <div class="login-card">
        <?php if ($isRegister): ?>
            <h2>Crie sua conta</h2>
            <p class="text-muted mb-4">Cadastre-se para começar a organizar sua vida financeira.</p>
        <?php else: ?>
            <h2>Bem-vindo de volta</h2>
            <p class="text-muted mb-4">Entre com sua conta Nymbrio.</p>
        <?php endif; ?>

        <?php if ($flash): ?>
            <div class="alert alert-<?= FinPlanWeb::e($flash['type']) ?>"><?= FinPlanWeb::e($flash['message']) ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php">
            <input type="hidden" name="_csrf" value="<?= FinPlanWeb::e(FinPlanWeb::csrfToken()) ?>">
            <input type="hidden" name="_action" value="<?= $isRegister ? 'register' : 'login' ?>">

            <?php if ($isRegister): ?>
                <div class="mb-3">
                    <label for="nome" class="form-label">Nome completo</label>
                    <input type="text" id="nome" name="nome" class="form-control" maxlength="120" autocomplete="name" required autofocus>
                </div>
            <?php endif; ?>

            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" id="email" name="email" class="form-control" maxlength="160" autocomplete="email" required <?= $isRegister ? '' : 'autofocus' ?>>
            </div>

            <div class="<?= $isRegister ? 'mb-3' : 'mb-4' ?>">
                <label for="senha" class="form-label">Senha</label>
                <input type="password" id="senha" name="senha" class="form-control" minlength="8" maxlength="72" autocomplete="<?= $isRegister ? 'new-password' : 'current-password' ?>" required>
                <?php if ($isRegister): ?>
                    <div class="form-text">A senha deve possuir pelo menos 8 caracteres.</div>
                <?php endif; ?>
            </div>

            <?php if ($isRegister): ?>
                <div class="mb-4">
                    <label for="confirmar_senha" class="form-label">Confirmar senha</label>
                    <input type="password" id="confirmar_senha" name="confirmar_senha" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-person-plus me-2"></i>Criar conta</button>
            <?php else: ?>
                <button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-box-arrow-in-right me-2"></i>Entrar</button>
            <?php endif; ?>
        </form>

        <div class="text-center mt-4">
            <?php if ($isRegister): ?>
                <span class="text-muted">Já possui uma conta?</span>
                <a href="index.php?pagina=login" class="fw-semibold text-decoration-none">Entrar</a>
            <?php else: ?>
                <span class="text-muted">Ainda não tem uma conta?</span>
                <a href="index.php?pagina=login&amp;modo=register" class="fw-semibold text-decoration-none">Criar conta</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="login-foot">Acesso protegido por autenticação e isolamento de dados por usuário.</div>
</div>
</body>
</html>
