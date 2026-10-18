<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

final class ReportController
{
    public function index(): void
    {
        Auth::authorize('reports.view');
        $u = Auth::user();
        $from = $this->date($_GET['from'] ?? date('Y-m-01'));
        $to = $this->date($_GET['to'] ?? date('Y-m-d'));
        $end = $to . ' 23:59:59';
        $start = $from . ' 00:00:00';
        $pdo = Database::connection();
        $params = ['company' => $u['company_id'], 'branch' => $u['branch_id'], 'from' => $start, 'to' => $end];

        $sales = $this->rows($pdo,
            "SELECT DATE(completed_at) day, COUNT(*) qty, SUM(total) total
             FROM sales WHERE company_id=:company AND branch_id=:branch AND status='completed'
             AND completed_at BETWEEN :from AND :to GROUP BY DATE(completed_at) ORDER BY day",
            $params
        );
        $products = $this->rows($pdo,
            "SELECT si.description, SUM(si.quantity) qty, SUM(si.subtotal) total
             FROM sale_items si JOIN sales s ON s.id=si.sale_id
             WHERE s.company_id=:company AND s.branch_id=:branch AND s.status='completed'
             AND s.completed_at BETWEEN :from AND :to
             GROUP BY si.product_id, si.description ORDER BY total DESC LIMIT 50",
            $params
        );
        $operators = $this->rows($pdo,
            "SELECT u.name, COUNT(*) qty, SUM(s.total) total FROM sales s JOIN users u ON u.id=s.operator_id
             WHERE s.company_id=:company AND s.branch_id=:branch AND s.status='completed'
             AND s.completed_at BETWEEN :from AND :to GROUP BY s.operator_id, u.name ORDER BY total DESC",
            $params
        );
        $payments = $this->rows($pdo,
            "SELECT pm.name, SUM(sp.amount) total FROM sale_payments sp
             JOIN sales s ON s.id=sp.sale_id JOIN payment_methods pm ON pm.id=sp.payment_method_id
             WHERE s.company_id=:company AND s.branch_id=:branch AND s.status='completed'
             AND s.completed_at BETWEEN :from AND :to GROUP BY pm.id, pm.name ORDER BY total DESC",
            $params
        );
        $lowStock = $this->rows($pdo,
            "SELECT p.description, p.minimum_stock,
                    COALESCE((SELECT SUM(quantity) FROM stock_movements m WHERE m.product_id=p.id AND m.company_id=p.company_id),0) stock
             FROM products p
             WHERE p.company_id=:company AND p.track_stock=1 AND p.active=1
               AND COALESCE((SELECT SUM(quantity) FROM stock_movements m WHERE m.product_id=p.id AND m.company_id=p.company_id),0) <= p.minimum_stock
             ORDER BY stock, p.description",
            ['company' => $u['company_id']]
        );
        $returns = $this->rows($pdo,
            "SELECT r.return_number, r.created_at, r.total, r.reason, s.sale_number
             FROM sale_returns r JOIN sales s ON s.id=r.sale_id
             WHERE r.company_id=:company AND r.branch_id=:branch AND r.created_at BETWEEN :from AND :to
             ORDER BY r.id DESC",
            $params
        );
        $cash = $this->rows($pdo,
            "SELECT cs.opened_at, cs.closed_at, cs.opening_amount, cs.expected_cash, cs.counted_cash, cs.difference_amount, cs.closing_notes, cr.name register_name, u.name operator_name
             FROM cash_sessions cs JOIN cash_registers cr ON cr.id=cs.cash_register_id JOIN users u ON u.id=cs.user_id
             WHERE cs.company_id=:company AND cs.branch_id=:branch AND cs.opened_at BETWEEN :from AND :to
             ORDER BY cs.id DESC",
            $params
        );

        if (($_GET['export'] ?? '') === 'csv') {
            $this->csv('relatorio-vendas-' . $from . '-' . $to . '.csv', ['Dia', 'Vendas', 'Total'], $sales, static fn (array $r): array => [$r['day'], $r['qty'], $r['total']]);
        }

        View::render('reports/index', [
            'title' => 'Relatórios',
            'from' => $from,
            'to' => $to,
            'sales' => $sales,
            'products' => $products,
            'operators' => $operators,
            'payments' => $payments,
            'lowStock' => $lowStock,
            'returns' => $returns,
            'cash' => $cash,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function rows(\PDO $pdo, string $sql, array $params): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function date(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : date('Y-m-d');
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function csv(string $name, array $header, array $rows, callable $map): never
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        $out = fopen('php://output', 'w');
        if ($out === false) {
            throw new \RuntimeException('Não foi possível exportar o CSV.');
        }
        fputcsv($out, $header, ';');
        foreach ($rows as $row) {
            fputcsv($out, $map($row), ';');
        }
        exit;
    }
}
