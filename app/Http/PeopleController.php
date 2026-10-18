<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;

final class PeopleController
{
    public function index(): void
    {
        $type = $this->type();
        Auth::authorize($type === 'customers' ? 'customers.manage' : 'suppliers.manage');
        $query = trim((string) ($_GET['q'] ?? ''));
        $sql = "SELECT * FROM {$type} WHERE company_id=:company";
        $params = ['company' => Auth::user()['company_id']];
        if ($query !== '') {
            $like = '%' . mb_strtolower($query) . '%';
            $sql .= ' AND (LOWER(name) LIKE :query OR document LIKE :raw OR LOWER(email) LIKE :query_email)';
            $params['query'] = $like;
            $params['query_email'] = $like;
            $params['raw'] = '%' . preg_replace('/\D+/', '', $query) . '%';
        }
        $sql .= ' ORDER BY active DESC, name LIMIT 200';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        View::render('people/index', [
            'title' => $type === 'customers' ? 'Clientes' : 'Fornecedores',
            'type' => $type,
            'items' => $stmt->fetchAll(),
            'query' => $query,
        ]);
    }

    public function save(): void
    {
        $type = $this->type();
        Auth::authorize($type === 'customers' ? 'customers.manage' : 'suppliers.manage');
        Csrf::enforce();
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $document = preg_replace('/\D+/', '', (string) ($_POST['document'] ?? '')) ?: null;
        $email = trim((string) ($_POST['email'] ?? '')) ?: null;
        $phone = trim((string) ($_POST['phone'] ?? '')) ?: null;
        if ($name === '' || ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            Session::flash('erro', 'Informe um nome e um e-mail válido.');
            View::redirect('/people?type=' . $type);
        }
        $pdo = Database::connection();
        $company = Auth::user()['company_id'];
        $now = date('Y-m-d H:i:s');
        try {
            if ($id) {
                $pdo->prepare(
                    "UPDATE {$type} SET name=:name,document=:document,email=:email,phone=:phone,notes=:notes,
                     active=:active,updated_at=:updated WHERE id=:id AND company_id=:company"
                )->execute([
                    'name' => $name, 'document' => $document, 'email' => $email, 'phone' => $phone,
                    'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
                    'active' => isset($_POST['active']) ? 1 : 0, 'updated' => $now, 'id' => $id, 'company' => $company,
                ]);
            } else {
                $pdo->prepare(
                    "INSERT INTO {$type} (company_id,name,document,email,phone,notes,active,created_at,updated_at)
                     VALUES (:company,:name,:document,:email,:phone,:notes,1,:created,:updated)"
                )->execute([
                    'company' => $company, 'name' => $name, 'document' => $document, 'email' => $email,
                    'phone' => $phone, 'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
                    'created' => $now, 'updated' => $now,
                ]);
                $id = (int) $pdo->lastInsertId();
            }
        } catch (\Throwable $exception) {
            Session::flash('erro', str_contains($exception->getMessage(), 'UNIQUE') ? 'Documento já cadastrado.' : $exception->getMessage());
            View::redirect('/people?type=' . $type);
        }
        Audit::log(rtrim($type, 's') . '.saved', $type, $id);
        View::redirect('/people?type=' . $type, 'sucesso', 'Cadastro salvo.');
    }

    private function type(): string
    {
        return ($_GET['type'] ?? $_POST['type'] ?? 'customers') === 'suppliers' ? 'suppliers' : 'customers';
    }
}

