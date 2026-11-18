<?php

$router = new Router();

$healthController = new HealthController();
$authController = new AuthController();
$clientController = new ClientController();
$loanController = new LoanController();
$planningController = new PlanningController();
$scenarioController = new ScenarioController();

$tokenTestController = new TokenTestController();
$protectedTestController = new ProtectedTestController();
$financialCalculatorTestController = new FinancialCalculatorTestController();
$financialAnalysisTestController = new FinancialAnalysisTestController();
$planningTestController = new PlanningTestController();
$travelPlanningTestController = new TravelPlanningTestController();
$purchasePlanningTestController = new PurchasePlanningTestController();
$projectPlanningTestController = new ProjectPlanningTestController();
$eventPlanningTestController = new EventPlanningTestController();

$router->post(
    '/api/test/event/budget',
    [$eventPlanningTestController, 'calculateBudget']
);

$router->post(
    '/api/test/project/budget',
    [$projectPlanningTestController, 'calculateBudget']
);

$router->post(
    '/api/test/purchase/budget',
    [$purchasePlanningTestController, 'calculateBudget']
);

$router->post(
    '/api/test/travel/budget',
    [$travelPlanningTestController, 'calculateBudget']
);

$router->get(
    '/api/health',
    [$healthController, 'index']
);

$router->get(
    '/api/clientes',
    [$clientController, 'index']
);

$router->post(
    '/api/clientes',
    [$clientController, 'store']
);

$router->get(
    '/api/clientes/{id}',
    [$clientController, 'show']
);

$router->put(
    '/api/clientes/{id}',
    [$clientController, 'update']
);

$router->patch(
    '/api/clientes/{id}',
    [$clientController, 'update']
);

$router->delete(
    '/api/clientes/{id}',
    [$clientController, 'destroy']
);

$router->post(
    '/api/emprestimos/simular',
    [$loanController, 'simulate']
);

$router->get(
    '/api/emprestimos',
    [$loanController, 'index']
);

$router->post(
    '/api/emprestimos',
    [$loanController, 'store']
);

$router->get(
    '/api/emprestimos/{id}',
    [$loanController, 'show']
);

$router->get(
    '/api/emprestimos/{id}/parcelas',
    [$loanController, 'installments']
);

$router->post(
    '/api/parcelas/{id}/recebimentos',
    [$loanController, 'receive']
);

$router->patch(
    '/api/emprestimos/{id}/cancelar',
    [$loanController, 'cancel']
);

$router->post(
    '/api/auth/register',
    [$authController, 'register']
);

$router->post(
    '/api/auth/login',
    [$authController, 'login']
);

$router->post(
    '/api/auth/logout',
    [$authController, 'logout']
);

$router->get(
    '/api/auth/me',
    [$authController, 'me']
);

$router->post(
    '/api/planning/create',
    [$planningController, 'create']
);

$router->post(
    '/api/scenarios/expense',
    [$scenarioController, 'simulateExpense']
);

$router->post(
    '/api/scenarios/income',
    [$scenarioController, 'simulateIncomeChange']
);

$router->post(
    '/api/scenarios/expense-reduction',
    [$scenarioController, 'simulateExpenseReduction']
);

$router->post(
    '/api/scenarios/goal-deadline',
    [$scenarioController, 'simulateGoalDeadline']
);

$router->get(
    '/api/test/token',
    [$tokenTestController, 'generate']
);

$router->post(
    '/api/test/token/validate',
    [$tokenTestController, 'validate']
);

$router->get(
    '/api/test/protected',
    [$protectedTestController, 'index']
);

$router->post(
    '/api/test/financial/projection',
    [$financialCalculatorTestController, 'projection']
);

$router->post(
    '/api/test/financial/goal',
    [$financialCalculatorTestController, 'goal']
);

$router->post(
    '/api/test/financial/required-contribution',
    [$financialCalculatorTestController, 'requiredContribution']
);

$router->post(
    '/api/test/financial/scenario',
    [$financialCalculatorTestController, 'scenario']
);

$router->post(
    '/api/test/financial/analyze',
    [$financialAnalysisTestController, 'analyze']
);

$router->post(
    '/api/test/financial/goal-capacity',
    [$financialAnalysisTestController, 'goalCapacity']
);

$router->post(
    '/api/test/financial/simulate-expense',
    [$financialAnalysisTestController, 'simulateExpense']
);

$router->post(
    '/api/test/planning',
    [$planningTestController, 'create']
);
