<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use SessionHandlerInterface;

final class PdoSessionHandler implements SessionHandlerInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        $stmt = $this->pdo->prepare('SELECT data FROM php_sessions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetchColumn();
        return is_string($data) ? $data : '';
    }

    public function write(string $id, string $data): bool
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $stmt = $this->pdo->prepare(
                'INSERT INTO php_sessions (id, data, last_activity) VALUES (:id, :data, :at)
                 ON DUPLICATE KEY UPDATE data = VALUES(data), last_activity = VALUES(last_activity)'
            );
            return $stmt->execute(['id' => $id, 'data' => $data, 'at' => time()]);
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO php_sessions (id, data, last_activity) VALUES (:id, :data, :at)
             ON CONFLICT(id) DO UPDATE SET data = excluded.data, last_activity = excluded.last_activity'
        );
        return $stmt->execute(['id' => $id, 'data' => $data, 'at' => time()]);
    }

    public function destroy(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM php_sessions WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = $this->pdo->prepare('DELETE FROM php_sessions WHERE last_activity < :limit');
        $stmt->execute(['limit' => time() - $max_lifetime]);
        return $stmt->rowCount();
    }
}
