<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Core\Database;

/** Produtos mais vendidos e mais procurados a partir das vendas concluídas. */
final class ProductDemand
{
    public const LIMIT = 40;

    /** @return array{0:string,1:string} */
    public static function period(string $from, string $to): array
    {
        $today = date('Y-m-d');
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : $today;
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : $today;
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    /**
     * @return array{
     *   from:string,
     *   to:string,
     *   sales_count:int,
     *   sales_total:float,
     *   sold:list<array{description:string,qty:float,sales:int,total:float}>,
     *   sought:list<array{description:string,qty:float,sales:int,total:float}>
     * }
     */
    public static function report(int $company, int $branch, string $from, string $to): array
    {
        [$from, $to] = self::period($from, $to);
        $pdo = Database::connection();
        $params = [
            'company' => $company,
            'branch' => $branch,
            'from' => $from . ' 00:00:00',
            'to' => $to . ' 23:59:59',
        ];
        $summary = $pdo->prepare(
            "SELECT COUNT(*) AS sales_count, COALESCE(SUM(total), 0) AS sales_total
             FROM sales
             WHERE company_id = :company AND branch_id = :branch AND status = 'completed'
               AND completed_at >= :from AND completed_at <= :to"
        );
        $summary->execute($params);
        $head = $summary->fetch() ?: ['sales_count' => 0, 'sales_total' => 0];

        $items = $pdo->prepare(
            "SELECT MAX(si.description) AS description,
                    SUM(si.quantity - si.returned_quantity) AS qty,
                    COUNT(DISTINCT s.id) AS sales,
                    SUM(CASE WHEN si.quantity > 0
                        THEN si.subtotal * ((si.quantity - si.returned_quantity) / si.quantity)
                        ELSE 0 END) AS total
             FROM sale_items si
             JOIN sales s ON s.id = si.sale_id
             WHERE s.company_id = :company AND s.branch_id = :branch AND s.status = 'completed'
               AND s.completed_at >= :from AND s.completed_at <= :to
               AND si.quantity > si.returned_quantity
             GROUP BY si.product_id"
        );
        $items->execute($params);
        $rows = [];
        foreach ($items->fetchAll() as $row) {
            $rows[] = [
                'description' => (string) ($row['description'] ?? ''),
                'qty' => (float) $row['qty'],
                'sales' => (int) $row['sales'],
                'total' => round((float) $row['total'], 2),
            ];
        }
        $sold = $rows;
        usort($sold, static function (array $a, array $b): int {
            $qty = $b['qty'] <=> $a['qty'];
            return $qty !== 0 ? $qty : ($b['total'] <=> $a['total']);
        });
        $sought = $rows;
        usort($sought, static function (array $a, array $b): int {
            $sales = $b['sales'] <=> $a['sales'];
            return $sales !== 0 ? $sales : ($b['qty'] <=> $a['qty']);
        });

        return [
            'from' => $from,
            'to' => $to,
            'sales_count' => (int) $head['sales_count'],
            'sales_total' => round((float) $head['sales_total'], 2),
            'sold' => array_slice($sold, 0, self::LIMIT),
            'sought' => array_slice($sought, 0, self::LIMIT),
        ];
    }

    public static function qty(float $value): string
    {
        if (abs($value - round($value)) < 0.0001) {
            return number_format($value, 0, ',', '.');
        }

        return number_format($value, 3, ',', '.');
    }

    public static function money(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    public static function date(string $value): string
    {
        $time = strtotime($value);

        return $time ? date('d/m/Y', $time) : $value;
    }
}
