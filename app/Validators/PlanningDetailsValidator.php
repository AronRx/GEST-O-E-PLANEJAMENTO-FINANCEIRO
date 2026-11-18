<?php

class PlanningDetailsValidator
{
    public function validate(
        string $type,
        mixed $details
    ): array {
        $errors = [];

        if ($details === null) {
            return $errors;
        }

        if (!is_array($details)) {
            return [
                'detalhes' =>
                    'Os detalhes do planejamento devem ser enviados como um objeto.'
            ];
        }

        switch (PlanningType::normalize($type)) {
            case PlanningType::META:
                $errors = $this->validateMeta(
                    $details
                );

                break;

            case PlanningType::VIAGEM:
                $errors = $this->validateTravel(
                    $details
                );

                break;

            case PlanningType::COMPRA:
                $errors = $this->validatePurchase(
                    $details
                );

                break;

            case PlanningType::PROJETO:
                $errors = $this->validateProject(
                    $details
                );

                break;

            case PlanningType::EVENTO:
                $errors = $this->validateEvent(
                    $details
                );

                break;
        }

        return $errors;
    }

    private function validateMeta(
        array $details
    ): array {
        return [];
    }

    private function validateTravel(
        array $details
    ): array {
        $errors = [];

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
                    $details
                ) ||
                !is_scalar($details[$field]) ||
                trim((string) $details[$field]) === ''
            ) {
                $errors["detalhes.{$field}"] =
                    "O campo {$field} é obrigatório.";
            }
        }

        $this->validatePositiveInteger(
            $details,
            'pessoas',
            $errors,
            'A quantidade de pessoas deve ser maior que zero.'
        );

        $this->validatePositiveInteger(
            $details,
            'dias',
            $errors,
            'A quantidade de dias deve ser maior que zero.'
        );

        $moneyFields = [
            'transporte',
            'hospedagem',
            'alimentacao',
            'transporte_local',
            'atividades',
            'reserva_emergencia'
        ];

        foreach ($moneyFields as $field) {
            $this->validateMoney(
                $details,
                $field,
                $errors
            );
        }

        return $errors;
    }

    private function validatePurchase(
        array $details
    ): array {
        $errors = [];

        $requiredFields = [
            'produto',
            'valor',
            'entrada'
        ];

        foreach ($requiredFields as $field) {
            if (
                !array_key_exists(
                    $field,
                    $details
                ) ||
                !is_scalar($details[$field]) ||
                trim((string) $details[$field]) === ''
            ) {
                $errors["detalhes.{$field}"] =
                    "O campo {$field} é obrigatório.";
            }
        }

        if (!array_key_exists('parcelamento', $details)) {
            $errors['detalhes.parcelamento'] =
                'O campo parcelamento é obrigatório.';
        } elseif (
            !in_array(
                $details['parcelamento'],
                [true, false, 0, 1, '0', '1'],
                true
            )
        ) {
            $errors['detalhes.parcelamento'] =
                'O campo parcelamento deve ser booleano.';
        }

        $this->validateMoney(
            $details,
            'valor',
            $errors
        );

        $this->validateMoney(
            $details,
            'entrada',
            $errors
        );

        if (
            isset($details['valor']) &&
            isset($details['entrada']) &&
            is_numeric($details['valor']) &&
            is_numeric($details['entrada']) &&
            (float) $details['entrada'] >
            (float) $details['valor']
        ) {
            $errors['detalhes.entrada'] =
                'A entrada não pode ser maior que o valor da compra.';
        }

        return $errors;
    }

    private function validateProject(
        array $details
    ): array {
        return $this->validateItemBasedPlanning(
            $details,
            'projeto'
        );
    }

    private function validateEvent(
        array $details
    ): array {
        return $this->validateItemBasedPlanning(
            $details,
            'evento'
        );
    }

    private function validateItemBasedPlanning(
        array $details,
        string $typeName
    ): array {
        $errors = [];

        if (
            !array_key_exists(
                'descricao',
                $details
            ) ||
            !is_scalar($details['descricao']) ||
            trim((string) $details['descricao']) === ''
        ) {
            $errors['detalhes.descricao'] =
                'O campo descricao é obrigatório.';
        }

        if (
            !array_key_exists(
                'itens',
                $details
            )
        ) {
            $errors['detalhes.itens'] =
                "O {$typeName} deve possuir pelo menos um item.";

            return $errors;
        }

        if (
            !is_array($details['itens']) ||
            count($details['itens']) === 0
        ) {
            $errors['detalhes.itens'] =
                "O {$typeName} deve possuir pelo menos um item.";

            return $errors;
        }

        foreach (
            $details['itens']
            as $index => $item
        ) {
            if (!is_array($item)) {
                $errors[
                    "detalhes.itens.{$index}"
                ] =
                    'O item deve ser um objeto válido.';

                continue;
            }

            if (
                !array_key_exists(
                    'nome',
                    $item
                ) ||
                !is_scalar($item['nome']) ||
                trim((string) $item['nome']) === ''
            ) {
                $errors[
                    "detalhes.itens.{$index}.nome"
                ] =
                    'O nome do item é obrigatório.';
            }

            if (
                !array_key_exists(
                    'valor',
                    $item
                )
            ) {
                $errors[
                    "detalhes.itens.{$index}.valor"
                ] =
                    'O valor do item é obrigatório.';

                continue;
            }

            if (!is_numeric($item['valor'])) {
                $errors[
                    "detalhes.itens.{$index}.valor"
                ] =
                    'O valor do item deve ser numérico.';

                continue;
            }

            if ((float) $item['valor'] < 0) {
                $errors[
                    "detalhes.itens.{$index}.valor"
                ] =
                    'O valor do item não pode ser negativo.';
            }
        }

        return $errors;
    }

    private function validatePositiveInteger(
        array $details,
        string $field,
        array &$errors,
        string $message
    ): void {
        if (
            !array_key_exists(
                $field,
                $details
            )
        ) {
            return;
        }

        if (
            !is_numeric($details[$field]) ||
            (float) $details[$field] <= 0 ||
            (float) $details[$field] !=
            (int) $details[$field]
        ) {
            $errors["detalhes.{$field}"] =
                $message;
        }
    }

    private function validateMoney(
        array $details,
        string $field,
        array &$errors
    ): void {
        if (
            !array_key_exists(
                $field,
                $details
            )
        ) {
            return;
        }

        if (!is_numeric($details[$field])) {
            $errors["detalhes.{$field}"] =
                "O campo {$field} deve ser numérico.";

            return;
        }

        if ((float) $details[$field] < 0) {
            $errors["detalhes.{$field}"] =
                "O campo {$field} não pode ser negativo.";
        }
    }
}
