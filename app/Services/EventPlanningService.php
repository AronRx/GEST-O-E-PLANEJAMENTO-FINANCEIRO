<?php

class EventPlanningService
{
    public function calculateBudget(array $data): array
    {
        $this->validateRequiredData($data);

        $description = trim(
            (string) $data['descricao']
        );

        $items = $data['itens'];

        $total = 0;

        $calculatedItems = [];

        foreach ($items as $index => $item) {
            $itemName = trim(
                (string) $item['nome']
            );

            $itemValue = (float) $item['valor'];

            $total += $itemValue;

            $calculatedItems[] = [
                'nome' => $itemName,

                'valor' => $this->roundMoney(
                    $itemValue
                )
            ];
        }

        return [
            'descricao' => $description,

            'itens' => $calculatedItems,

            'orcamento' => [
                'total' => $this->roundMoney(
                    $total
                )
            ]
        ];
    }

    private function validateRequiredData(
        array $data
    ): void {
        if (
            !array_key_exists(
                'descricao',
                $data
            ) ||
            !is_scalar($data['descricao']) ||
            trim((string) $data['descricao']) === ''
        ) {
            throw new InvalidArgumentException(
                'O campo descricao é obrigatório.'
            );
        }

        if (
            !array_key_exists(
                'itens',
                $data
            ) ||
            !is_array($data['itens']) ||
            count($data['itens']) === 0
        ) {
            throw new InvalidArgumentException(
                'O evento deve possuir pelo menos um item.'
            );
        }

        foreach ($data['itens'] as $index => $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException(
                    "O item {$index} do evento é inválido."
                );
            }

            if (
                !array_key_exists(
                    'nome',
                    $item
                ) ||
                !is_scalar($item['nome']) ||
                trim((string) $item['nome']) === ''
            ) {
                throw new InvalidArgumentException(
                    "O nome do item {$index} é obrigatório."
                );
            }

            if (
                !array_key_exists(
                    'valor',
                    $item
                ) ||
                !is_numeric($item['valor'])
            ) {
                throw new InvalidArgumentException(
                    "O valor do item {$index} deve ser numérico."
                );
            }

            if ((float) $item['valor'] < 0) {
                throw new InvalidArgumentException(
                    "O valor do item {$index} não pode ser negativo."
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
