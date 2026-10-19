<?php

declare(strict_types=1);

namespace App\Setup;

use App\Core\Database;
use PDO;
use RuntimeException;

final class Migrator
{
    public static function up(): void
    {
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

        $files = glob(dirname(__DIR__, 2) . '/database/migrations/*.php') ?: [];
        sort($files);
        $applied = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $pending = array_values(array_filter(
            $files,
            static fn (string $file): bool => !in_array(pathinfo($file, PATHINFO_FILENAME), $applied, true)
        ));
        if ($pending === []) {
            return;
        }
        $batch = (int) $pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM schema_migrations')->fetchColumn();
        foreach ($pending as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $migration = require $file;
            if (!is_array($migration) || !isset($migration['up'])) {
                throw new RuntimeException('Migration inválida: ' . $name);
            }
            Database::transactional(static function () use ($migration, $pdo, $driver, $name, $batch): void {
                $migration['up']($pdo, $driver);
                $pdo->prepare(
                    'INSERT INTO schema_migrations (migration, batch, applied_at) VALUES (:name, :batch, :at)'
                )->execute(['name' => $name, 'batch' => $batch, 'at' => date('Y-m-d H:i:s')]);
            });
        }
    }
}
