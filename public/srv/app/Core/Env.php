<?php

declare(strict_types=1);

namespace App\Core;

final class Env
{
    /** @var array<string, string> */
    private static array $values = [];

    public static function load(string $primary, ?string $fallback = null): void
    {
        $file = is_file($primary) ? $primary : $fallback;
        if ($file === null || !is_file($file)) {
            return;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if ($key === '') {
                continue;
            }
            $value = trim($value, "\"'");
            self::$values[$key] = $value;
            if (getenv($key) === false) {
                putenv($key . '=' . $value);
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        foreach ([$_ENV[$key] ?? null, $_SERVER[$key] ?? null] as $value) {
            if (is_string($value)) {
                return $value;
            }
        }
        $env = getenv($key);
        if (is_string($env)) {
            return $env;
        }
        return self::$values[$key] ?? $default;
    }

    public static function require(string $key): string
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            throw new \RuntimeException("Configuração obrigatória ausente: {$key}");
        }
        return $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function int(string $key, int $default): int
    {
        $value = self::get($key);
        return $value === null || !is_numeric($value) ? $default : (int) $value;
    }
}

