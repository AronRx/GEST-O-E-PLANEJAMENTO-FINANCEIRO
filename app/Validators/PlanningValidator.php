<?php

class PlanningValidator extends Validator
{
    private PlanningDetailsValidator $detailsValidator;

    public function __construct(array $data)
    {
        parent::__construct($data);

        $this->detailsValidator =
            new PlanningDetailsValidator();
    }

    public function validateCreate(): self
    {
        $this
            ->required(
                'nome',
                'O nome do planejamento é obrigatório.'
            )
            ->required(
                'tipo',
                'O tipo do planejamento é obrigatório.'
            )
            ->required(
                'valor_atual',
                'O valor atual é obrigatório.'
            )
            ->required(
                'prazo_meses',
                'O prazo é obrigatório.'
            )
            ->required(
                'receita_mensal',
                'A receita mensal é obrigatória.'
            )
            ->required(
                'despesas_fixas',
                'As despesas fixas são obrigatórias.'
            )
            ->required(
                'despesas_variaveis',
                'As despesas variáveis são obrigatórias.'
            )
            ->numeric(
                'valor_atual',
                'O valor atual deve ser numérico.'
            )
            ->numeric(
                'prazo_meses',
                'O prazo deve ser numérico.'
            )
            ->numeric(
                'receita_mensal',
                'A receita mensal deve ser numérica.'
            )
            ->numeric(
                'despesas_fixas',
                'As despesas fixas devem ser numéricas.'
            )
            ->numeric(
                'despesas_variaveis',
                'As despesas variáveis devem ser numéricas.'
            );

        $this->validatePlanningType();

        $this->validateGoalAmount();

        $this->validateNonNegative(
            'valor_atual',
            'O valor atual não pode ser negativo.'
        );

        $this->validateNonNegative(
            'receita_mensal',
            'A receita mensal não pode ser negativa.'
        );

        $this->validateNonNegative(
            'despesas_fixas',
            'As despesas fixas não podem ser negativas.'
        );

        $this->validateNonNegative(
            'despesas_variaveis',
            'As despesas variáveis não podem ser negativas.'
        );

        $this->validatePositiveInteger(
            'prazo_meses',
            'O prazo deve ser um número inteiro maior que zero.'
        );

        $this->validateDetails();

        return $this;
    }

    private function validatePlanningType(): void
    {
        $value = $this->data['tipo'] ?? null;

        if (
            $value === null ||
            trim((string) $value) === ''
        ) {
            return;
        }

        $type = PlanningType::normalize(
            (string) $value
        );

        if (!PlanningType::isValid($type)) {
            $this->errors['tipo'] =
                'O tipo de planejamento informado é inválido.';
        }
    }

    private function validateGoalAmount(): void
    {
        $type = PlanningType::normalize(
            (string) ($this->data['tipo'] ?? '')
        );

        if (
            $type === PlanningType::VIAGEM ||
            $type === PlanningType::COMPRA ||
            $type === PlanningType::PROJETO ||
            $type === PlanningType::EVENTO
        ) {
            return;
        }

        $value = $this->data['valor_objetivo'] ?? null;

        if (
            $value === null ||
            !is_scalar($value) ||
            trim((string) $value) === ''
        ) {
            $this->errors['valor_objetivo'] =
                'O valor do objetivo é obrigatório.';

            return;
        }

        if (!is_numeric($value)) {
            $this->errors['valor_objetivo'] =
                'O valor do objetivo deve ser numérico.';
        }

        if (
            is_numeric($value) &&
            (float) $value < 0
        ) {
            $this->errors['valor_objetivo'] =
                'O valor do objetivo não pode ser negativo.';
        }
    }

    private function validateDetails(): void
    {
        $type = $this->data['tipo'] ?? '';

        if (
            !PlanningType::isValid(
                (string) $type
            )
        ) {
            return;
        }

        $details = $this->data['detalhes'] ?? null;

        $errors = $this->detailsValidator->validate(
            (string) $type,
            $details
        );

        foreach ($errors as $field => $message) {
            $this->errors[$field] = $message;
        }
    }

    private function validateNonNegative(
        string $field,
        string $message
    ): void {
        $value = $this->data[$field] ?? null;

        if (
            $value !== null &&
            is_numeric($value) &&
            (float) $value < 0
        ) {
            $this->errors[$field] = $message;
        }
    }

    private function validatePositiveInteger(
        string $field,
        string $message
    ): void {
        $value = $this->data[$field] ?? null;

        if (
            $value !== null &&
            is_numeric($value) &&
            (
                (float) $value <= 0 ||
                (float) $value != (int) $value
            )
        ) {
            $this->errors[$field] = $message;
        }
    }
}
