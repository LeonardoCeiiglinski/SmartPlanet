<?php

declare(strict_types=1);

namespace App\Core;

final class Security
{
    private static ?string $nonce = null;

    public static function nonce(): string
    {
        return self::$nonce ??= bin2hex(random_bytes(16));
    }

    public static function nonceAttribute(): string
    {
        return ' nonce="' . htmlspecialchars(self::nonce(), ENT_QUOTES, 'UTF-8') . '"';
    }

    public static function sendHeaders(): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }
        $nonce = self::nonce();
        header(
            "Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; "
            . "frame-ancestors 'none'; form-action 'self'; img-src 'self' data: blob:; font-src 'self'; "
            . "style-src 'self' 'unsafe-inline'; script-src 'nonce-{$nonce}' 'strict-dynamic'; connect-src 'self'"
        );
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('Permissions-Policy: accelerometer=(), camera=(), geolocation=(), gyroscope=(), microphone=(), payment=()');
        header('X-Permitted-Cross-Domain-Policies: none');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
    }
}
