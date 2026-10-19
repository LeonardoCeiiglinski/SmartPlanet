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

        $url = Env::get('DATABASE_URL') ?? Env::get('MYSQL_URL') ?? Env::get('MYSQL_PUBLIC_URL');
        if ($url) {
            self::applyUrl($url);
        }
        $driver = Env::get('DB_DRIVER', Paths::isServerless() ? 'pgsql' : 'sqlite');
        if ($driver === 'sqlite') {
            if (Paths::isServerless()) {
                throw new \RuntimeException('Na Vercel use Postgres (Supabase) ou MySQL persistente. SQLite não sobrevive entre requisições.');
            }
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
            $options = [];
            if (Env::bool('DB_SSL', Paths::isServerless())) {
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }
            self::$pdo = new PDO($dsn, Env::require('DB_USERNAME'), Env::get('DB_PASSWORD', '') ?? '', $options);
        } elseif (in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
            if (!extension_loaded('pdo_pgsql')) {
                throw new \RuntimeException('A extensão pdo_pgsql precisa estar habilitada.');
            }
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                Env::get('DB_HOST', '127.0.0.1'),
                Env::get('DB_PORT', '5432'),
                Env::require('DB_DATABASE')
            );
            $ssl = Env::get('DB_SSLMODE', Paths::isServerless() ? 'require' : 'prefer');
            if ($ssl) {
                $dsn .= ';sslmode=' . $ssl;
            }
            self::$pdo = new PDO($dsn, Env::require('DB_USERNAME'), Env::get('DB_PASSWORD', '') ?? '');
        } else {
            throw new \RuntimeException('DB_DRIVER deve ser mysql, pgsql ou sqlite.');
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

    private static function applyUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            throw new \RuntimeException('DATABASE_URL inválida.');
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? 'mysql'));
        $pgsql = in_array($scheme, ['postgres', 'postgresql', 'pgsql'], true);
        $mysql = in_array($scheme, ['mysql', 'mariadb', 'mysql2'], true);
        if (!$pgsql && !$mysql) {
            throw new \RuntimeException('Use DATABASE_URL Postgres (Supabase) ou MySQL.');
        }
        $mapped = $pgsql ? 'pgsql' : 'mysql';
        putenv('DB_DRIVER=' . $mapped);
        $_ENV['DB_DRIVER'] = $mapped;
        putenv('DB_HOST=' . $parts['host']);
        $_ENV['DB_HOST'] = $parts['host'];
        $port = (string) ($parts['port'] ?? ($pgsql ? 5432 : 3306));
        putenv('DB_PORT=' . $port);
        $_ENV['DB_PORT'] = $port;
        $db = ltrim((string) ($parts['path'] ?? ''), '/');
        if ($db !== '') {
            putenv('DB_DATABASE=' . $db);
            $_ENV['DB_DATABASE'] = $db;
        }
        if (isset($parts['user'])) {
            putenv('DB_USERNAME=' . $parts['user']);
            $_ENV['DB_USERNAME'] = $parts['user'];
        }
        if (isset($parts['pass'])) {
            putenv('DB_PASSWORD=' . $parts['pass']);
            $_ENV['DB_PASSWORD'] = $parts['pass'];
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

