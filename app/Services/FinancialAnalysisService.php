<?php

class FinancialAnalysisService
{
    public function analyze(
        float $monthlyIncome,
        float $fixedExpenses,
        float $variableExpenses,
        float $desiredReserve = 0
    ): array {
        $this->validateNonNegative(
            $monthlyIncome,
            'A receita mensal não pode ser negativa.'
        );

        $this->validateNonNegative(
            $fixedExpenses,
            'As despesas fixas não podem ser negativas.'
        );

        $this->validateNonNegative(
            $variableExpenses,
            'As despesas variáveis não podem ser negativas.'
        );

        $this->validateNonNegative(
            $desiredReserve,
            'A reserva desejada não pode ser negativa.'
        );

        $totalExpenses = $fixedExpenses + $variableExpenses;

        $monthlyBalance = $monthlyIncome - $totalExpenses;

        $availableForGoals = max(
            0,
            $monthlyBalance - $desiredReserve
        );

        $expensePercentage = $monthlyIncome > 0
            ? ($totalExpenses / $monthlyIncome) * 100
            : 0;

        $savingPercentage = $monthlyIncome > 0
            ? ($availableForGoals / $monthlyIncome) * 100
            : 0;

        return [
            'receita_mensal' => $this->roundMoney($monthlyIncome),

            'despesas_fixas' => $this->roundMoney($fixedExpenses),

            'despesas_variaveis' => $this->roundMoney(
                $variableExpenses
            ),

            'despesas_totais' => $this->roundMoney(
                $totalExpenses
            ),

            'saldo_mensal' => $this->roundMoney(
                $monthlyBalance
            ),

            'reserva_desejada' => $this->roundMoney(
                $desiredReserve
            ),

            'disponivel_para_metas' => $this->roundMoney(
                $availableForGoals
            ),

            'percentual_comprometido' => $this->roundPercentage(
                $expensePercentage
            ),

            'percentual_disponivel' => $this->roundPercentage(
                $savingPercentage
            ),

            'saldo_negativo' => $monthlyBalance < 0
        ];
    }

    public function calculateGoalCapacity(
        float $monthlyIncome,
        float $fixedExpenses,
        float $variableExpenses,
        float $goalAmount,
        int $months
    ): array {
        $analysis = $this->analyze(
            $monthlyIncome,
            $fixedExpenses,
            $variableExpenses
        );

        $this->validateNonNegative(
            $goalAmount,
            'O valor da meta não pode ser negativo.'
        );

        if ($months <= 0) {
            throw new InvalidArgumentException(
                'A quantidade de meses deve ser maior que zero.'
            );
        }

        $availableMonthly = $analysis['disponivel_para_metas'];

        $possibleContribution = $availableMonthly * $months;

        $goalReached = $possibleContribution >= $goalAmount;

        $requiredMonthly = $goalAmount / $months;

        return [
            'analise' => $analysis,

            'valor_meta' => $this->roundMoney(
                $goalAmount
            ),

            'prazo_meses' => $months,

            'capacidade_mensal' => $this->roundMoney(
                $availableMonthly
            ),

            'capacidade_total' => $this->roundMoney(
                $possibleContribution
            ),

            'aporte_mensal_necessario' => $this->roundMoney(
                $requiredMonthly
            ),

            'meta_possivel' => $goalReached
        ];
    }

    public function simulateExpense(
        float $monthlyIncome,
        float $fixedExpenses,
        float $variableExpenses,
        float $newExpense
    ): array {
        $this->validateNonNegative(
            $newExpense,
            'A nova despesa não pode ser negativa.'
        );

        $current = $this->analyze(
            $monthlyIncome,
            $fixedExpenses,
            $variableExpenses
        );

        $newTotalExpenses =
            $current['despesas_totais'] +
            $newExpense;

        $newBalance =
            $monthlyIncome -
            $newTotalExpenses;

        $currentAvailable =
            $current['disponivel_para_metas'];

        $newAvailable = max(
            0,
            $newBalance
        );

        return [
            'situacao_atual' => $current,

            'nova_despesa' => $this->roundMoney(
                $newExpense
            ),

            'novas_despesas_totais' => $this->roundMoney(
                $newTotalExpenses
            ),

            'novo_saldo_mensal' => $this->roundMoney(
                $newBalance
            ),

            'disponivel_atual' => $this->roundMoney(
                $currentAvailable
            ),

            'novo_disponivel' => $this->roundMoney(
                $newAvailable
            ),

            'impacto_mensal' => $this->roundMoney(
                $newAvailable - $currentAvailable
            ),

            'gera_saldo_negativo' => $newBalance < 0
        ];
    }

    private function validateNonNegative(
        float $value,
        string $message
    ): void {
        if ($value < 0) {
            throw new InvalidArgumentException($message);
        }
    }

    private function roundMoney(float $value): float
    {
        return round($value, 2);
    }

    private function roundPercentage(float $value): float
    {
        return round($value, 2);
    }
}
