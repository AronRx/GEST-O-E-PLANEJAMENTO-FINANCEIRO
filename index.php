<?php

require_once __DIR__ . '/config/web.php';

function finplanRedirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function finplanPostData(): array
{
    $data = $_POST;
    unset($data['_csrf'], $data['_action']);
    return $data;
}

function finplanRequestId(mixed $value, string $message): int|string
{
    $id = FinPlanId::tryNormalize($value);
    if ($id === null) {
        throw new InvalidArgumentException($message);
    }
    return $id;
}

function finplanRedirectId(mixed $value): string
{
    $id = FinPlanId::tryNormalize($value);
    return $id === null ? '' : rawurlencode((string) $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['_action'] ?? '');
    try {
        FinPlanWeb::verifyCsrf();

        if ($action === 'login') {
            $result = (new AuthService())->login([
                'email' => $_POST['email'] ?? '',
                'senha' => $_POST['senha'] ?? ''
            ]);
            session_regenerate_id(true);
            $_SESSION['finplan_web_token'] = $result['access_token'];
            $_SESSION['finplan_web_user'] = $result['usuario'];
            $_SESSION['finplan_csrf'] = bin2hex(random_bytes(32));
            FinPlanWeb::flash('success', 'Bem-vindo ao Nymbrio. Login realizado com sucesso.');
            finplanRedirect('index.php');
        }

        if ($action === 'logout') {
            unset($_SESSION['finplan_web_token'], $_SESSION['finplan_web_user']);
            session_regenerate_id(true);
            $_SESSION['finplan_csrf'] = bin2hex(random_bytes(32));
            FinPlanWeb::flash('success', 'Você saiu do sistema com segurança.');
            finplanRedirect('index.php?pagina=login');
        }

        $user = FinPlanWeb::currentUser();
        if (!$user) {
            FinPlanWeb::flash('danger', 'Sua sessão expirou. Entre novamente.');
            finplanRedirect('index.php?pagina=login');
        }

        $userId = $user['id'];
        $data = finplanPostData();

        switch ($action) {
            case 'client_create':
                $client = (new ClientService())->create($userId, $data);
                FinPlanWeb::flash('success', 'Cliente cadastrado com sucesso.');
                finplanRedirect('index.php?pagina=visualizar_cliente&id=' . rawurlencode((string) FinPlanId::normalize($client['id'])));

            case 'client_update':
                $clientId = finplanRequestId($_POST['id'] ?? null, 'Identificador do cliente inválido.');
                $client = (new ClientService())->update($userId, $clientId, $data);
                FinPlanWeb::flash('success', 'Dados do cliente atualizados com sucesso.');
                finplanRedirect('index.php?pagina=visualizar_cliente&id=' . rawurlencode((string) FinPlanId::normalize($client['id'])));

            case 'client_deactivate':
                $clientId = finplanRequestId($_POST['id'] ?? null, 'Identificador do cliente inválido.');
                (new ClientService())->deactivate($userId, $clientId);
                FinPlanWeb::flash('success', 'Cliente desativado. Os registros financeiros foram preservados.');
                finplanRedirect('index.php?pagina=clientes');

            case 'loan_create':
                $loan = (new LoanService())->create($userId, $data);
                FinPlanWeb::flash('success', 'Empréstimo criado e cronograma de parcelas gerado com sucesso.');
                finplanRedirect('index.php?pagina=visualizar_emprestimo&id=' . rawurlencode((string) FinPlanId::normalize($loan['id'])));

            case 'receipt_create':
                $installmentId = finplanRequestId($_POST['parcela_id'] ?? null, 'Identificador da parcela inválido.');
                (new LoanService())->registerReceipt($userId, $installmentId, $data);
                $installment = FinPlanWeb::installment($userId, $installmentId);
                $installmentLoanId = FinPlanId::tryNormalize($installment['emprestimo_id'] ?? null);
                if ($installmentLoanId === null) {
                    throw new RuntimeException('Não foi possível localizar o contrato da parcela recebida.');
                }
                FinPlanWeb::flash('success', 'Recebimento registrado com sucesso.');
                finplanRedirect('index.php?pagina=visualizar_emprestimo&id=' . rawurlencode((string) $installmentLoanId));

            case 'loan_cancel':
                $loanId = finplanRequestId($_POST['id'] ?? null, 'Identificador do empréstimo inválido.');
                (new LoanService())->cancel($userId, $loanId);
                FinPlanWeb::flash('success', 'Empréstimo cancelado. O contrato e as parcelas foram preservados.');
                finplanRedirect('index.php?pagina=visualizar_emprestimo&id=' . rawurlencode((string) $loanId));

            default:
                throw new InvalidArgumentException('Operação não reconhecida.');
        }
    } catch (Throwable $exception) {
        error_log('[Nymbrio Web] ' . $exception->getMessage());
        $message = $exception->getMessage();
        if ($exception instanceof UnexpectedValueException) {
            $message = $exception->getMessage();
        } elseif ($exception instanceof PDOException || $exception instanceof RuntimeException && !($exception instanceof InvalidArgumentException)) {
            $debug = filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN);
            $message = $debug ? $exception->getMessage() : 'Não foi possível concluir a operação. Confira os dados e tente novamente.';
        }
        FinPlanWeb::flash('danger', $message);
        $back = match ($action) {
            'client_create' => 'index.php?pagina=novo_cliente',
            'client_update' => 'index.php?pagina=editar_cliente&id=' . finplanRedirectId($_POST['id'] ?? null),
            'loan_create' => 'index.php?pagina=novo_emprestimo',
            'receipt_create' => 'index.php?pagina=receber_parcela&id=' . finplanRedirectId($_POST['parcela_id'] ?? null),
            'loan_cancel' => 'index.php?pagina=visualizar_emprestimo&id=' . finplanRedirectId($_POST['id'] ?? null),
            default => 'index.php?pagina=login'
        };
        finplanRedirect($back);
    }
}

$user = FinPlanWeb::currentUser();
$pagina = (string) ($_GET['pagina'] ?? 'dashboard');

if (!$user) {
    $pagina = 'login';
} elseif ($pagina === 'login') {
    finplanRedirect('index.php');
}

if ($pagina === 'login') {
    $flash = FinPlanWeb::pullFlash();
    include __DIR__ . '/pages/login.php';
    exit;
}

$pages = [
    'dashboard' => 'dashboard.php',
    'clientes' => 'clientes.php',
    'novo_cliente' => 'novo_cliente.php',
    'editar_cliente' => 'editar_cliente.php',
    'visualizar_cliente' => 'visualizar_cliente.php',
    'emprestimos' => 'emprestimos.php',
    'novo_emprestimo' => 'novo_emprestimo.php',
    'visualizar_emprestimo' => 'visualizar_emprestimo.php',
    'parcelas' => 'parcelas.php',
    'receber_parcela' => 'receber_parcela.php',
    'relatorios' => 'relatorios.php'
];

if (!isset($pages[$pagina])) {
    http_response_code(404);
    $pagina = 'dashboard';
    FinPlanWeb::flash('warning', 'A página solicitada não foi encontrada.');
}

$flash = FinPlanWeb::pullFlash();
$tituloPagina = [
    'dashboard' => 'Dashboard',
    'clientes' => 'Clientes',
    'novo_cliente' => 'Novo Cliente',
    'editar_cliente' => 'Editar Cliente',
    'visualizar_cliente' => 'Cliente',
    'emprestimos' => 'Empréstimos',
    'novo_emprestimo' => 'Novo Empréstimo',
    'visualizar_emprestimo' => 'Empréstimo',
    'parcelas' => 'Parcelas',
    'receber_parcela' => 'Receber Parcela',
    'relatorios' => 'Relatórios'
][$pagina];

include __DIR__ . '/includes/layout_top.php';
if ($flash) {
    echo '<div class="alert alert-' . FinPlanWeb::e($flash['type']) . ' alert-dismissible fade show" role="alert">' . FinPlanWeb::e($flash['message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button></div>';
}
include __DIR__ . '/pages/' . $pages[$pagina];
include __DIR__ . '/includes/layout_bottom.php';
