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
        $birthDate = null;
        if ($type === 'customers') {
            $birthDate = $this->birthDate((string) ($_POST['birth_date'] ?? ''));
            if ($birthDate === false) {
                Session::flash('erro', 'Informe a data de nascimento do cliente.');
                View::redirect('/people?type=' . $type);
            }
        }
        if ($name === '' || ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            Session::flash('erro', 'Informe um nome e um e-mail válido.');
            View::redirect('/people?type=' . $type);
        }
        $pdo = Database::connection();
        $company = Auth::user()['company_id'];
        $now = date('Y-m-d H:i:s');
        try {
            if ($id) {
                if ($type === 'customers') {
                    $pdo->prepare(
                        "UPDATE customers SET name=:name,document=:document,email=:email,phone=:phone,birth_date=:birth,
                         notes=:notes,active=:active,updated_at=:updated WHERE id=:id AND company_id=:company"
                    )->execute([
                        'name' => $name, 'document' => $document, 'email' => $email, 'phone' => $phone,
                        'birth' => $birthDate,
                        'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
                        'active' => isset($_POST['active']) ? 1 : 0, 'updated' => $now, 'id' => $id, 'company' => $company,
                    ]);
                } else {
                    $pdo->prepare(
                        "UPDATE suppliers SET name=:name,document=:document,email=:email,phone=:phone,notes=:notes,
                         active=:active,updated_at=:updated WHERE id=:id AND company_id=:company"
                    )->execute([
                        'name' => $name, 'document' => $document, 'email' => $email, 'phone' => $phone,
                        'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
                        'active' => isset($_POST['active']) ? 1 : 0, 'updated' => $now, 'id' => $id, 'company' => $company,
                    ]);
                }
            } elseif ($type === 'customers') {
                $pdo->prepare(
                    "INSERT INTO customers (company_id,name,document,email,phone,birth_date,notes,active,created_at,updated_at)
                     VALUES (:company,:name,:document,:email,:phone,:birth,:notes,1,:created,:updated)"
                )->execute([
                    'company' => $company, 'name' => $name, 'document' => $document, 'email' => $email,
                    'phone' => $phone, 'birth' => $birthDate,
                    'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
                    'created' => $now, 'updated' => $now,
                ]);
                $id = (int) $pdo->lastInsertId();
            } else {
                $pdo->prepare(
                    "INSERT INTO suppliers (company_id,name,document,email,phone,notes,active,created_at,updated_at)
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

    /** @return string|false|null */
    private function birthDate(string $value): string|false|null
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors() ?: ['warning_count' => 0, 'error_count' => 0];
        if (!$date || $date->format('Y-m-d') !== $value || $errors['warning_count'] > 0 || $errors['error_count'] > 0) {
            return false;
        }
        $today = new DateTimeImmutable('today');
        if ($date > $today || $date < new DateTimeImmutable('1900-01-01')) {
            return false;
        }
        return $date->format('Y-m-d');
    }
}

