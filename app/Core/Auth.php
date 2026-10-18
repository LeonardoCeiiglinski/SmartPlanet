<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Auth
{
    /** @var array<string, mixed>|null */
    private static ?array $user = null;

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $id = Session::get('user_id');
        if (!is_int($id) && !ctype_digit((string) $id)) {
            return null;
        }
        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id AND u.active = 1'
        );
        $stmt->execute(['id' => (int) $id]);
        $user = $stmt->fetch();
        self::$user = $user ?: null;
        return self::$user;
    }

    public static function attempt(string $email, string $password, string $ip): bool
    {
        $pdo = Database::connection();
        self::guardAttempts($email, $ip);

        $stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(email) = LOWER(:email) AND active = 1 LIMIT 1');
        $stmt->execute(['email' => trim($email)]);
        $user = $stmt->fetch();
        $ok = is_array($user) && password_verify($password, (string) $user['password_hash']);

        $log = $pdo->prepare(
            'INSERT INTO login_attempts (email, ip_address, succeeded, attempted_at)
             VALUES (:email, :ip, :succeeded, :at)'
        );
        $log->execute([
            'email' => mb_strtolower(trim($email)),
            'ip' => $ip,
            'succeeded' => $ok ? 1 : 0,
            'at' => date('Y-m-d H:i:s'),
        ]);

        if (!$ok) {
            return false;
        }

        Session::regenerate();
        Session::put('user_id', (int) $user['id']);
        Session::put('branch_id', (int) $user['branch_id']);
        self::$user = null;
        $sessionHash = hash('sha256', session_id());
        Session::put('_session_hash', $sessionHash);
        $pdo->prepare('UPDATE users SET last_login_at = :at WHERE id = :id')->execute([
            'at' => date('Y-m-d H:i:s'),
            'id' => (int) $user['id'],
        ]);
        $pdo->prepare(
            'INSERT INTO user_sessions
             (user_id, session_hash, ip_address, user_agent, last_activity_at, created_at)
             VALUES (:user, :hash, :ip, :agent, :activity, :created)'
        )->execute([
            'user' => (int) $user['id'],
            'hash' => $sessionHash,
            'ip' => $ip,
            'agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'activity' => date('Y-m-d H:i:s'),
            'created' => date('Y-m-d H:i:s'),
        ]);
        Audit::log('auth.login', 'users', (int) $user['id']);
        return true;
    }

    public static function logout(): void
    {
        $user = self::user();
        if ($user !== null) {
            Audit::log('auth.logout', 'users', (int) $user['id']);
            $hash = Session::get('_session_hash');
            if (is_string($hash)) {
                Database::connection()->prepare(
                    'UPDATE user_sessions SET logged_out_at=:at,last_activity_at=:at WHERE session_hash=:hash'
                )->execute(['at' => date('Y-m-d H:i:s'), 'hash' => $hash]);
            }
        }
        self::$user = null;
        Session::destroy();
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            Session::flash('erro', 'Entre para acessar o sistema.');
            header('Location: /login');
            exit;
        }
    }

    public static function can(string $permission): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }
        if (($user['role_name'] ?? '') === 'Administrador') {
            return true;
        }
        $stmt = Database::connection()->prepare(
            'SELECT 1
             FROM role_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = :role AND p.code = :code
             LIMIT 1'
        );
        $stmt->execute(['role' => (int) $user['role_id'], 'code' => $permission]);
        return (bool) $stmt->fetchColumn();
    }

    public static function authorize(string $permission): void
    {
        self::requireLogin();
        if (!self::can($permission)) {
            http_response_code(403);
            throw new \RuntimeException('Você não possui permissão para executar esta operação.');
        }
    }

    private static function guardAttempts(string $email, string $ip): void
    {
        $minutes = Env::int('LOGIN_LOCK_MINUTES', 15);
        $max = Env::int('LOGIN_MAX_ATTEMPTS', 5);
        $since = date('Y-m-d H:i:s', time() - $minutes * 60);
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE succeeded = 0 AND attempted_at >= :since
               AND (LOWER(email) = LOWER(:email) OR ip_address = :ip)'
        );
        $stmt->execute(['since' => $since, 'email' => trim($email), 'ip' => $ip]);
        if ((int) $stmt->fetchColumn() >= $max) {
            throw new \RuntimeException("Muitas tentativas. Aguarde {$minutes} minutos.");
        }
    }
}

