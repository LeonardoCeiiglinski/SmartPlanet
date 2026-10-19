<?php

declare(strict_types=1);

/**
 * Um projeto na Vercel, dois endereços:
 * pdv.lojadeeletronicossaojose.com.br → sistema
 * lojadeeletronicossaojose.com.br (e www) → site da loja
 * Nos demais hosts, DEFAULT_APP=site mostra a loja; qualquer outro valor mostra o sistema.
 */

function smartplanet_request_host(): string
{
    $raw = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '';
    $raw = trim(explode(',', (string) $raw)[0]);

    return strtolower((string) preg_replace('/:\d+$/', '', $raw));
}

function smartplanet_is_pdv(string $host): bool
{
    return $host === 'pdv.lojadeeletronicossaojose.com.br';
}

function smartplanet_is_store(string $host): bool
{
    return $host === 'lojadeeletronicossaojose.com.br' || $host === 'www.lojadeeletronicossaojose.com.br';
}

function smartplanet_serve_file(string $file): bool
{
    if (!is_file($file)) {
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
        'txt' => 'text/plain; charset=UTF-8',
        'xml' => 'application/xml; charset=UTF-8',
    ];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!isset($types[$ext])) {
        return false;
    }
    header('Content-Type: ' . $types[$ext]);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=86400');
    readfile($file);

    return true;
}

function smartplanet_public_file(string $root, string $path): ?string
{
    if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
        return null;
    }
    $file = $root . '/' . ltrim($path, '/');
    $rootReal = realpath($root);
    $fileReal = realpath($file);
    if ($rootReal === false || $fileReal === false || !str_starts_with($fileReal, $rootReal . DIRECTORY_SEPARATOR)) {
        return null;
    }

    return $fileReal;
}

$host = smartplanet_request_host();
$pdv = smartplanet_is_pdv($host);
$store = smartplanet_is_store($host);
if (!$pdv && !$store) {
    $default = getenv('DEFAULT_APP');
    $store = is_string($default) && $default === 'site';
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rawurldecode($path);

if ($store && ($path === '/favicon.ico' || $path === '/favicon.png')) {
    $logo = dirname(__DIR__) . '/website/public/assets/img/logo.png';
    if (smartplanet_serve_file($logo)) {
        return;
    }
}

if ($pdv && ($path === '/favicon.ico' || $path === '/favicon.png')) {
    $icon = dirname(__DIR__) . '/public/favicon.png';
    if (smartplanet_serve_file($icon)) {
        return;
    }
}

$staticRoot = $store
    ? dirname(__DIR__) . '/website/public'
    : dirname(__DIR__) . '/public';
$static = smartplanet_public_file($staticRoot, $path);
if ($static !== null && smartplanet_serve_file($static)) {
    return;
}

if (!$store) {
    $extra = smartplanet_public_file(dirname(__DIR__), $path);
    if ($extra !== null && smartplanet_serve_file($extra)) {
        return;
    }
}

if ($store) {
    require dirname(__DIR__) . '/website/public/index.php';
    return;
}

require dirname(__DIR__) . '/public/index.php';
