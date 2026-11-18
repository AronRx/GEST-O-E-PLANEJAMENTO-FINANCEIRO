<?php

class PlanningType
{
    public const META = 'META';

    public const VIAGEM = 'VIAGEM';

    public const COMPRA = 'COMPRA';

    public const PROJETO = 'PROJETO';

    public const EVENTO = 'EVENTO';

    public static function all(): array
    {
        return [
            self::META,
            self::VIAGEM,
            self::COMPRA,
            self::PROJETO,
            self::EVENTO
        ];
    }

    public static function isValid(string $type): bool
    {
        return in_array(
            strtoupper(trim($type)),
            self::all(),
            true
        );
    }

    public static function normalize(string $type): string
    {
        return strtoupper(
            trim($type)
        );
    }
}
