<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();
$driver = Database::driver();
$id = $driver === 'mysql'
    ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'
    : 'INTEGER PRIMARY KEY AUTOINCREMENT';

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS schema_migrations (
        id {$id},
        migration VARCHAR(191) NOT NULL UNIQUE,
        batch INTEGER NOT NULL,
        applied_at DATETIME NOT NULL
    )"
);

$direction = $argv[1] ?? 'up';
$files = glob(dirname(__DIR__) . '/database/migrations/*.php') ?: [];
sort($files);

if ($direction === 'down') {
    $batch = (int) $pdo->query('SELECT COALESCE(MAX(batch), 0) FROM schema_migrations')->fetchColumn();
    if ($batch === 0) {
        echo "Nenhuma migration para reverter.\n";
        exit(0);
    }
    $stmt = $pdo->prepare('SELECT migration FROM schema_migrations WHERE batch = :batch ORDER BY id DESC');
    $stmt->execute(['batch' => $batch]);
    $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($names as $name) {
        $file = dirname(__DIR__) . '/database/migrations/' . $name . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Migration ausente: {$name}");
        }
        $migration = require $file;
        Database::transactional(static function () use ($migration, $pdo, $driver, $name): void {
            $migration['down']($pdo, $driver);
            $pdo->prepare('DELETE FROM schema_migrations WHERE migration = :name')->execute(['name' => $name]);
        });
        echo "Revertida: {$name}\n";
    }
    exit(0);
}

$applied = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$pending = array_values(array_filter($files, static fn (string $file): bool => !in_array(pathinfo($file, PATHINFO_FILENAME), $applied, true)));
if ($pending === []) {
    echo "Banco já está atualizado.\n";
    exit(0);
}

$batch = (int) $pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM schema_migrations')->fetchColumn();
foreach ($pending as $file) {
    $name = pathinfo($file, PATHINFO_FILENAME);
    $migration = require $file;
    Database::transactional(static function () use ($migration, $pdo, $driver, $name, $batch): void {
        $migration['up']($pdo, $driver);
        $pdo->prepare(
            'INSERT INTO schema_migrations (migration, batch, applied_at) VALUES (:name, :batch, :at)'
        )->execute(['name' => $name, 'batch' => $batch, 'at' => date('Y-m-d H:i:s')]);
    });
    echo "Aplicada: {$name}\n";
}

