<?php

class ScenarioController
{
    private ScenarioService $scenarioService;

    public function __construct()
    {
        $this->scenarioService = new ScenarioService();
    }

    public function simulateExpense(): void
    {
        try {
            $data = Request::all();

            $result = $this->scenarioService->simulateExpense(
                (float) ($data['receita_mensal'] ?? 0),
                (float) ($data['despesas_fixas'] ?? 0),
                (float) ($data['despesas_variaveis'] ?? 0),
                (float) ($data['nova_despesa'] ?? 0)
            );

            Response::success($result);
        } catch (InvalidArgumentException $exception) {
            Response::error(
                $exception->getMessage(),
                422
            );
        }
    }

    public function simulateIncomeChange(): void
    {
        try {
            $data = Request::all();

            $result = $this->scenarioService->simulateIncomeChange(
                (float) ($data['receita_mensal'] ?? 0),
                (float) ($data['despesas_fixas'] ?? 0),
                (float) ($data['despesas_variaveis'] ?? 0),
                (float) ($data['alteracao_receita'] ?? 0)
            );

            Response::success($result);
        } catch (InvalidArgumentException $exception) {
            Response::error(
                $exception->getMessage(),
                422
            );
        }
    }

    public function simulateExpenseReduction(): void
    {
        try {
            $data = Request::all();

            $result = $this->scenarioService->simulateExpenseReduction(
                (float) ($data['receita_mensal'] ?? 0),
                (float) ($data['despesas_fixas'] ?? 0),
                (float) ($data['despesas_variaveis'] ?? 0),
                (float) ($data['reducao_despesas'] ?? 0)
            );

            Response::success($result);
        } catch (InvalidArgumentException $exception) {
            Response::error(
                $exception->getMessage(),
                422
            );
        }
    }

    public function simulateGoalDeadline(): void
    {
        try {
            $data = Request::all();

            $result = $this->scenarioService->simulateGoalDeadline(
                (float) ($data['valor_atual'] ?? 0),
                (float) ($data['valor_meta'] ?? 0),
                (float) ($data['receita_mensal'] ?? 0),
                (float) ($data['despesas_fixas'] ?? 0),
                (float) ($data['despesas_variaveis'] ?? 0),
                (int) ($data['meses'] ?? 0)
            );

            Response::success($result);
        } catch (InvalidArgumentException $exception) {
            Response::error(
                $exception->getMessage(),
                422
            );
        }
    }
}
