<?php

class FinancialAnalysisTestController
{
    private FinancialAnalysisService $analysisService;

    public function __construct()
    {
        $this->analysisService = new FinancialAnalysisService();
    }

    public function analyze(): void
    {
        try {
            $data = Request::all();

            $monthlyIncome = (float) ($data['receita_mensal'] ?? 0);
            $fixedExpenses = (float) ($data['despesas_fixas'] ?? 0);
            $variableExpenses = (float) ($data['despesas_variaveis'] ?? 0);
            $desiredReserve = (float) ($data['reserva_desejada'] ?? 0);

            $result = $this->analysisService->analyze(
                $monthlyIncome,
                $fixedExpenses,
                $variableExpenses,
                $desiredReserve
            );

            Response::success($result);
        } catch (InvalidArgumentException $exception) {
            Response::error(
                $exception->getMessage(),
                422
            );
        }
    }

    public function goalCapacity(): void
    {
        try {
            $data = Request::all();

            $monthlyIncome = (float) ($data['receita_mensal'] ?? 0);
            $fixedExpenses = (float) ($data['despesas_fixas'] ?? 0);
            $variableExpenses = (float) ($data['despesas_variaveis'] ?? 0);
            $goalAmount = (float) ($data['valor_meta'] ?? 0);
            $months = (int) ($data['meses'] ?? 0);

            $result = $this->analysisService->calculateGoalCapacity(
                $monthlyIncome,
                $fixedExpenses,
                $variableExpenses,
                $goalAmount,
                $months
            );

            Response::success($result);
        } catch (InvalidArgumentException $exception) {
            Response::error(
                $exception->getMessage(),
                422
            );
        }
    }

    public function simulateExpense(): void
    {
        try {
            $data = Request::all();

            $monthlyIncome = (float) ($data['receita_mensal'] ?? 0);
            $fixedExpenses = (float) ($data['despesas_fixas'] ?? 0);
            $variableExpenses = (float) ($data['despesas_variaveis'] ?? 0);
            $newExpense = (float) ($data['nova_despesa'] ?? 0);

            $result = $this->analysisService->simulateExpense(
                $monthlyIncome,
                $fixedExpenses,
                $variableExpenses,
                $newExpense
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
