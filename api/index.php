<?php

declare(strict_types=1);

header('Content-Disposition: inline');
header('X-Content-Type-Options: nosniff');

if (($_GET['verhost'] ?? '') === '1') {
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    $lines = [];
    foreach ($_SERVER as $key => $value) {
        if (!is_string($value)) {
            continue;
        }
        $lower = strtolower((string) $key);
        if (str_contains($lower, 'host') || str_contains($lower, 'forward') || str_contains($lower, 'vercel') || str_contains($lower, 'http_cf')) {
            $lines[] = $key . '=' . $value;
        }
    }
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            $lines[] = 'H:' . $name . '=' . $value;
        }
    }
    echo implode("\n", $lines);
    exit;
}

function request_is_pdv(): bool
{
    $wanted = 'pdv.lojadeletronicossaojose.com.br';
    $sources = [
        $_SERVER['HTTP_HOST'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_HOST'] ?? '',
        $_SERVER['HTTP_X_ORIGINAL_HOST'] ?? '',
        $_SERVER['SERVER_NAME'] ?? '',
    ];
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (str_contains(strtolower((string) $name), 'host')) {
                $sources[] = $value;
            }
        }
    }
    foreach ($sources as $raw) {
        foreach (explode(',', (string) $raw) as $part) {
            $host = strtolower(trim($part));
            $host = (string) preg_replace('/:\d+$/', '', $host);
            if ($host === $wanted) {
                return true;
            }
        }
    }

    return false;
}

function proxy_to_pdv(string $uri): void
{
    $ch = curl_init('https://smart-planet-rouge.vercel.app' . $uri);
    $headers = [];
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            $lower = strtolower($name);
            if (in_array($lower, ['host', 'content-length', 'accept-encoding', 'connection'], true)) {
                continue;
            }
            $headers[] = $name . ': ' . $value;
        }
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 25,
    ]);
    if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input') ?: '');
    }
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    if (!is_string($response)) {
        http_response_code(502);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>SmartPlanet</title><body><h1>O PDV não respondeu</h1></body></html>';
        return;
    }
    http_response_code($status > 0 ? $status : 502);
    $rawHeaders = substr($response, 0, $headerSize);
    foreach (preg_split("/\r\n|\n|\r/", $rawHeaders) ?: [] as $line) {
        if ($line === '' || str_starts_with($line, 'HTTP/')) {
            continue;
        }
        $lower = strtolower($line);
        if (str_starts_with($lower, 'transfer-encoding:') || str_starts_with($lower, 'content-length:') || str_starts_with($lower, 'content-encoding:')) {
            continue;
        }
        if (str_starts_with($lower, 'location:')) {
            $line = preg_replace('#https://smart-planet-rouge\.vercel\.app#i', '', $line) ?? $line;
        }
        header($line, false);
    }
    header('Content-Disposition: inline');
    echo substr($response, $headerSize);
}

function serve_site_file(string $path): bool
{
    if ($path === '/favicon.ico') {
        $path = '/assets/img/logo.png';
    }
    if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
        return false;
    }
    $root = dirname(__DIR__) . '/public';
    $file = $root . '/' . ltrim($path, '/');
    $rootReal = realpath($root);
    $fileReal = realpath($file);
    if ($rootReal === false || $fileReal === false || !is_file($fileReal) || !str_starts_with($fileReal, $rootReal . DIRECTORY_SEPARATOR)) {
        return false;
    }
    $types = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'text/javascript; charset=UTF-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff2' => 'font/woff2',
    ];
    $ext = strtolower(pathinfo($fileReal, PATHINFO_EXTENSION));
    if (!isset($types[$ext])) {
        return false;
    }
    header('Content-Type: ' . $types[$ext]);
    header('Cache-Control: public, max-age=86400');
    readfile($fileReal);

    return true;
}

if (request_is_pdv()) {
    proxy_to_pdv($_SERVER['REQUEST_URI'] ?? '/');
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rawurldecode($path);
if ($path !== '/' && serve_site_file($path)) {
    exit;
}

require dirname(__DIR__) . '/public/index.php';
