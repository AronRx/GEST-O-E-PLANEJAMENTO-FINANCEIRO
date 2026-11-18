<?php

class FinancialCalculatorService
{
    public function calculateProjection(
        float $currentAmount,
        float $monthlyContribution,
        int $months
    ): array {
        $this->validateNonNegative(
            $currentAmount,
            'O valor atual não pode ser negativo.'
        );

        $this->validateNonNegative(
            $monthlyContribution,
            'A contribuição mensal não pode ser negativa.'
        );

        if ($months < 0) {
            throw new InvalidArgumentException(
                'A quantidade de meses não pode ser negativa.'
            );
        }

        $totalContributions = $monthlyContribution * $months;

        $projectedAmount = $currentAmount + $totalContributions;

        return [
            'valor_atual' => $this->roundMoney($currentAmount),
            'aporte_mensal' => $this->roundMoney($monthlyContribution),
            'meses' => $months,
            'total_aportes' => $this->roundMoney($totalContributions),
            'valor_projetado' => $this->roundMoney($projectedAmount)
        ];
    }

    public function calculateGoal(
        float $currentAmount,
        float $goalAmount,
        float $monthlyContribution
    ): array {
        $this->validateNonNegative(
            $currentAmount,
            'O valor atual não pode ser negativo.'
        );

        $this->validateNonNegative(
            $goalAmount,
            'O valor da meta não pode ser negativo.'
        );

        $this->validateNonNegative(
            $monthlyContribution,
            'A contribuição mensal não pode ser negativa.'
        );

        $remainingAmount = max(
            0,
            $goalAmount - $currentAmount
        );

        if ($remainingAmount === 0.0) {
            return [
                'valor_atual' => $this->roundMoney($currentAmount),
                'valor_meta' => $this->roundMoney($goalAmount),
                'valor_faltante' => 0,
                'aporte_mensal' => $this->roundMoney($monthlyContribution),
                'meses_necessarios' => 0,
                'meta_atingida' => true
            ];
        }

        if ($monthlyContribution <= 0) {
            return [
                'valor_atual' => $this->roundMoney($currentAmount),
                'valor_meta' => $this->roundMoney($goalAmount),
                'valor_faltante' => $this->roundMoney($remainingAmount),
                'aporte_mensal' => 0,
                'meses_necessarios' => null,
                'meta_atingida' => false
            ];
        }

        $months = (int) ceil(
            $remainingAmount / $monthlyContribution
        );

        return [
            'valor_atual' => $this->roundMoney($currentAmount),
            'valor_meta' => $this->roundMoney($goalAmount),
            'valor_faltante' => $this->roundMoney($remainingAmount),
            'aporte_mensal' => $this->roundMoney($monthlyContribution),
            'meses_necessarios' => $months,
            'meta_atingida' => false
        ];
    }

    public function calculateRequiredMonthlyContribution(
        float $currentAmount,
        float $goalAmount,
        int $months
    ): array {
        $this->validateNonNegative(
            $currentAmount,
            'O valor atual não pode ser negativo.'
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

        $remainingAmount = max(
            0,
            $goalAmount - $currentAmount
        );

        $monthlyContribution = $remainingAmount / $months;

        return [
            'valor_atual' => $this->roundMoney($currentAmount),
            'valor_meta' => $this->roundMoney($goalAmount),
            'valor_faltante' => $this->roundMoney($remainingAmount),
            'meses' => $months,
            'aporte_mensal_necessario' => $this->roundMoney(
                $monthlyContribution
            ),
            'meta_atingida' => $remainingAmount === 0.0
        ];
    }

    public function calculateScenario(
        float $currentAmount,
        float $monthlyContribution,
        int $months,
        float $extraExpense = 0
    ): array {
        $this->validateNonNegative(
            $currentAmount,
            'O valor atual não pode ser negativo.'
        );

        $this->validateNonNegative(
            $monthlyContribution,
            'A contribuição mensal não pode ser negativa.'
        );

        $this->validateNonNegative(
            $extraExpense,
            'A despesa adicional não pode ser negativa.'
        );

        if ($months < 0) {
            throw new InvalidArgumentException(
                'A quantidade de meses não pode ser negativa.'
            );
        }

        $projectedBeforeExpense =
            $currentAmount +
            ($monthlyContribution * $months);

        $projectedAfterExpense = max(
            0,
            $projectedBeforeExpense - $extraExpense
        );

        return [
            'valor_atual' => $this->roundMoney($currentAmount),
            'aporte_mensal' => $this->roundMoney($monthlyContribution),
            'meses' => $months,
            'despesa_adicional' => $this->roundMoney($extraExpense),
            'projecao_sem_despesa' => $this->roundMoney(
                $projectedBeforeExpense
            ),
            'projecao_com_despesa' => $this->roundMoney(
                $projectedAfterExpense
            ),
            'impacto_despesa' => $this->roundMoney(
                min($extraExpense, $projectedBeforeExpense)
            )
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
}
