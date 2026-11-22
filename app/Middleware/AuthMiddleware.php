<?php

class AuthMiddleware
{
    public static function authenticate(): array
    {
        $authorization = self::getAuthorizationHeader();

        if ($authorization === '') {
            Response::error('Token de autenticação não informado.', 401);
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            Response::error('Formato do token de autenticação inválido.', 401);
        }

        $token = trim($matches[1]);
        $payload = Token::validate($token);

        if ($payload === null) {
            Response::error('Token de autenticação inválido ou expirado.', 401);
        }

        $userId = FinPlanId::tryNormalize($payload['sub'] ?? null);

        if ($userId === null) {
            Response::error('Token de autenticação inválido.', 401);
        }

        try {
            $user = (new UserRepository())->findById($userId);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '42501') {
                Response::error('Sessão inválida ou usuário inativo.', 401);
            }

            error_log('[Nymbrio API] Não foi possível validar o usuário do token: ' . $exception->getMessage());
            Response::error('Não foi possível validar a sessão.', 500);
        } catch (Throwable $exception) {
            error_log('[Nymbrio API] Não foi possível validar o usuário do token: ' . $exception->getMessage());
            Response::error('Não foi possível validar a sessão.', 500);
        }

        if (!$user || !self::databaseBoolean($user['ativo'] ?? false)) {
            Response::error('Sessão inválida ou usuário inativo.', 401);
        }

        return [
            'sub' => FinPlanId::normalize($user['id']),
            'nome' => (string) $user['nome'],
            'email' => (string) $user['email'],
            'perfil' => (string) $user['perfil'],
            'iat' => (int) $payload['iat'],
            'exp' => (int) $payload['exp']
        ];
    }

    private static function databaseBoolean(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        return is_string($value) && in_array(strtolower($value), ['t', 'true', 'yes', 'on'], true);
    }

    private static function getAuthorizationHeader(): string
    {
        $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if ($authorization !== '') {
            return trim($authorization);
        }

        $authorization = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

        if ($authorization !== '') {
            return trim($authorization);
        }

        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strtolower($name) === 'authorization') {
                    return trim((string) $value);
                }
            }
        }

        return '';
    }
}
