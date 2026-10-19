<?php

declare(strict_types=1);

$host = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? ''))[0]));
$host = (string) preg_replace('/:\d+$/', '', $host);
if ($host === 'pdv.lojadeeletronicossaojose.com.br') {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
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
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'O PDV não respondeu.';
        exit;
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
        header($line, false);
    }
    echo substr($response, $headerSize);
    exit;
}

require dirname(__DIR__) . '/public/index.php';
