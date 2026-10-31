<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Support\A4Pdf;

/** PDF A4 da listagem de produtos mais vendidos e mais procurados. */
final class ProductDemandDocument
{
    private const LEFT = 36.0;
    private const RIGHT = 559.0;
    private const GOLD = [0.776, 0.631, 0.353];
    private const INK = [0.067, 0.067, 0.067];
    private const MUTED = [0.427, 0.392, 0.337];
    private const BAND = [0.965, 0.953, 0.925];

    /** @param array<string,mixed> $report */
    public static function render(array $report): string
    {
        $pdf = new A4Pdf();
        $doc = new self($pdf, $report);
        $doc->draw();

        return $pdf->output('Vendas ' . ProductDemand::date((string) ($report['from'] ?? '')) . ' a ' . ProductDemand::date((string) ($report['to'] ?? '')));
    }

    /** @param array<string,mixed> $report */
    private function __construct(private readonly A4Pdf $pdf, private readonly array $report)
    {
    }

    private int $page = 1;

    private function draw(): void
    {
        $this->pdf->addPage();
        $top = $this->header();
        $top = $this->table($top, 'Mais vendidos', 'Quantidade vendida no período, da maior para a menor.', $this->rows('sold'), 'qty');
        $this->table($top, 'Mais procurados', 'Produtos que entraram em mais vendas, mesmo com poucas unidades por venda.', $this->rows('sought'), 'sales');
        $this->footer();
    }

    /** @return list<array<string,mixed>> */
    private function rows(string $key): array
    {
        $rows = $this->report[$key] ?? [];

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    private function header(): float
    {
        $from = ProductDemand::date((string) ($this->report['from'] ?? ''));
        $to = ProductDemand::date((string) ($this->report['to'] ?? ''));
        $count = (int) ($this->report['sales_count'] ?? 0);
        $this->pdf->rect(0, A4Pdf::HEIGHT - 8, A4Pdf::WIDTH, 8, self::GOLD);
        $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - 32, 'SmartPlanet', 14, true, self::INK);
        $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - 46, 'Vendas do período', 10, false, self::GOLD);
        $this->pdf->textRight(self::RIGHT, A4Pdf::HEIGHT - 32, $from . '  a  ' . $to, 11, true, self::INK);
        $label = $count === 1 ? '1 venda concluída' : $count . ' vendas concluídas';
        $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - 64, $label . '   ·   ' . ProductDemand::money((float) ($this->report['sales_total'] ?? 0)), 9, false, self::INK);
        $this->pdf->line(self::LEFT, A4Pdf::HEIGHT - 74, self::RIGHT, A4Pdf::HEIGHT - 74, self::GOLD, 1);

        return 88.0;
    }

    /** @param list<array<string,mixed>> $rows */
    private function table(float $top, string $title, string $note, array $rows, string $lead): float
    {
        $top = $this->room($top, 48);
        $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - $top - 12, $title, 12, true, self::INK);
        $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - $top - 26, $note, 8, false, self::MUTED);
        $top += 34;
        $top = $this->room($top, 18);
        $this->columns($top);
        $top += 16;
        if ($rows === []) {
            $top = $this->room($top, 18);
            $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - $top - 12, 'Nenhuma venda concluída neste período.', 9, false, self::MUTED);

            return $top + 28;
        }
        foreach ($rows as $index => $row) {
            $before = $top;
            $top = $this->room($top, 16);
            if ($top < $before) {
                $this->columns($top);
                $top += 16;
            }
            if ($index % 2 === 1) {
                $this->pdf->rect(self::LEFT, A4Pdf::HEIGHT - $top - 16, self::RIGHT - self::LEFT, 16, self::BAND);
            }
            $y = A4Pdf::HEIGHT - $top - 11;
            $this->pdf->text(self::LEFT, $y, (string) ($index + 1), 8, true, self::MUTED);
            $this->pdf->text(58, $y, $this->clip((string) ($row['description'] ?? ''), 250, 8, false), 8, false, self::INK);
            $this->pdf->textRight(400, $y, ProductDemand::qty((float) ($row['qty'] ?? 0)), 8, $lead === 'qty', self::INK);
            $this->pdf->textRight(470, $y, (string) (int) ($row['sales'] ?? 0), 8, $lead === 'sales', self::INK);
            $this->pdf->textRight(self::RIGHT, $y, ProductDemand::money((float) ($row['total'] ?? 0)), 8, true, self::INK);
            $top += 16;
        }

        return $top + 18;
    }

    private function columns(float $top): void
    {
        $y = A4Pdf::HEIGHT - $top - 11;
        $this->pdf->line(self::LEFT, A4Pdf::HEIGHT - $top - 16, self::RIGHT, A4Pdf::HEIGHT - $top - 16, self::GOLD, 0.6);
        $this->pdf->text(self::LEFT, $y, '#', 7, true, self::MUTED);
        $this->pdf->text(58, $y, 'Produto', 7, true, self::MUTED);
        $this->pdf->textRight(400, $y, 'Qtd.', 7, true, self::MUTED);
        $this->pdf->textRight(470, $y, 'Vendas', 7, true, self::MUTED);
        $this->pdf->textRight(self::RIGHT, $y, 'Total', 7, true, self::MUTED);
    }

    private function room(float $top, float $height): float
    {
        if ($top + $height <= A4Pdf::HEIGHT - 40) {
            return $top;
        }
        $this->footer();
        $this->pdf->addPage();
        $this->page++;
        $this->pdf->rect(0, A4Pdf::HEIGHT - 8, A4Pdf::WIDTH, 8, self::GOLD);
        $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - 28, 'SmartPlanet   ·   Vendas do período', 10, true, self::INK);
        $this->pdf->line(self::LEFT, A4Pdf::HEIGHT - 38, self::RIGHT, A4Pdf::HEIGHT - 38, self::GOLD, 1);

        return 50.0;
    }

    private function footer(): void
    {
        $this->pdf->text(self::LEFT, 22, 'Listagem interna. Não é documento fiscal.', 7, false, self::MUTED);
        $this->pdf->textRight(self::RIGHT, 22, 'Página ' . $this->page, 7, false, self::MUTED);
    }

    private function clip(string $text, float $max, float $size, bool $bold): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($this->pdf->width($text, $size, $bold) <= $max) {
            return $text;
        }
        $out = $text;
        while ($out !== '' && $this->pdf->width($out . '...', $size, $bold) > $max) {
            $out = mb_substr($out, 0, -1);
        }

        return $out . '...';
    }
}
