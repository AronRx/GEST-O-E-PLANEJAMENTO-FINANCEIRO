<?php

class PurchasePlanningService
{
    public function calculateBudget(array $data): array
    {
        $this->validateRequiredData($data);

        $product = trim(
            (string) $data['produto']
        );

        $purchaseValue = (float) $data['valor'];

        $initialAmount = (float) $data['entrada'];

        $installmentPurchase =
            (bool) $data['parcelamento'];

        $remainingAmount = max(
            0,
            $purchaseValue - $initialAmount
        );

        return [
            'produto' => $product,

            'orcamento' => [
                'valor' => $this->roundMoney(
                    $purchaseValue
                ),

                'valor_inicial' => $this->roundMoney(
                    $initialAmount
                ),

                'valor_faltante' => $this->roundMoney(
                    $remainingAmount
                ),

                'parcelamento' => $installmentPurchase,

                'total' => $this->roundMoney(
                    $purchaseValue
                )
            ]
        ];
    }

    private function validateRequiredData(
        array $data
    ): void {

        $requiredFields = [
            'produto',
            'valor',
            'entrada'
        ];

        foreach ($requiredFields as $field) {
            if (
                !array_key_exists(
                    $field,
                    $data
                ) ||
                !is_scalar($data[$field]) ||
                trim((string) $data[$field]) === ''
            ) {
                throw new InvalidArgumentException(
                    "O campo {$field} é obrigatório."
                );
            }
        }

        if (!array_key_exists('parcelamento', $data)) {
            throw new InvalidArgumentException(
                'O campo parcelamento é obrigatório.'
            );
        }

        if (
            !is_numeric($data['valor']) ||
            (float) $data['valor'] < 0
        ) {
            throw new InvalidArgumentException(
                'O valor da compra deve ser maior ou igual a zero.'
            );
        }

        if (
            !is_numeric($data['entrada']) ||
            (float) $data['entrada'] < 0
        ) {
            throw new InvalidArgumentException(
                'O valor da entrada deve ser maior ou igual a zero.'
            );
        }

        if (
            (float) $data['entrada'] >
            (float) $data['valor']
        ) {
            throw new InvalidArgumentException(
                'A entrada não pode ser maior que o valor da compra.'
            );
        }

        if (
            !in_array(
                $data['parcelamento'],
                [true, false, 0, 1, '0', '1'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'O campo parcelamento deve ser booleano.'
            );
        }
    }

    private function roundMoney(float $value): float
    {
        return round(
            $value,
            2
        );
    }
}
