<?php

declare(strict_types=1);

namespace App\Core;

final class Paths
{
    public static function writable(string $relative = ''): string
    {
        $root = self::isServerless()
            ? rtrim(sys_get_temp_dir(), '/\\') . '/smartplanet'
            : BASE_PATH . '/storage';
        if (!is_dir($root)) {
            mkdir($root, 0775, true);
        }
        $path = $relative === '' ? $root : $root . '/' . ltrim(str_replace('\\', '/', $relative), '/');
        $dir = is_dir($path) ? $path : dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $path;
    }

    public static function isServerless(): bool
    {
        return Env::get('VERCEL') === '1'
            || Env::get('AWS_LAMBDA_FUNCTION_NAME') !== null
            || Env::get('VERCEL_ENV') !== null;
    }

    public static function isHttps(): bool
    {
        $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($forwarded === 'https') {
            return true;
        }
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    }
}
