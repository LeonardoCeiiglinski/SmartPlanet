<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Auth
{
    private const DUMMY_HASH = '$2y$12$arXOdB8ZT16BAfJUPI9zB.h3psOb1LtfjbBc6b5OJJ9wl.vUZ7mo6';

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
        if (!$user || !self::sessionIsCurrent((int) $user['id'])) {
            self::$user = null;
            return null;
        }
        self::$user = $user;
        return self::$user;
    }

    public static function attempt(string $email, string $password, string $ip): bool
    {
        $pdo = Database::connection();
        self::guardAttempts($email, $ip);

        $email = mb_strtolower(trim($email));
        if ($email === '' || strlen($email) > 191 || strlen($password) === 0 || strlen($password) > 200) {
            password_verify($password !== '' ? $password : 'invalid', self::DUMMY_HASH);
            $pdo->prepare(
                'INSERT INTO login_attempts (email, ip_address, succeeded, attempted_at)
                 VALUES (:email, :ip, 0, :at)'
            )->execute([
                'email' => substr($email, 0, 191),
                'ip' => $ip,
                'at' => date('Y-m-d H:i:s'),
            ]);
            return false;
        }
        $stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(email) = LOWER(:email) AND active = 1 LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        $hash = is_array($user) ? (string) $user['password_hash'] : self::DUMMY_HASH;
        $ok = is_array($user) && password_verify($password, $hash);
        if ($ok && password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = :at WHERE id = :id')->execute([
                'hash' => password_hash($password, PASSWORD_DEFAULT),
                'at' => date('Y-m-d H:i:s'),
                'id' => (int) $user['id'],
            ]);
        }

        $log = $pdo->prepare(
            'INSERT INTO login_attempts (email, ip_address, succeeded, attempted_at)
             VALUES (:email, :ip, :succeeded, :at)'
        );
        $log->execute([
            'email' => $email,
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
        Session::put('_client', hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')));
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

    public static function reset(): void
    {
        self::$user = null;
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

    public static function isMaster(): bool
    {
        $user = self::user();
        return $user !== null && (int) ($user['is_master'] ?? 0) === 1;
    }

    public static function requireMaster(): void
    {
        self::requireLogin();
        if (!self::isMaster()) {
            if (!headers_sent()) {
                http_response_code(403);
            }
            throw new \RuntimeException('Somente o administrador master, dono da loja, configura o login dos funcionários.');
        }
    }

    public static function authorize(string $permission): void
    {
        self::requireLogin();
        if (!self::can($permission)) {
            if (!headers_sent()) {
                http_response_code(403);
            }
            throw new \RuntimeException('Você não possui permissão para executar esta operação.');
        }
    }

    private static function guardAttempts(string $email, string $ip): void
    {
        $minutes = Env::int('LOGIN_LOCK_MINUTES', 15);
        $max = Env::int('LOGIN_MAX_ATTEMPTS', 5);
        $since = date('Y-m-d H:i:s', time() - $minutes * 60);
        $pdo = Database::connection();
        $byEmail = $pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE succeeded = 0 AND attempted_at >= :since AND LOWER(email) = LOWER(:email)'
        );
        $byEmail->execute(['since' => $since, 'email' => trim($email)]);
        $byIp = $pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE succeeded = 0 AND attempted_at >= :since AND ip_address = :ip'
        );
        $byIp->execute(['since' => $since, 'ip' => $ip]);
        if ((int) $byEmail->fetchColumn() >= $max || (int) $byIp->fetchColumn() >= ($max * 6)) {
            throw new \RuntimeException("Muitas tentativas. Aguarde {$minutes} minutos.");
        }
    }

    private static function sessionIsCurrent(int $userId): bool
    {
        $fingerprint = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $storedFingerprint = Session::get('_client');
        $sessionHash = Session::get('_session_hash');
        if (is_string($storedFingerprint) && !hash_equals($storedFingerprint, $fingerprint)) {
            self::dropSession();
            return false;
        }
        if (!is_string($sessionHash) || $sessionHash === '') {
            return true;
        }
        $stmt = Database::connection()->prepare(
            'SELECT 1 FROM user_sessions
             WHERE session_hash = :hash AND user_id = :user AND logged_out_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['hash' => $sessionHash, 'user' => $userId]);
        if (!$stmt->fetchColumn()) {
            self::dropSession();
            return false;
        }
        Database::connection()->prepare(
            'UPDATE user_sessions SET last_activity_at = :at WHERE session_hash = :hash'
        )->execute(['at' => date('Y-m-d H:i:s'), 'hash' => $sessionHash]);
        return true;
    }

    private static function dropSession(): void
    {
        self::$user = null;
        if (session_status() === PHP_SESSION_ACTIVE) {
            Session::destroy();
        }
    }
}

