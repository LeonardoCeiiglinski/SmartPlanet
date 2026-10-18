<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        $token = Session::get('_csrf');
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            Session::put('_csrf', $token);
        }
        return $token;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function verify(?string $token): bool
    {
        $stored = Session::get('_csrf');
        return is_string($stored) && is_string($token) && hash_equals($stored, $token);
    }

    public static function enforce(): void
    {
        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!self::verify(is_string($token) ? $token : null)) {
            http_response_code(419);
            throw new \RuntimeException('Sessão expirada ou formulário inválido. Atualize a página e tente novamente.');
        }
    }
}

