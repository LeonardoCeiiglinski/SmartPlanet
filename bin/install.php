<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Setup\Installer;
use App\Setup\Migrator;

$options = getopt('', ['company:', 'email:', 'password:', 'name::', 'document::']);
$company = trim((string) ($options['company'] ?? ''));
$email = trim((string) ($options['email'] ?? ''));
$password = (string) ($options['password'] ?? '');
$name = trim((string) ($options['name'] ?? 'Administrador'));
$document = (string) ($options['document'] ?? '');

if ($company === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
    fwrite(STDERR, "Uso: php bin/install.php --company=\"Empresa\" --email=\"admin@empresa.com\" --password=\"senha-com-10-ou-mais\" [--name=\"Nome\"] [--document=\"CNPJ\"]\n");
    exit(1);
}

try {
    Migrator::up();
    Installer::run($company, $email, $password, $name, $document ?: null);
    echo "Instalação concluída. Entre com {$email}.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit($exception instanceof RuntimeException ? 2 : 1);
}
