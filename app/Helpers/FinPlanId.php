<?php

/**
 * Normaliza identificadores do schema finplan aceitando os dois formatos
 * usados pelo banco:
 *
 *   BIGINT -> retorna int (ex.: 42)
 *   UUID   -> retorna string minúscula (ex.: 3f2504e0-4f89-41d3-9a0c-0305e82c3301)
 *
 * O valor normalizado é o que deve circular pelo token, pelo contexto do banco
 * (fn_iniciar_contexto) e pelas assinaturas int|string dos serviços.
 */
class FinPlanId
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public static function normalize(mixed $value): int|string
    {
        if (is_int($value)) {
            if ($value < 1) {
                throw new InvalidArgumentException('Identificador inválido.');
            }

            return $value;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('Identificador inválido.');
        }

        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('Identificador inválido.');
        }

        if (preg_match('/^[1-9][0-9]*$/', $value) === 1) {
            $integer = filter_var(
                $value,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            // Em builds 32-bit, um BIGINT pode exceder o PHP_INT_MAX.
            // Nesse caso o valor segue como string numérica para o PostgreSQL.
            return $integer === false ? $value : $integer;
        }

        if (self::isUuid($value)) {
            return strtolower($value);
        }

        throw new InvalidArgumentException('Identificador inválido.');
    }

    public static function tryNormalize(mixed $value): int|string|null
    {
        try {
            return self::normalize($value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public static function isUuid(mixed $value): bool
    {
        return is_string($value) && preg_match(self::UUID_PATTERN, trim($value)) === 1;
    }
}
