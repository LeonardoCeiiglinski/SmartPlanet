<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name(Env::get('SESSION_NAME', 'erp_pdv_session') ?? 'erp_pdv_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => Env::bool('SESSION_SECURE', Paths::isHttps()),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        $driver = Env::get('SESSION_DRIVER', Paths::isServerless() ? 'database' : 'files');
        if ($driver === 'database') {
            $handler = new PdoSessionHandler(Database::connection());
            session_set_save_handler($handler, true);
        } elseif (Paths::isServerless()) {
            session_save_path(Paths::writable('sessions'));
        }
        session_start();

        $now = time();
        $idle = Env::int('SESSION_IDLE_MINUTES', 30) * 60;
        $absolute = Env::int('SESSION_ABSOLUTE_HOURS', 12) * 3600;
        $expired = (isset($_SESSION['_last_activity']) && $now - (int) $_SESSION['_last_activity'] > $idle)
            || (isset($_SESSION['_started_at']) && $now - (int) $_SESSION['_started_at'] > $absolute);
        if ($expired) {
            self::destroy();
            session_start();
        }
        if (!isset($_SESSION['_started_at'])) {
            $_SESSION['_started_at'] = $now;
        }
        $_SESSION['_last_activity'] = $now;
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return array<int, array{type:string,message:string}> */
    public static function consumeFlash(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return is_array($messages) ? $messages : [];
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}

