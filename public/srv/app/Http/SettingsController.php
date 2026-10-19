<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;

final class SettingsController
{
    public function index(): void
    {
        Auth::authorize('settings.manage');
        $pdo = Database::connection();
        $companyId = Auth::user()['company_id'];
        $company = $pdo->prepare('SELECT * FROM companies WHERE id=:id');
        $company->execute(['id' => $companyId]);
        $data = ['title' => 'Configurações', 'company' => $company->fetch()];
        foreach (['categories', 'brands', 'units'] as $table) {
            $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE company_id=:company ORDER BY name");
            $stmt->execute(['company' => $companyId]);
            $data[$table] = $stmt->fetchAll();
        }
        View::render('settings/index', $data);
    }

    public function company(): void
    {
        Auth::authorize('settings.manage');
        Csrf::enforce();
        $legal = trim((string) ($_POST['legal_name'] ?? ''));
        $trade = trim((string) ($_POST['trade_name'] ?? ''));
        $document = preg_replace('/\D+/', '', (string) ($_POST['document'] ?? '')) ?: null;
        if ($legal === '' || $trade === '') {
            Session::flash('erro', 'Razão social e nome fantasia são obrigatórios.');
            View::redirect('/settings');
        }
        Database::connection()->prepare(
            'UPDATE companies SET legal_name=:legal,trade_name=:trade,document=:document,email=:email,phone=:phone,updated_at=:at WHERE id=:id'
        )->execute([
            'legal' => $legal, 'trade' => $trade, 'document' => $document,
            'email' => trim((string) ($_POST['email'] ?? '')) ?: null,
            'phone' => trim((string) ($_POST['phone'] ?? '')) ?: null,
            'at' => date('Y-m-d H:i:s'), 'id' => Auth::user()['company_id'],
        ]);
        Audit::log('company.updated', 'companies', Auth::user()['company_id']);
        View::redirect('/settings', 'sucesso', 'Empresa atualizada.');
    }

    public function dictionary(): void
    {
        Auth::authorize('settings.manage');
        Csrf::enforce();
        $type = (string) ($_POST['dictionary'] ?? '');
        $allowed = ['categories', 'brands', 'units'];
        if (!in_array($type, $allowed, true)) {
            throw new \InvalidArgumentException('Cadastro auxiliar inválido.');
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
        if ($name === '' || ($type === 'units' && $code === '')) {
            Session::flash('erro', 'Preencha os campos obrigatórios.');
            View::redirect('/settings');
        }
        $pdo = Database::connection();
        try {
            if ($type === 'units') {
                $pdo->prepare(
                    'INSERT INTO units (company_id,code,name,decimal_places,active) VALUES (:company,:code,:name,:decimals,1)'
                )->execute([
                    'company' => Auth::user()['company_id'], 'code' => $code, 'name' => $name,
                    'decimals' => max(0, min(4, (int) ($_POST['decimal_places'] ?? 0))),
                ]);
            } else {
                $pdo->prepare("INSERT INTO {$type} (company_id,name,active) VALUES (:company,:name,1)")
                    ->execute(['company' => Auth::user()['company_id'], 'name' => $name]);
            }
        } catch (\Throwable $exception) {
            Session::flash('erro', 'Esse cadastro já existe.');
            View::redirect('/settings');
        }
        Audit::log('dictionary.created', $type, (int) $pdo->lastInsertId());
        View::redirect('/settings', 'sucesso', 'Cadastro auxiliar incluído.');
    }

    public function audit(): void
    {
        Auth::authorize('audit.view');
        $stmt = Database::connection()->prepare(
            'SELECT a.*,u.name AS user_name FROM audit_logs a
             LEFT JOIN users u ON u.id=a.user_id WHERE a.company_id=:company
             ORDER BY a.id DESC LIMIT 500'
        );
        $stmt->execute(['company' => Auth::user()['company_id']]);
        View::render('audit/index', ['title' => 'Auditoria', 'logs' => $stmt->fetchAll()]);
    }
}

