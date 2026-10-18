<?php

declare(strict_types=1);

namespace App\Core;

final class Audit
{
    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    public static function log(
        string $action,
        string $entityType,
        int|string|null $entityId = null,
        ?array $before = null,
        ?array $after = null
    ): void {
        $user = Auth::user();
        $stmt = Database::connection()->prepare(
            'INSERT INTO audit_logs
             (company_id, branch_id, user_id, action, entity_type, entity_id, before_json, after_json, ip_address, user_agent, created_at)
             VALUES (:company, :branch, :user, :action, :type, :entity, :before, :after, :ip, :agent, :created)'
        );
        $stmt->execute([
            'company' => $user['company_id'] ?? null,
            'branch' => $user['branch_id'] ?? null,
            'user' => $user['id'] ?? null,
            'action' => $action,
            'type' => $entityType,
            'entity' => $entityId === null ? null : (string) $entityId,
            'before' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'after' => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli',
            'agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'cli', 0, 500),
            'created' => date('Y-m-d H:i:s'),
        ]);
    }
}

