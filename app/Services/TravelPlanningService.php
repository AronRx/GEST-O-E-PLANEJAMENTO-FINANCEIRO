<?php

class TravelPlanningService
{
    public function calculateBudget(array $data): array
    {
        $this->validateRequiredData($data);

        $transport = (float) $data['transporte'];

        $accommodation = (float) $data['hospedagem'];

        $food = (float) $data['alimentacao'];

        $localTransport = (float) $data['transporte_local'];

        $activities = (float) $data['atividades'];

        $emergencyReserve = (float) $data['reserva_emergencia'];

        $total = $transport
            + $accommodation
            + $food
            + $localTransport
            + $activities
            + $emergencyReserve;

        return [
            'destino' => trim(
                (string) $data['destino']
            ),

            'pessoas' => (int) $data['pessoas'],

            'dias' => (int) $data['dias'],

            'orcamento' => [
                'transporte' => $this->roundMoney(
                    $transport
                ),

                'hospedagem' => $this->roundMoney(
                    $accommodation
                ),

                'alimentacao' => $this->roundMoney(
                    $food
                ),

                'transporte_local' => $this->roundMoney(
                    $localTransport
                ),

                'atividades' => $this->roundMoney(
                    $activities
                ),

                'reserva_emergencia' => $this->roundMoney(
                    $emergencyReserve
                ),

                'total' => $this->roundMoney(
                    $total
                )
            ]
        ];
    }

    private function validateRequiredData(
        array $data
    ): void {
        $requiredFields = [
            'destino',
            'pessoas',
            'dias',
            'transporte',
            'hospedagem',
            'alimentacao',
            'transporte_local',
            'atividades',
            'reserva_emergencia'
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

        if (
            !is_numeric($data['pessoas']) ||
            (int) $data['pessoas'] <= 0
        ) {
            throw new InvalidArgumentException(
                'A quantidade de pessoas deve ser maior que zero.'
            );
        }

        if (
            !is_numeric($data['dias']) ||
            (int) $data['dias'] <= 0
        ) {
            throw new InvalidArgumentException(
                'A quantidade de dias deve ser maior que zero.'
            );
        }

        $moneyFields = [
            'transporte',
            'hospedagem',
            'alimentacao',
            'transporte_local',
            'atividades',
            'reserva_emergencia'
        ];

        foreach ($moneyFields as $field) {
            if (!is_numeric($data[$field])) {
                throw new InvalidArgumentException(
                    "O campo {$field} deve ser numérico."
                );
            }

            if ((float) $data[$field] < 0) {
                throw new InvalidArgumentException(
                    "O campo {$field} não pode ser negativo."
                );
            }
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
