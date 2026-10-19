<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;
use App\Core\Session;

if (!defined('BASE_PATH')) {
    $base = dirname(__DIR__);
    if (!is_dir($base . '/database')) {
        $base = dirname($base);
    }
    define('BASE_PATH', $base);
}

$composer = BASE_PATH . '/vendor/autoload.php';
if (is_file($composer)) {
    require $composer;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = BASE_PATH . '/app/' . $relative . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}

Env::load(BASE_PATH . '/.env', BASE_PATH . '/.env.example');

date_default_timezone_set(Env::get('APP_TIMEZONE', 'America/Sao_Paulo') ?? 'America/Sao_Paulo');
error_reporting(E_ALL);
ini_set('display_errors', Env::bool('APP_DEBUG', false) ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', \App\Core\Paths::writable('logs/php-error.log'));

set_exception_handler(static function (Throwable $exception): void {
    error_log((string) $exception);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: inline');
    }
    $message = Env::bool('APP_DEBUG', false)
        ? htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8')
        : 'Ocorreu um erro interno. A operação não foi concluída.';
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Erro</title>';
    echo '<body><h1>Erro interno</h1><p>' . $message . '</p></body></html>';
});

$forwardedHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '';
$forwardedHost = strtolower(trim(explode(',', (string) $forwardedHost)[0]));
$forwardedHost = (string) preg_replace('/:\d+$/', '', $forwardedHost);
if ($forwardedHost === 'pdv.lojadeeletronicossaojose.com.br') {
    $_ENV['APP_URL'] = 'https://pdv.lojadeeletronicossaojose.com.br';
    $_SERVER['APP_URL'] = 'https://pdv.lojadeeletronicossaojose.com.br';
    $_ENV['SESSION_SECURE'] = 'true';
    $_SERVER['SESSION_SECURE'] = 'true';
    putenv('APP_URL=https://pdv.lojadeeletronicossaojose.com.br');
    putenv('SESSION_SECURE=true');
} elseif (Env::get('APP_URL') === null && Env::get('VERCEL_URL')) {
    putenv('APP_URL=https://' . Env::get('VERCEL_URL'));
    $_ENV['APP_URL'] = 'https://' . Env::get('VERCEL_URL');
}

Database::configure();
if (PHP_SAPI !== 'cli') {
    \App\Setup\Runtime::bootDatabase();
    \App\Core\Security::sendHeaders();
}
Session::start();