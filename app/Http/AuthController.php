<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Env;
use App\Core\Password;
use App\Core\Session;
use App\Core\View;

final class AuthController
{
    public function loginForm(): void
    {
        if (Auth::check()) {
            View::redirect('/');
        }
        View::render('auth/login', ['title' => 'Entrar'], 'guest');
    }

    public function login(): void
    {
        Csrf::enforce();
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if (trim((string) ($_POST['sp_hp'] ?? '')) !== '') {
            Session::flash('erro', 'E-mail ou senha inválidos.');
            View::redirect('/login');
        }
        try {
            if (Auth::attempt($email, $password, $_SERVER['REMOTE_ADDR'] ?? 'unknown')) {
                View::redirect('/', 'sucesso', 'Acesso realizado.');
            }
        } catch (\RuntimeException $exception) {
            Session::flash('erro', $exception->getMessage());
            View::redirect('/login');
        }
        Session::flash('erro', 'E-mail ou senha inválidos.');
        View::redirect('/login');
    }

    public function logout(): void
    {
        Csrf::enforce();
        Auth::logout();
        header('Location: /login', true, 303);
        exit;
    }

    public function forgotForm(): void
    {
        View::render('auth/forgot', ['title' => 'Recuperar senha'], 'guest');
    }

    public function forgot(): void
    {
        Csrf::enforce();
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(:email) AND active = 1');
        $stmt->execute(['email' => $email]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            $token = bin2hex(random_bytes(32));
            $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user')->execute(['user' => $id]);
            $pdo->prepare(
                'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at)
                 VALUES (:user, :hash, :expires, :created)'
            )->execute([
                'user' => $id,
                'hash' => hash('sha256', $token),
                'expires' => date('Y-m-d H:i:s', time() + 3600),
                'created' => date('Y-m-d H:i:s'),
            ]);
            if (Env::bool('APP_DEBUG', false)) {
                $url = rtrim(Env::get('APP_URL', 'http://localhost:8080') ?? '', '/') . '/reset?token=' . urlencode($token);
                file_put_contents(
                    \App\Core\Paths::writable('logs/mail.log'),
                    sprintf("[%s] Recuperação para %s: %s\n", date('c'), $email, $url),
                    FILE_APPEND | LOCK_EX
                );
            }
        }
        Session::flash('sucesso', 'Se o e-mail estiver cadastrado, as instruções foram enviadas.');
        View::redirect('/forgot');
    }

    public function resetForm(): void
    {
        View::render('auth/reset', [
            'title' => 'Definir nova senha',
            'token' => (string) ($_GET['token'] ?? ''),
        ], 'guest');
    }

    public function reset(): void
    {
        Csrf::enforce();
        $token = (string) ($_POST['token'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        try {
            Password::assertStrong($password);
        } catch (\InvalidArgumentException $exception) {
            Session::flash('erro', $exception->getMessage());
            View::redirect('/reset?token=' . urlencode($token));
        }
        if ($password !== $confirmation) {
            Session::flash('erro', 'As senhas devem coincidir.');
            View::redirect('/reset?token=' . urlencode($token));
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT * FROM password_reset_tokens
             WHERE token_hash = :hash AND used_at IS NULL AND expires_at >= :now LIMIT 1'
        );
        $stmt->execute(['hash' => hash('sha256', $token), 'now' => date('Y-m-d H:i:s')]);
        $reset = $stmt->fetch();
        if (!$reset) {
            Session::flash('erro', 'Link inválido ou expirado.');
            View::redirect('/forgot');
        }
        Database::transactional(static function () use ($pdo, $reset, $password): void {
            $pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = :at WHERE id = :id')->execute([
                'hash' => password_hash($password, PASSWORD_DEFAULT),
                'at' => date('Y-m-d H:i:s'),
                'id' => $reset['user_id'],
            ]);
            $pdo->prepare('UPDATE password_reset_tokens SET used_at = :at WHERE id = :id')->execute([
                'at' => date('Y-m-d H:i:s'),
                'id' => $reset['id'],
            ]);
            $pdo->prepare(
                'UPDATE user_sessions SET logged_out_at = :at, last_activity_at = :at
                 WHERE user_id = :user AND logged_out_at IS NULL'
            )->execute(['at' => date('Y-m-d H:i:s'), 'user' => $reset['user_id']]);
            Audit::log('auth.password_reset', 'users', (int) $reset['user_id']);
        });
        Session::flash('sucesso', 'Senha alterada. Entre novamente.');
        View::redirect('/login');
    }
}

