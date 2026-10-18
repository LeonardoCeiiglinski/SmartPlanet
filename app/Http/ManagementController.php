<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Domain\Management\ManagementService;

final class ManagementController
{
    public function __construct(private readonly ManagementService $service = new ManagementService())
    {
    }

    public function quotes(): void
    {
        Auth::authorize('sales.view');
        $u = Auth::user();
        $pdo = Database::connection();
        $quotes = $pdo->prepare(
            'SELECT q.*, c.name customer_name FROM quotes q
             LEFT JOIN customers c ON c.id = q.customer_id
             WHERE q.company_id=:company AND q.branch_id=:branch ORDER BY q.id DESC LIMIT 200'
        );
        $quotes->execute(['company' => $u['company_id'], 'branch' => $u['branch_id']]);
        $products = $pdo->prepare('SELECT id, description, sale_price FROM products WHERE company_id=:company AND active=1 ORDER BY description LIMIT 500');
        $products->execute(['company' => $u['company_id']]);
        $customers = $pdo->prepare('SELECT id, name FROM customers WHERE company_id=:company AND active=1 ORDER BY name LIMIT 500');
        $customers->execute(['company' => $u['company_id']]);
        $methods = $pdo->prepare('SELECT id, name FROM payment_methods WHERE company_id=:company AND active=1 ORDER BY id');
        $methods->execute(['company' => $u['company_id']]);
        $detail = null;
        if (isset($_GET['id'])) {
            $q = $pdo->prepare('SELECT * FROM quotes WHERE id=:id AND company_id=:company AND branch_id=:branch');
            $q->execute(['id' => (int) $_GET['id'], 'company' => $u['company_id'], 'branch' => $u['branch_id']]);
            $detail = $q->fetch() ?: null;
            if ($detail) {
                $i = $pdo->prepare('SELECT * FROM quote_items WHERE quote_id=:id');
                $i->execute(['id' => $detail['id']]);
                $detail['items'] = $i->fetchAll();
            }
        }
        View::render('quotes/index', [
            'title' => 'Orçamentos',
            'quotes' => $quotes->fetchAll(),
            'products' => $products->fetchAll(),
            'customers' => $customers->fetchAll(),
            'methods' => $methods->fetchAll(),
            'detail' => $detail,
        ]);
    }

    public function quoteCreate(): void
    {
        Auth::authorize('sales.view');
        Csrf::enforce();
        try {
            $items = [];
            foreach ((array) ($_POST['items'] ?? []) as $line) {
                if (!is_array($line) || (int) ($line['product_id'] ?? 0) <= 0) {
                    continue;
                }
                $items[] = [
                    'product_id' => (int) $line['product_id'],
                    'quantity' => (string) ($line['quantity'] ?? '1'),
                    'discount' => (string) ($line['discount'] ?? '0'),
                ];
            }
            $id = $this->service->createQuote(
                (string) ($_POST['idempotency_key'] ?? ''),
                $items,
                (int) ($_POST['customer_id'] ?? 0) ?: null,
                trim((string) ($_POST['valid_until'] ?? '')) ?: null,
                (string) ($_POST['notes'] ?? '')
            );
            View::redirect('/quotes?id=' . $id, 'sucesso', 'Orçamento gravado.');
        } catch (\Throwable $e) {
            Session::flash('erro', $e->getMessage());
            View::redirect('/quotes');
        }
    }

    public function quoteConvert(): void
    {
        Auth::authorize('pos.sell');
        Csrf::enforce();
        try {
            $payments = [[
                'method_id' => (int) ($_POST['method_id'] ?? 0),
                'amount' => (string) ($_POST['amount'] ?? '0'),
                'confirmed' => 1,
            ]];
            $sale = $this->service->convertQuote((int) ($_POST['quote_id'] ?? 0), (string) ($_POST['idempotency_key'] ?? ''), $payments);
            View::redirect('/sales?receipt=' . $sale, 'sucesso', 'Orçamento convertido em venda.');
        } catch (\Throwable $e) {
            Session::flash('erro', $e->getMessage());
            View::redirect('/quotes');
        }
    }

    public function finance(): void
    {
        Auth::authorize('finance.view');
        $u = Auth::user();
        $pdo = Database::connection();
        $entries = $pdo->prepare(
            'SELECT e.*, c.name customer_name, s.name supplier_name FROM financial_entries e
             LEFT JOIN customers c ON c.id=e.customer_id LEFT JOIN suppliers s ON s.id=e.supplier_id
             WHERE e.company_id=:company AND e.branch_id=:branch ORDER BY e.due_date, e.id DESC LIMIT 300'
        );
        $entries->execute(['company' => $u['company_id'], 'branch' => $u['branch_id']]);
        $accounts = $pdo->prepare('SELECT id,name FROM financial_accounts WHERE company_id=:company AND active=1 AND (branch_id IS NULL OR branch_id=:branch)');
        $accounts->execute(['company' => $u['company_id'], 'branch' => $u['branch_id']]);
        $customers = $pdo->prepare('SELECT id,name FROM customers WHERE company_id=:company AND active=1 ORDER BY name');
        $customers->execute(['company' => $u['company_id']]);
        $suppliers = $pdo->prepare('SELECT id,name FROM suppliers WHERE company_id=:company AND active=1 ORDER BY name');
        $suppliers->execute(['company' => $u['company_id']]);
        $openIn = 0.0;
        $openOut = 0.0;
        $rows = $entries->fetchAll();
        foreach ($rows as $row) {
            if (!in_array($row['status'], ['open', 'partial'], true)) {
                continue;
            }
            $remaining = (float) $row['amount'] - (float) $row['settled_amount'];
            if ($row['direction'] === 'in') {
                $openIn += $remaining;
            } else {
                $openOut += $remaining;
            }
        }
        View::render('finance/index', [
            'title' => 'Financeiro',
            'entries' => $rows,
            'accounts' => $accounts->fetchAll(),
            'customers' => $customers->fetchAll(),
            'suppliers' => $suppliers->fetchAll(),
            'openIn' => $openIn,
            'openOut' => $openOut,
        ]);
    }

    public function financeCreate(): void
    {
        Auth::authorize('finance.manage');
        Csrf::enforce();
        try {
            $direction = (string) ($_POST['direction'] ?? '');
            $party = (int) ($_POST['party_id'] ?? 0) ?: null;
            $id = $this->service->createEntry(
                $direction,
                (string) ($_POST['description'] ?? ''),
                (string) ($_POST['amount'] ?? ''),
                (string) ($_POST['issue_date'] ?? date('Y-m-d')),
                (string) ($_POST['due_date'] ?? date('Y-m-d')),
                $party
            );
            View::redirect('/finance', 'sucesso', 'Lançamento ' . $id . ' criado.');
        } catch (\Throwable $e) {
            Session::flash('erro', $e->getMessage());
            View::redirect('/finance');
        }
    }

    public function financeSettle(): void
    {
        Auth::authorize('finance.manage');
        Csrf::enforce();
        try {
            $this->service->settle(
                (int) ($_POST['entry_id'] ?? 0),
                (int) ($_POST['account_id'] ?? 0),
                (string) ($_POST['amount'] ?? ''),
                (string) ($_POST['idempotency_key'] ?? ''),
                (string) ($_POST['notes'] ?? '')
            );
            View::redirect('/finance', 'sucesso', 'Liquidação registrada.');
        } catch (\Throwable $e) {
            Session::flash('erro', $e->getMessage());
            View::redirect('/finance');
        }
    }

    public function returnSale(): void
    {
        Auth::authorize('sales.return');
        Csrf::enforce();
        try {
            $items = [];
            foreach ((array) ($_POST['items'] ?? []) as $line) {
                if (!is_array($line) || empty($line['selected'])) {
                    continue;
                }
                $items[] = [
                    'sale_item_id' => (int) ($line['sale_item_id'] ?? 0),
                    'quantity' => (string) ($line['quantity'] ?? '0'),
                    'return_to_stock' => !empty($line['return_to_stock']),
                ];
            }
            $id = $this->service->returnSale(
                (int) ($_POST['sale_id'] ?? 0),
                (string) ($_POST['idempotency_key'] ?? ''),
                $items,
                (string) ($_POST['reason'] ?? ''),
                !empty($_POST['refund_cash'])
            );
            View::redirect('/sales?receipt=' . (int) ($_POST['sale_id'] ?? 0), 'sucesso', 'Devolução ' . $id . ' confirmada.');
        } catch (\Throwable $e) {
            Session::flash('erro', $e->getMessage());
            View::redirect('/sales');
        }
    }
}
