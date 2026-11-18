<?php

class ScenarioService
{
    private FinancialAnalysisService $analysisService;

    private FinancialCalculatorService $calculator;

    public function __construct()
    {
        $this->analysisService = new FinancialAnalysisService();

        $this->calculator = new FinancialCalculatorService();
    }

    public function simulateExpense(
        float $monthlyIncome,
        float $fixedExpenses,
        float $variableExpenses,
        float $newExpense
    ): array {
        $current = $this->analysisService->analyze(
            $monthlyIncome,
            $fixedExpenses,
            $variableExpenses
        );

        $newVariableExpenses =
            $variableExpenses + $newExpense;

        $scenario = $this->analysisService->analyze(
            $monthlyIncome,
            $fixedExpenses,
            $newVariableExpenses
        );

        return [
            'tipo' => 'nova_despesa',

            'situacao_atual' => $current,

            'cenario' => $scenario,

            'impacto' => [
                'nova_despesa' => $this->roundMoney(
                    $newExpense
                ),

                'reducao_do_saldo' => $this->roundMoney(
                    $current['saldo_mensal'] -
                    $scenario['saldo_mensal']
                ),

                'reducao_da_capacidade' => $this->roundMoney(
                    $current['disponivel_para_metas'] -
                    $scenario['disponivel_para_metas']
                ),

                'gera_saldo_negativo' =>
                    $scenario['saldo_mensal'] < 0
            ]
        ];
    }

    public function simulateIncomeChange(
        float $monthlyIncome,
        float $fixedExpenses,
        float $variableExpenses,
        float $incomeChange
    ): array {
        $current = $this->analysisService->analyze(
            $monthlyIncome,
            $fixedExpenses,
            $variableExpenses
        );

        $newIncome =
            $monthlyIncome + $incomeChange;

        if ($newIncome < 0) {
            throw new InvalidArgumentException(
                'A nova receita mensal não pode ser negativa.'
            );
        }

        $scenario = $this->analysisService->analyze(
            $newIncome,
            $fixedExpenses,
            $variableExpenses
        );

        return [
            'tipo' => 'alteracao_receita',

            'situacao_atual' => $current,

            'cenario' => $scenario,

            'impacto' => [
                'alteracao_receita' => $this->roundMoney(
                    $incomeChange
                ),

                'novo_valor_receita' => $this->roundMoney(
                    $newIncome
                ),

                'aumento_do_saldo' => $this->roundMoney(
                    $scenario['saldo_mensal'] -
                    $current['saldo_mensal']
                ),

                'aumento_da_capacidade' => $this->roundMoney(
                    $scenario['disponivel_para_metas'] -
                    $current['disponivel_para_metas']
                )
            ]
        ];
    }

    public function simulateExpenseReduction(
        float $monthlyIncome,
        float $fixedExpenses,
        float $variableExpenses,
        float $expenseReduction
    ): array {
        if ($expenseReduction < 0) {
            throw new InvalidArgumentException(
                'A redução de despesas não pode ser negativa.'
            );
        }

        if ($expenseReduction > $variableExpenses) {
            throw new InvalidArgumentException(
                'A redução não pode ser maior que as despesas variáveis atuais.'
            );
        }

        $current = $this->analysisService->analyze(
            $monthlyIncome,
            $fixedExpenses,
            $variableExpenses
        );

        $newVariableExpenses =
            $variableExpenses - $expenseReduction;

        $scenario = $this->analysisService->analyze(
            $monthlyIncome,
            $fixedExpenses,
            $newVariableExpenses
        );

        return [
            'tipo' => 'reducao_despesas',

            'situacao_atual' => $current,

            'cenario' => $scenario,

            'impacto' => [
                'reducao_despesas' => $this->roundMoney(
                    $expenseReduction
                ),

                'aumento_do_saldo' => $this->roundMoney(
                    $scenario['saldo_mensal'] -
                    $current['saldo_mensal']
                ),

                'aumento_da_capacidade' => $this->roundMoney(
                    $scenario['disponivel_para_metas'] -
                    $current['disponivel_para_metas']
                )
            ]
        ];
    }

    public function simulateGoalDeadline(
        float $currentAmount,
        float $goalAmount,
        float $monthlyIncome,
        float $fixedExpenses,
        float $variableExpenses,
        int $months
    ): array {
        if ($months <= 0) {
            throw new InvalidArgumentException(
                'O prazo deve ser maior que zero.'
            );
        }

        $analysis = $this->analysisService->analyze(
            $monthlyIncome,
            $fixedExpenses,
            $variableExpenses
        );

        $required =
            $this->calculator->calculateRequiredMonthlyContribution(
                $currentAmount,
                $goalAmount,
                $months
            );

        $capacity =
            $analysis['disponivel_para_metas'];

        $difference =
            $capacity -
            $required['aporte_mensal_necessario'];

        return [
            'tipo' => 'alteracao_prazo',

            'situacao_financeira' => $analysis,

            'meta' => [
                'valor' => $this->roundMoney($goalAmount),

                'valor_atual' => $this->roundMoney(
                    $currentAmount
                ),

                'valor_faltante' =>
                    $required['valor_faltante']
            ],

            'prazo' => [
                'meses' => $months,

                'aporte_necessario' =>
                    $required[
                        'aporte_mensal_necessario'
                    ],

                'capacidade_mensal' =>
                    $this->roundMoney($capacity),

                'diferenca_mensal' =>
                    $this->roundMoney($difference),

                'possivel' =>
                    $capacity >=
                    $required[
                        'aporte_mensal_necessario'
                    ]
            ]
        ];
    }

    private function roundMoney(float $value): float
    {
        return round($value, 2);
    }
}
