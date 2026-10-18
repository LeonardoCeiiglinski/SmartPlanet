<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;

final class UserController
{
    public function index(): void
    {
        Auth::requireMaster();
        $company = Auth::user()['company_id'];
        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.name AS role_name, b.name AS branch_name
             FROM users u JOIN roles r ON r.id=u.role_id JOIN branches b ON b.id=u.branch_id
             WHERE u.company_id=:company ORDER BY u.active DESC, u.name'
        );
        $stmt->execute(['company' => $company]);
        View::render('users/index', ['title' => 'Funcionários', 'users' => $stmt->fetchAll()]);
    }

    public function form(): void
    {
        Auth::requireMaster();
        $pdo = Database::connection();
        $company = Auth::user()['company_id'];
        $id = (int) ($_GET['id'] ?? 0);
        $user = null;
        if ($id) {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id=:id AND company_id=:company');
            $stmt->execute(['id' => $id, 'company' => $company]);
            $user = $stmt->fetch() ?: null;
        }
        $roles = $pdo->prepare("SELECT id,name FROM roles WHERE company_id=:company AND name <> 'Administrador' ORDER BY name");
        $branches = $pdo->prepare('SELECT id,name FROM branches WHERE company_id=:company AND active=1 ORDER BY name');
        $roles->execute(['company' => $company]);
        $branches->execute(['company' => $company]);
        View::render('users/form', [
            'title' => $id ? 'Editar funcionário' : 'Novo funcionário',
            'editingUser' => $user,
            'roles' => $roles->fetchAll(),
            'branches' => $branches->fetchAll(),
            'masterLocked' => $user !== null && (int) ($user['is_master'] ?? 0) === 1,
        ]);
    }

    public function save(): void
    {
        Auth::requireMaster();
        Csrf::enforce();
        $pdo = Database::connection();
        $company = (int) Auth::user()['company_id'];
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $role = (int) ($_POST['role_id'] ?? 0);
        $branch = (int) ($_POST['branch_id'] ?? 0);
        $existing = null;
        if ($id) {
            $current = $pdo->prepare('SELECT is_master, role_id FROM users WHERE id=:id AND company_id=:company');
            $current->execute(['id' => $id, 'company' => $company]);
            $existing = $current->fetch() ?: null;
            if ($existing === null) {
                throw new \RuntimeException('Funcionário não encontrado.');
            }
        }
        $masterLocked = $existing !== null && (int) $existing['is_master'] === 1;
        if ($masterLocked) {
            $role = (int) $existing['role_id'];
        }
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$role || !$branch || (!$id && strlen($password) < 10)) {
            Session::flash('erro', 'Preencha nome, e-mail, perfil, filial e senha inicial de ao menos 10 caracteres.');
            View::redirect($id ? '/users/edit?id=' . $id : '/users/new');
        }
        if (!$masterLocked) {
            $roleName = $pdo->prepare('SELECT name FROM roles WHERE id=:id AND company_id=:company');
            $roleName->execute(['id' => $role, 'company' => $company]);
            if ($roleName->fetchColumn() === 'Administrador') {
                Session::flash('erro', 'O perfil de administrador master pertence ao dono da loja e não é atribuído a funcionários.');
                View::redirect($id ? '/users/edit?id=' . $id : '/users/new');
            }
        }
        $now = date('Y-m-d H:i:s');
        try {
            if ($id) {
                $params = [
                    'name' => $name, 'email' => $email, 'role' => $role, 'branch' => $branch,
                    'active' => $masterLocked ? 1 : (isset($_POST['active']) ? 1 : 0), 'updated' => $now, 'id' => $id, 'company' => $company,
                ];
                $set = 'name=:name,email=:email,role_id=:role,branch_id=:branch,active=:active,updated_at=:updated';
                if ($password !== '') {
                    if (strlen($password) < 10) {
                        throw new \InvalidArgumentException('A nova senha deve ter ao menos 10 caracteres.');
                    }
                    $set .= ',password_hash=:password';
                    $params['password'] = password_hash($password, PASSWORD_DEFAULT);
                }
                $pdo->prepare("UPDATE users SET {$set} WHERE id=:id AND company_id=:company")->execute($params);
            } else {
                $pdo->prepare(
                    'INSERT INTO users (company_id,branch_id,role_id,name,email,password_hash,active,created_at,updated_at)
                     VALUES (:company,:branch,:role,:name,:email,:password,1,:created,:updated)'
                )->execute([
                    'company' => $company, 'branch' => $branch, 'role' => $role, 'name' => $name, 'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT), 'created' => $now, 'updated' => $now,
                ]);
                $id = (int) $pdo->lastInsertId();
            }
        } catch (\Throwable $exception) {
            Session::flash('erro', str_contains($exception->getMessage(), 'UNIQUE') ? 'E-mail já cadastrado.' : $exception->getMessage());
            View::redirect($id ? '/users/edit?id=' . $id : '/users/new');
        }
        Audit::log('user.saved', 'users', $id);
        View::redirect('/users', 'sucesso', $masterLocked ? 'Dados do administrador master atualizados.' : 'Login do funcionário salvo.');
    }
}

