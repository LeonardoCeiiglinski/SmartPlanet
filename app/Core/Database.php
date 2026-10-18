<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

final class Database
{
    private static ?PDO $pdo = null;

    public static function configure(?PDO $pdo = null): void
    {
        if ($pdo !== null) {
            self::$pdo = $pdo;
        }
    }

    public static function connection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $driver = Env::get('DB_DRIVER', 'sqlite');
        if ($driver === 'sqlite') {
            $database = Env::get('DB_DATABASE', 'storage/app/erp.sqlite') ?? 'storage/app/erp.sqlite';
            $path = self::absolutePath($database);
            $directory = dirname($path);
            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }
            self::$pdo = new PDO('sqlite:' . $path);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            self::$pdo->exec('PRAGMA busy_timeout = 5000');
        } elseif ($driver === 'mysql') {
            if (!extension_loaded('pdo_mysql')) {
                throw new \RuntimeException('A extensão pdo_mysql precisa estar habilitada.');
            }
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                Env::get('DB_HOST', '127.0.0.1'),
                Env::get('DB_PORT', '3306'),
                Env::require('DB_DATABASE')
            );
            self::$pdo = new PDO($dsn, Env::require('DB_USERNAME'), Env::get('DB_PASSWORD', ''));
        } else {
            throw new \RuntimeException('DB_DRIVER deve ser mysql ou sqlite.');
        }

        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        return self::$pdo;
    }

    public static function driver(): string
    {
        return (string) self::connection()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public static function transactional(callable $callback): mixed
    {
        $pdo = self::connection();
        $outer = $pdo->inTransaction();
        if (!$outer) {
            $pdo->beginTransaction();
        }
        try {
            $result = $callback($pdo);
            if (!$outer) {
                $pdo->commit();
            }
            return $result;
        } catch (Throwable $exception) {
            if (!$outer && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private static function absolutePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) || str_starts_with($path, '/')) {
            return $path;
        }
        return BASE_PATH . '/' . ltrim(str_replace('\\', '/', $path), '/');
    }
}

