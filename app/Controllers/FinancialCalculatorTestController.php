<?php

class FinancialCalculatorTestController
{
    private FinancialCalculatorService $calculator;

    public function __construct()
    {
        $this->calculator = new FinancialCalculatorService();
    }

    public function projection(): void
    {
        try {
            $data = Request::all();

            $currentAmount = (float) ($data['valor_atual'] ?? 0);
            $monthlyContribution = (float) ($data['aporte_mensal'] ?? 0);
            $months = (int) ($data['meses'] ?? 0);

            $result = $this->calculator->calculateProjection(
                $currentAmount,
                $monthlyContribution,
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

    public function goal(): void
    {
        try {
            $data = Request::all();

            $currentAmount = (float) ($data['valor_atual'] ?? 0);
            $goalAmount = (float) ($data['valor_meta'] ?? 0);
            $monthlyContribution = (float) ($data['aporte_mensal'] ?? 0);

            $result = $this->calculator->calculateGoal(
                $currentAmount,
                $goalAmount,
                $monthlyContribution
            );

            Response::success($result);
        } catch (InvalidArgumentException $exception) {
            Response::error(
                $exception->getMessage(),
                422
            );
        }
    }

    public function requiredContribution(): void
    {
        try {
            $data = Request::all();

            $currentAmount = (float) ($data['valor_atual'] ?? 0);
            $goalAmount = (float) ($data['valor_meta'] ?? 0);
            $months = (int) ($data['meses'] ?? 0);

            $result = $this->calculator
                ->calculateRequiredMonthlyContribution(
                    $currentAmount,
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

    public function scenario(): void
    {
        try {
            $data = Request::all();

            $currentAmount = (float) ($data['valor_atual'] ?? 0);
            $monthlyContribution = (float) ($data['aporte_mensal'] ?? 0);
            $months = (int) ($data['meses'] ?? 0);
            $extraExpense = (float) ($data['despesa_extra'] ?? 0);

            $result = $this->calculator->calculateScenario(
                $currentAmount,
                $monthlyContribution,
                $months,
                $extraExpense
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
