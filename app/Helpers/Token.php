<?php

class Token
{
    private const ALGORITHM = 'sha256';

    private const DEFAULT_TTL = 7200;

    public static function generate(array $payload): string
    {
        $header = [
            'alg' => self::ALGORITHM,
            'typ' => 'TOKEN'
        ];

        $encodedHeader = self::encode($header);

        $issuedAt = time();

        $payload['iat'] = $issuedAt;
        $payload['exp'] = $issuedAt + self::ttl();

        $encodedPayload = self::encode($payload);

        $signature = hash_hmac(
            self::ALGORITHM,
            $encodedHeader . '.' . $encodedPayload,
            self::secret()
        );

        return $encodedHeader
            . '.'
            . $encodedPayload
            . '.'
            . $signature;
    }

    public static function validate(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $signature] = $parts;

        $expectedSignature = hash_hmac(
            self::ALGORITHM,
            $encodedHeader . '.' . $encodedPayload,
            self::secret()
        );

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $header = self::decode($encodedHeader);

        if (!is_array($header)) {
            return null;
        }

        if (
            ($header['alg'] ?? null) !== self::ALGORITHM ||
            ($header['typ'] ?? null) !== 'TOKEN'
        ) {
            return null;
        }

        $payload = self::decode($encodedPayload);

        if (!is_array($payload)) {
            return null;
        }

        if (!isset($payload['iat'], $payload['exp'])) {
            return null;
        }

        if (
            !is_numeric($payload['iat']) ||
            !is_numeric($payload['exp'])
        ) {
            return null;
        }

        if ((int) $payload['exp'] <= time()) {
            return null;
        }

        return $payload;
    }

    private static function ttl(): int
    {
        $ttl = getenv('TOKEN_TTL');

        if ($ttl === false || $ttl === '') {
            return self::DEFAULT_TTL;
        }

        $ttl = (int) $ttl;

        if ($ttl <= 0) {
            return self::DEFAULT_TTL;
        }

        return $ttl;
    }

    private static function encode(array $data): string
    {
        $json = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            throw new RuntimeException(
                'Não foi possível codificar os dados do token.'
            );
        }

        return rtrim(
            strtr(
                base64_encode($json),
                '+/',
                '-_'
            ),
            '='
        );
    }

    private static function decode(string $data): ?array
    {
        $padding = strlen($data) % 4;

        if ($padding !== 0) {
            $data .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(
            strtr($data, '-_', '+/'),
            true
        );

        if ($decoded === false) {
            return null;
        }

        $result = json_decode($decoded, true);

        return is_array($result) ? $result : null;
    }

    private static function secret(): string
    {
        $secret = getenv('APP_KEY');

        if (!$secret) {
            throw new RuntimeException(
                'APP_KEY não configurada.'
            );
        }

        return $secret;
    }
}
