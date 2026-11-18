<?php

class PlanningService
{
    private FinancialCalculatorService $calculator;

    private FinancialAnalysisService $analysisService;

    private TravelPlanningService $travelPlanningService;

    private PurchasePlanningService $purchasePlanningService;

    private ProjectPlanningService $projectPlanningService;

    private EventPlanningService $eventPlanningService;

    public function __construct()
    {
        $this->calculator = new FinancialCalculatorService();

        $this->analysisService = new FinancialAnalysisService();

        $this->travelPlanningService =
            new TravelPlanningService();

        $this->purchasePlanningService =
            new PurchasePlanningService();

        $this->projectPlanningService =
            new ProjectPlanningService();

        $this->eventPlanningService =
            new EventPlanningService();
    }

    public function createPlan(array $data): array
    {
        $name = trim((string) $data['nome']);

        $type = PlanningType::normalize(
            (string) $data['tipo']
        );

        $details = $data['detalhes'] ?? null;

        $goalAmount = $this->resolveGoalAmount(
            $type,
            $details,
            $data
        );

        $currentAmount = (float) $data['valor_atual'];

        $months = (int) $data['prazo_meses'];

        $monthlyIncome = (float) $data['receita_mensal'];

        $fixedExpenses = (float) $data['despesas_fixas'];

        $variableExpenses = (float) $data['despesas_variaveis'];

        $analysis = $this->analysisService->analyze(
            $monthlyIncome,
            $fixedExpenses,
            $variableExpenses
        );

        $requiredContribution =
            $this->calculator->calculateRequiredMonthlyContribution(
                $currentAmount,
                $goalAmount,
                $months
            );

        $requiredMonthly =
            $requiredContribution['aporte_mensal_necessario'];

        $monthlyCapacity =
            $analysis['disponivel_para_metas'];

        $remainingAmount =
            $requiredContribution['valor_faltante'];

        $monthlyMargin = round(
            $monthlyCapacity - $requiredMonthly,
            2
        );

        $monthlyDeficit = round(
            max(
                0,
                $requiredMonthly - $monthlyCapacity
            ),
            2
        );

        $financialDeficit = round(
            max(
                0,
                -$analysis['saldo_mensal']
            ),
            2
        );

        $feasible =
            $monthlyCapacity >= $requiredMonthly;

        $projection = $this->calculator->calculateProjection(
            $currentAmount,
            $monthlyCapacity,
            $months
        );

        $capacityTotal = round(
            $monthlyCapacity * $months,
            2
        );

        return [
            'nome' => $name,

            'tipo' => $type,

            'detalhes' => $details,

            'objetivo' => [
                'valor' => $this->roundMoney(
                    $goalAmount
                ),

                'valor_atual' => $this->roundMoney(
                    $currentAmount
                ),

                'valor_faltante' => $this->roundMoney(
                    $remainingAmount
                )
            ],

            'situacao_financeira' => $analysis,

            'planejamento' => [
                'prazo_meses' => $months,

                'aporte_mensal_necessario' =>
                    $this->roundMoney(
                        $requiredMonthly
                    ),

                'capacidade_mensal' =>
                    $this->roundMoney(
                        $monthlyCapacity
                    ),

                'margem_mensal' =>
                    $monthlyMargin,

                'deficit_mensal_para_meta' =>
                    $monthlyDeficit,

                'deficit_financeiro_atual' =>
                    $financialDeficit,

                'capacidade_total' =>
                    $this->roundMoney(
                        $capacityTotal
                    )
            ],

            'projecao' => $projection,

            'viabilidade' => [
                'possivel' => $feasible,

                'motivo' =>
                    $this->getFeasibilityMessage(
                        $feasible,
                        $monthlyCapacity,
                        $requiredMonthly,
                        $analysis['saldo_mensal']
                    )
            ]
        ];
    }

    private function resolveGoalAmount(
        string $type,
        mixed $details,
        array $data
    ): float {
        if ($type === PlanningType::VIAGEM) {
            if (!is_array($details)) {
                throw new InvalidArgumentException(
                    'Os detalhes da viagem são obrigatórios.'
                );
            }

            $travelBudget =
                $this->travelPlanningService->calculateBudget(
                    $details
                );

            return (float) $travelBudget['orcamento']['total'];
        }

        if ($type === PlanningType::COMPRA) {
            if (!is_array($details)) {
                throw new InvalidArgumentException(
                    'Os detalhes da compra são obrigatórios.'
                );
            }

            $purchaseBudget =
                $this->purchasePlanningService->calculateBudget(
                    $details
                );

            return (float) $purchaseBudget['orcamento']['total'];
        }

        if ($type === PlanningType::PROJETO) {
            if (!is_array($details)) {
                throw new InvalidArgumentException(
                    'Os detalhes do projeto são obrigatórios.'
                );
            }

            $projectBudget =
                $this->projectPlanningService->calculateBudget(
                    $details
                );

            return (float) $projectBudget['orcamento']['total'];
        }

        if ($type === PlanningType::EVENTO) {
            if (!is_array($details)) {
                throw new InvalidArgumentException(
                    'Os detalhes do evento são obrigatórios.'
                );
            }

            $eventBudget =
                $this->eventPlanningService->calculateBudget(
                    $details
                );

            return (float) $eventBudget['orcamento']['total'];
        }

        return (float) $data['valor_objetivo'];
    }

    private function getFeasibilityMessage(
        bool $feasible,
        float $monthlyCapacity,
        float $requiredMonthly,
        float $monthlyBalance
    ): string {
        if ($monthlyBalance < 0) {
            return sprintf(
                'O orçamento apresenta déficit de R$ %.2f por mês. A meta exige um aporte de R$ %.2f mensais, mas não existe capacidade atual para realizá-lo.',
                abs($monthlyBalance),
                $requiredMonthly
            );
        }

        if ($feasible) {
            return sprintf(
                'A meta é compatível com a capacidade financeira informada. O aporte necessário é de R$ %.2f por mês, com capacidade estimada de R$ %.2f.',
                $requiredMonthly,
                $monthlyCapacity
            );
        }

        return sprintf(
            'A meta exige R$ %.2f por mês, mas a capacidade estimada é de R$ %.2f. É necessário aumentar a capacidade mensal em R$ %.2f ou rever o prazo.',
            $requiredMonthly,
            $monthlyCapacity,
            $requiredMonthly - $monthlyCapacity
        );
    }

    private function roundMoney(float $value): float
    {
        return round(
            $value,
            2
        );
    }
}
