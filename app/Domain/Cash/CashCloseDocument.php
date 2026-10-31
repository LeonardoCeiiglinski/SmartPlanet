<?php

declare(strict_types=1);

namespace App\Domain\Cash;

use App\Support\A4Pdf;

/**
 * Folha A4 de fechamento de caixa, para impressão.
 */
final class CashCloseDocument
{
    private const LEFT = 36.0;
    private const RIGHT = 559.0;
    private const GOLD = [0.776, 0.631, 0.353];
    private const INK = [0.067, 0.067, 0.067];
    private const MUTED = [0.427, 0.392, 0.337];
    private const PAPER = [1.0, 1.0, 1.0];
    private const BAND = [0.965, 0.953, 0.925];

    /** @param array<string,mixed> $report */
    public static function render(array $report): string
    {
        $pdf = new A4Pdf();
        $doc = new self($pdf, $report);
        $doc->draw();
        $code = self::code((int) ($report['session']['id'] ?? 0));

        return $pdf->output('Fechamento de caixa ' . $code);
    }

    /** @param array<string,mixed> $report */
    private function __construct(private readonly A4Pdf $pdf, private readonly array $report)
    {
    }

    private int $page = 1;

    private function draw(): void
    {
        $this->pdf->addPage();
        $top = $this->header(true);
        $top = $this->facts($top);
        $top = $this->cards($top);
        $top = $this->payments($top);
        $top = $this->movements($top);
        $top = $this->sales($top);
        $top = $this->returns($top);
        $top = $this->products($top);
        $this->signatures($top);
        $this->footer();
    }

    private function header(bool $full): float
    {
        $session = $this->session();
        $company = is_array($this->report['company'] ?? null) ? $this->report['company'] : [];
        $closed = ($session['status'] ?? '') === 'closed';
        $this->pdf->rect(0, A4Pdf::HEIGHT - 8, A4Pdf::WIDTH, 8, self::GOLD);
        if (!$full) {
            $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - 32, 'SmartPlanet   ·   Fechamento de caixa   ·   ' . self::code((int) ($session['id'] ?? 0)), 10, true, self::INK);
            $this->pdf->textRight(self::RIGHT, A4Pdf::HEIGHT - 32, $closed ? 'Caixa fechado' : 'Caixa ainda aberto', 8, false, self::MUTED);
            $this->pdf->line(self::LEFT, A4Pdf::HEIGHT - 44, self::RIGHT, A4Pdf::HEIGHT - 44, self::GOLD, 1);

            return 58.0;
        }
        $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - 34, 'SmartPlanet', 16, true, self::INK);
        $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - 48, 'a Boutique do Celular', 9, false, self::GOLD);

        $line = 64.0;
        $legal = trim((string) ($company['legal_name'] ?? ''));
        if ($legal !== '') {
            $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - $line, $this->clip($legal, 300, 8, false), 8, false, self::INK);
            $line += 12;
        }
        $cnpj = self::cnpj((string) ($company['document'] ?? ''));
        $phone = trim((string) ($company['phone'] ?? ''));
        if ($phone === '') {
            $phone = '(48) 99662-8811';
        }
        $email = trim((string) ($company['email'] ?? ''));
        $contact = array_values(array_filter([
            $cnpj !== '' ? 'CNPJ ' . $cnpj : '',
            'WhatsApp ' . $phone,
            $email,
        ], static fn (string $part): bool => $part !== ''));
        $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - $line, $this->clip(implode('   ·   ', $contact), 330, 8, false), 8, false, self::MUTED);
        $line += 12;
        $branch = trim((string) ($this->report['branch_name'] ?? ''));
        if ($branch !== '') {
            $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - $line, 'Filial ' . $branch, 8, false, self::MUTED);
            $line += 11;
        }
        foreach (array_slice($this->address(), 0, 3) as $address) {
            $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - $line, $address, 8, false, self::MUTED);
            $line += 11;
        }

        $this->pdf->textRight(self::RIGHT, A4Pdf::HEIGHT - 32, $closed ? 'FECHAMENTO' : 'POSIÇÃO', 14, true, self::INK);
        $this->pdf->textRight(self::RIGHT, A4Pdf::HEIGHT - 48, 'DE CAIXA', 14, true, self::INK);
        $this->pdf->textRight(self::RIGHT, A4Pdf::HEIGHT - 66, self::code((int) ($session['id'] ?? 0)), 11, true, self::GOLD);
        $this->pdf->textRight(self::RIGHT, A4Pdf::HEIGHT - 80, $closed ? 'Caixa fechado' : 'Caixa ainda aberto', 8, false, self::MUTED);

        $bottom = $full ? max($line + 6, 108) : 92;
        $this->pdf->line(self::LEFT, A4Pdf::HEIGHT - $bottom, self::RIGHT, A4Pdf::HEIGHT - $bottom, self::GOLD, 1.2);

        return $bottom + 16;
    }

    private function facts(float $top): float
    {
        $session = $this->session();
        $closed = ($session['status'] ?? '') === 'closed';
        $cells = [
            ['Caixa', (string) ($session['register_name'] ?? '')],
            ['Operador', (string) ($session['operator_name'] ?? '')],
            ['Abertura', self::when((string) ($session['opened_at'] ?? ''))],
            [$closed ? 'Fechamento' : 'Situação', $closed ? self::when((string) ($session['closed_at'] ?? '')) : 'Em aberto'],
        ];
        $width = (self::RIGHT - self::LEFT - 18) / 4;
        $height = 40.0;
        foreach ($cells as $index => $cell) {
            $x = self::LEFT + $index * ($width + 6);
            $this->pdf->rect($x, A4Pdf::HEIGHT - $top - $height, $width, $height, self::BAND);
            $this->pdf->text($x + 8, A4Pdf::HEIGHT - $top - 14, $cell[0], 7, true, self::MUTED);
            $this->pdf->text($x + 8, A4Pdf::HEIGHT - $top - 30, $this->clip($cell[1], $width - 16, 9, true), 9, true, self::INK);
        }
        $top += $height + 8;
        $closer = trim((string) ($session['closed_by_name'] ?? ''));
        $notes = trim((string) ($session['opening_notes'] ?? ''));
        $extra = array_values(array_filter([
            $closer !== '' ? 'Fechado por ' . $closer : '',
            $notes !== '' ? 'Obs. da abertura: ' . $notes : '',
            'Impresso por ' . trim((string) ($this->report['printed_by'] ?? '')) . ' em ' . self::when((string) ($this->report['printed_at'] ?? '')),
        ], static fn (string $part): bool => $part !== ''));
        if ($extra !== []) {
            $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - $top - 8, $this->clip(implode('   ·   ', $extra), self::RIGHT - self::LEFT, 8, false), 8, false, self::MUTED);
            $top += 16;
        }

        return $top + 6;
    }

    private function cards(float $top): float
    {
        $session = $this->session();
        $closed = ($session['status'] ?? '') === 'closed';
        $expected = (float) ($this->report['expected'] ?? 0);
        $counted = $session['counted_cash'];
        $difference = $session['difference_amount'];
        $sold = $this->sold();
        $cards = [
            ['Fundo de caixa', self::money((float) ($session['opening_amount'] ?? 0))],
            ['Dinheiro esperado', self::money($expected)],
            ['Dinheiro contado', $closed && $counted !== null ? self::money((float) $counted) : '-'],
            ['Diferença', $closed && $difference !== null ? self::money((float) $difference) : '-'],
        ];
        $width = (self::RIGHT - self::LEFT - 18) / 4;
        $height = 46.0;
        foreach ($cards as $index => $card) {
            $x = self::LEFT + $index * ($width + 6);
            $this->pdf->rect($x, A4Pdf::HEIGHT - $top - $height, $width, $height, self::INK);
            $this->pdf->text($x + 8, A4Pdf::HEIGHT - $top - 14, $card[0], 7, false, self::GOLD);
            $this->pdf->text($x + 8, A4Pdf::HEIGHT - $top - 34, $this->clip($card[1], $width - 16, 11, true), 11, true, $index === 3 ? self::GOLD : self::PAPER);
        }
        $top += $height + 12;
        $sentence = $sold['count'] === 1
            ? '1 venda concluída, total de ' . self::money($sold['total']) . '.'
            : $sold['count'] . ' vendas concluídas, total de ' . self::money($sold['total']) . '.';
        $cash = $sold['cash'];
        $sentence .= $cash > 0
            ? ' Vendas em dinheiro: ' . self::money($cash) . '.'
            : ' Nenhuma venda em dinheiro: PIX e cartão não entram na gaveta.';
        $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - $top - 8, $this->clip($sentence, self::RIGHT - self::LEFT, 8, false), 8, false, self::MUTED);
        $reason = trim((string) ($session['closing_reason'] ?? ''));
        $top += 14;
        if ($reason !== '') {
            $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - $top - 8, $this->clip('Justificativa: ' . $reason, self::RIGHT - self::LEFT, 8, false), 8, false, self::INK);
            $top += 14;
        }

        return $top + 8;
    }

    private function payments(float $top): float
    {
        $top = $this->ensure($top, 70);
        $top = $this->section($top, 'Formas de pagamento');
        $groups = [];
        foreach ($this->saleRows() as $sale) {
            if (($sale['status'] ?? '') !== 'completed') {
                continue;
            }
            foreach ($sale['payments'] ?? [] as $pay) {
                if (!is_array($pay)) {
                    continue;
                }
                $name = (string) ($pay['name'] ?? 'Pagamento');
                if (!isset($groups[$name])) {
                    $groups[$name] = ['count' => 0, 'total' => 0.0, 'type' => (string) ($pay['method_type'] ?? '')];
                }
                $groups[$name]['count']++;
                $groups[$name]['total'] += (float) ($pay['amount'] ?? 0);
            }
        }
        if ($groups === []) {
            $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - $top - 10, 'Nenhum pagamento registrado.', 9, false, self::MUTED);
            return $top + 22;
        }
        $top = $this->head($top, [
            [self::LEFT + 8, 'Forma', false],
            [280, 'Tipo', false],
            [400, 'Qtd.', true],
            [self::RIGHT - 8, 'Total', true],
        ]);
        $index = 0;
        foreach ($groups as $name => $group) {
            $top = $this->ensure($top, 18);
            if ($index % 2 === 1) {
                $this->pdf->rect(self::LEFT, A4Pdf::HEIGHT - $top - 16, self::RIGHT - self::LEFT, 16, self::BAND);
            }
            $y = A4Pdf::HEIGHT - $top - 11;
            $this->pdf->text(self::LEFT + 8, $y, $this->clip($name, 180, 8, false), 8, false, self::INK);
            $this->pdf->text(280, $y, $this->typeLabel($group['type']), 8, false, self::MUTED);
            $this->pdf->textRight(400, $y, (string) $group['count'], 8, false, self::INK);
            $this->pdf->textRight(self::RIGHT - 8, $y, self::money($group['total']), 8, true, self::INK);
            $top += 16;
            $index++;
        }

        return $top + 12;
    }

    private function movements(float $top): float
    {
        $moves = is_array($this->report['movements'] ?? null) ? $this->report['movements'] : [];
        $top = $this->ensure($top, 56);
        $top = $this->section($top, 'Movimentos da gaveta');
        if ($moves === []) {
            $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - $top - 10, 'Nenhum suprimento, sangria ou venda em dinheiro nesta sessão.', 9, false, self::MUTED);
            return $top + 22;
        }
        $top = $this->head($top, [
            [self::LEFT + 8, 'Hora', false],
            [130, 'Tipo', false],
            [250, 'Usuário', false],
            [360, 'Motivo', false],
            [self::RIGHT - 8, 'Valor', true],
        ]);
        foreach ($moves as $index => $move) {
            if (!is_array($move)) {
                continue;
            }
            $top = $this->ensure($top, 18);
            if ($index % 2 === 1) {
                $this->pdf->rect(self::LEFT, A4Pdf::HEIGHT - $top - 16, self::RIGHT - self::LEFT, 16, self::BAND);
            }
            $y = A4Pdf::HEIGHT - $top - 11;
            $type = (string) ($move['movement_type'] ?? '');
            $sign = in_array($type, ['sale', 'supply'], true) ? '+' : '-';
            $this->pdf->text(self::LEFT + 8, $y, self::when((string) ($move['created_at'] ?? '')), 7.5, false, self::INK);
            $this->pdf->text(130, $y, $this->movementLabel($type), 7.5, false, self::INK);
            $this->pdf->text(250, $y, $this->clip((string) ($move['user_name'] ?? ''), 100, 7.5, false), 7.5, false, self::INK);
            $this->pdf->text(360, $y, $this->clip((string) ($move['reason'] ?? ''), 110, 7.5, false), 7.5, false, self::MUTED);
            $this->pdf->textRight(self::RIGHT - 8, $y, $sign . ' ' . self::money((float) ($move['amount'] ?? 0)), 7.5, true, self::INK);
            $top += 16;
        }

        return $top + 12;
    }

    private function sales(float $top): float
    {
        $top = $this->ensure($top, 40);
        $top = $this->section($top, 'Vendas da sessão');
        $sales = $this->saleRows();
        if ($sales === []) {
            $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - $top - 10, 'Nenhuma venda gravada neste caixa.', 9, false, self::MUTED);
            return $top + 22;
        }
        foreach ($sales as $sale) {
            $top = $this->saleBlock($top, $sale);
        }

        return $top;
    }

    /** @param array<string,mixed> $sale */
    private function saleBlock(float $top, array $sale): float
    {
        $items = is_array($sale['items'] ?? null) ? $sale['items'] : [];
        $payments = is_array($sale['payments'] ?? null) ? $sale['payments'] : [];
        $block = 52 + count($payments) * 12 + 16 + count($items) * 22;
        $top = $this->ensure($top, min($block, 120));
        $cancelled = ($sale['status'] ?? '') !== 'completed';
        $this->pdf->rect(self::LEFT, A4Pdf::HEIGHT - $top - 20, self::RIGHT - self::LEFT, 20, self::INK);
        $y = A4Pdf::HEIGHT - $top - 14;
        $this->pdf->text(self::LEFT + 8, $y, (string) ($sale['sale_number'] ?? ''), 8, true, self::PAPER);
        $this->pdf->textRight(self::RIGHT - 8, $y, self::money((float) ($sale['total'] ?? 0)), 9, true, self::GOLD);
        $top += 20;
        $meta = implode('   ·   ', array_filter([
            self::when((string) ($sale['completed_at'] ?? '')),
            'Operador ' . trim((string) ($sale['operator_name'] ?? '')),
            'Cliente ' . (trim((string) ($sale['customer_name'] ?? '')) ?: 'Consumidor final'),
            $cancelled ? 'Cancelada' : 'Concluída',
        ], static fn (string $part): bool => $part !== ''));
        $this->pdf->text(self::LEFT + 8, A4Pdf::HEIGHT - $top - 12, $this->clip($meta, self::RIGHT - self::LEFT - 16, 8, false), 8, false, self::MUTED);
        $top += 16;
        if ((float) ($sale['discount_amount'] ?? 0) > 0) {
            $this->pdf->text(self::LEFT + 8, A4Pdf::HEIGHT - $top - 10, 'Subtotal ' . self::money((float) ($sale['subtotal'] ?? 0)) . '    Desconto ' . self::money((float) $sale['discount_amount']), 8, false, self::INK);
            $top += 12;
        }
        foreach ($payments as $pay) {
            if (!is_array($pay)) {
                continue;
            }
            $top = $this->ensure($top, 14);
            $label = trim((string) ($pay['name'] ?? 'Pagamento'));
            $installments = (int) ($pay['installments'] ?? 1);
            if ($installments > 1) {
                $label .= ' ' . $installments . 'x';
            }
            $ref = trim((string) ($pay['reference'] ?? ''));
            $line = $label . '   ' . self::money((float) ($pay['amount'] ?? 0)) . ($ref !== '' ? '    ref. ' . $ref : '');
            $this->pdf->text(self::LEFT + 8, A4Pdf::HEIGHT - $top - 10, $this->clip($line, self::RIGHT - self::LEFT - 16, 8, false), 8, false, self::INK);
            $top += 12;
        }
        $top += 2;
        $top = $this->ensure($top, 32);
        $top = $this->head($top, [
            [self::LEFT + 8, 'Código', false],
            [148, 'Produto', false],
            [368, 'Qtd.', true],
            [438, 'Unitário', true],
            [498, 'Desc.', true],
            [self::RIGHT - 8, 'Total', true],
        ]);
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $barcode = trim((string) ($item['barcode'] ?? ''));
            $returned = (float) ($item['returned_quantity'] ?? 0);
            $height = $barcode !== '' || $returned > 0 ? 28.0 : 16.0;
            $top = $this->ensure($top, $height);
            if ($index % 2 === 1) {
                $this->pdf->rect(self::LEFT, A4Pdf::HEIGHT - $top - $height, self::RIGHT - self::LEFT, $height, self::BAND);
            }
            $y = A4Pdf::HEIGHT - $top - 11;
            $code = trim((string) ($item['internal_code'] ?? ''));
            $this->pdf->text(self::LEFT + 8, $y, $this->clip($code !== '' ? $code : '-', 100, 7, true), 7, true, self::INK);
            $this->pdf->text(148, $y, $this->clip((string) ($item['description'] ?? ''), 190, 8, false), 8, false, self::INK);
            $this->pdf->textRight(368, $y, self::qty((float) ($item['quantity'] ?? 0)), 8, false, self::INK);
            $this->pdf->textRight(438, $y, self::money((float) ($item['unit_price'] ?? 0)), 8, false, self::INK);
            $discount = (float) ($item['discount_amount'] ?? 0);
            $this->pdf->textRight(498, $y, $discount > 0 ? self::money($discount) : '-', 8, false, self::MUTED);
            $this->pdf->textRight(self::RIGHT - 8, $y, self::money((float) ($item['subtotal'] ?? 0)), 8, true, self::INK);
            if ($barcode !== '' || $returned > 0) {
                $note = array_values(array_filter([
                    $barcode !== '' ? 'Barras ' . $barcode : '',
                    $returned > 0 ? 'Devolvido ' . self::qty($returned) : '',
                ]));
                $this->pdf->text(148, A4Pdf::HEIGHT - $top - 21, implode('   ·   ', $note), 7, false, self::MUTED);
            }
            $top += $height;
        }

        return $top + 12;
    }

    private function returns(float $top): float
    {
        $rows = is_array($this->report['returns'] ?? null) ? $this->report['returns'] : [];
        if ($rows === []) {
            return $top;
        }
        $top = $this->ensure($top, 56);
        $top = $this->section($top, 'Devoluções');
        $top = $this->head($top, [
            [self::LEFT + 8, 'Devolução', false],
            [140, 'Venda', false],
            [280, 'Quando', false],
            [390, 'Usuário', false],
            [self::RIGHT - 8, 'Valor', true],
        ]);
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $top = $this->ensure($top, 26);
            if ($index % 2 === 1) {
                $this->pdf->rect(self::LEFT, A4Pdf::HEIGHT - $top - 24, self::RIGHT - self::LEFT, 24, self::BAND);
            }
            $y = A4Pdf::HEIGHT - $top - 11;
            $this->pdf->text(self::LEFT + 8, $y, $this->clip((string) ($row['return_number'] ?? ''), 120, 7.5, true), 7.5, true, self::INK);
            $this->pdf->text(140, $y, $this->clip((string) ($row['sale_number'] ?? ''), 130, 7, false), 7, false, self::INK);
            $this->pdf->text(280, $y, self::when((string) ($row['created_at'] ?? '')), 7.5, false, self::INK);
            $this->pdf->text(390, $y, $this->clip((string) ($row['user_name'] ?? ''), 80, 7.5, false), 7.5, false, self::INK);
            $this->pdf->textRight(self::RIGHT - 8, $y, self::money((float) ($row['total'] ?? 0)), 8, true, self::INK);
            $reason = trim((string) ($row['reason'] ?? ''));
            if ($reason !== '') {
                $this->pdf->text(self::LEFT + 8, A4Pdf::HEIGHT - $top - 21, $this->clip($reason, 400, 7, false), 7, false, self::MUTED);
            }
            $top += 24;
        }

        return $top + 12;
    }

    private function products(float $top): float
    {
        $groups = [];
        foreach ($this->saleRows() as $sale) {
            if (($sale['status'] ?? '') !== 'completed') {
                continue;
            }
            foreach ($sale['items'] ?? [] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $code = trim((string) ($item['internal_code'] ?? ''));
                $key = $code . '|' . (string) ($item['description'] ?? '');
                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'code' => $code,
                        'description' => (string) ($item['description'] ?? ''),
                        'barcode' => trim((string) ($item['barcode'] ?? '')),
                        'quantity' => 0.0,
                        'total' => 0.0,
                    ];
                }
                $groups[$key]['quantity'] += (float) ($item['quantity'] ?? 0);
                $groups[$key]['total'] += (float) ($item['subtotal'] ?? 0);
            }
        }
        $top = $this->ensure($top, 56);
        $top = $this->section($top, 'Consolidado de produtos');
        if ($groups === []) {
            $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - $top - 10, 'Nenhum produto em venda concluída.', 9, false, self::MUTED);
            return $top + 22;
        }
        $top = $this->head($top, [
            [self::LEFT + 8, 'Código', false],
            [150, 'Produto', false],
            [430, 'Qtd.', true],
            [self::RIGHT - 8, 'Total', true],
        ]);
        $index = 0;
        $qty = 0.0;
        $sum = 0.0;
        foreach ($groups as $group) {
            $top = $this->ensure($top, 16);
            if ($index % 2 === 1) {
                $this->pdf->rect(self::LEFT, A4Pdf::HEIGHT - $top - 16, self::RIGHT - self::LEFT, 16, self::BAND);
            }
            $y = A4Pdf::HEIGHT - $top - 11;
            $this->pdf->text(self::LEFT + 8, $y, $this->clip($group['code'] !== '' ? $group['code'] : '-', 130, 7, true), 7, true, self::INK);
            $label = $group['description'] . ($group['barcode'] !== '' ? '  ·  ' . $group['barcode'] : '');
            $this->pdf->text(150, $y, $this->clip($label, 250, 8, false), 8, false, self::INK);
            $this->pdf->textRight(430, $y, self::qty($group['quantity']), 8, false, self::INK);
            $this->pdf->textRight(self::RIGHT - 8, $y, self::money($group['total']), 8, true, self::INK);
            $qty += $group['quantity'];
            $sum += $group['total'];
            $top += 16;
            $index++;
        }
        $top = $this->ensure($top, 18);
        $this->pdf->text(150, A4Pdf::HEIGHT - $top - 12, 'Total dos itens', 8, true, self::INK);
        $this->pdf->textRight(430, A4Pdf::HEIGHT - $top - 12, self::qty($qty), 8, true, self::INK);
        $this->pdf->textRight(self::RIGHT - 8, A4Pdf::HEIGHT - $top - 12, self::money($sum), 8, true, self::INK);

        return $top + 22;
    }

    private function signatures(float $top): void
    {
        $top = $this->ensure($top, 78);
        $session = $this->session();
        $top = $this->section($top, 'Conferência');
        $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - $top - 10, 'Documento interno para arquivo da loja. Não substitui cupom fiscal nem nota.', 8, false, self::MUTED);
        $top += 28;
        $left = self::LEFT + 10;
        $right = 320.0;
        $this->pdf->line($left, A4Pdf::HEIGHT - $top, $left + 180, A4Pdf::HEIGHT - $top, self::INK, 0.6);
        $this->pdf->line($right, A4Pdf::HEIGHT - $top, $right + 180, A4Pdf::HEIGHT - $top, self::INK, 0.6);
        $this->pdf->text($left, A4Pdf::HEIGHT - $top - 14, 'Operador do caixa', 8, true, self::INK);
        $this->pdf->text($right, A4Pdf::HEIGHT - $top - 14, 'Conferência do dinheiro', 8, true, self::INK);
        $this->pdf->text($left, A4Pdf::HEIGHT - $top - 26, $this->clip((string) ($session['operator_name'] ?? ''), 180, 8, false), 8, false, self::MUTED);
        $closer = trim((string) ($session['closed_by_name'] ?? ''));
        $this->pdf->text($right, A4Pdf::HEIGHT - $top - 26, $closer !== '' ? $this->clip($closer, 180, 8, false) : 'Assinatura', 8, false, self::MUTED);
    }

    private function section(float $top, string $title): float
    {
        $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - $top - 11, $title, 11, true, self::INK);
        $this->pdf->line(self::LEFT, A4Pdf::HEIGHT - $top - 16, self::RIGHT, A4Pdf::HEIGHT - $top - 16, self::GOLD, 1);

        return $top + 24;
    }

    /** @param list<array{0:float,1:string,2:bool}> $columns */
    private function head(float $top, array $columns): float
    {
        $this->pdf->rect(self::LEFT, A4Pdf::HEIGHT - $top - 16, self::RIGHT - self::LEFT, 16, self::INK);
        $y = A4Pdf::HEIGHT - $top - 11;
        foreach ($columns as $column) {
            if ($column[2]) {
                $this->pdf->textRight($column[0], $y, $column[1], 7.5, true, self::PAPER);
            } else {
                $this->pdf->text($column[0], $y, $column[1], 7.5, true, self::PAPER);
            }
        }

        return $top + 16;
    }

    private function ensure(float $top, float $need): float
    {
        if ($top + $need <= 760) {
            return $top;
        }
        $this->footer();
        $this->pdf->addPage();
        $this->page++;

        return $this->header(false);
    }

    private function footer(): void
    {
        $this->pdf->line(self::LEFT, 36, self::RIGHT, 36, self::GOLD, 0.8);
        $this->pdf->text(self::LEFT, 22, 'SmartPlanet  ·  Documento interno de fechamento de caixa  ·  Não é documento fiscal', 7, false, self::MUTED);
        $this->pdf->textRight(self::RIGHT, 22, 'Folha ' . $this->page, 7, true, self::INK);
    }

    /** @return array<string,mixed> */
    private function session(): array
    {
        $session = $this->report['session'] ?? [];

        return is_array($session) ? $session : [];
    }

    /** @return list<array<string,mixed>> */
    private function saleRows(): array
    {
        $sales = $this->report['sales'] ?? [];
        if (!is_array($sales)) {
            return [];
        }
        $rows = [];
        foreach ($sales as $sale) {
            if (is_array($sale)) {
                $rows[] = $sale;
            }
        }

        return $rows;
    }

    /** @return array{count:int,total:float,cash:float} */
    private function sold(): array
    {
        $count = 0;
        $total = 0.0;
        $cash = 0.0;
        foreach ($this->saleRows() as $sale) {
            if (($sale['status'] ?? '') !== 'completed') {
                continue;
            }
            $count++;
            $total += (float) ($sale['total'] ?? 0);
            foreach ($sale['payments'] ?? [] as $pay) {
                if (is_array($pay) && ($pay['method_type'] ?? '') === 'cash') {
                    $cash += (float) ($pay['amount'] ?? 0);
                }
            }
        }

        return ['count' => $count, 'total' => round($total, 2), 'cash' => round($cash, 2)];
    }

    /** @return list<string> */
    private function address(): array
    {
        $lines = $this->report['address'] ?? [];
        if (is_array($lines) && $lines !== []) {
            $clean = [];
            foreach ($lines as $line) {
                if (is_string($line) && trim($line) !== '') {
                    $clean[] = trim($line);
                }
            }
            if ($clean !== []) {
                return $clean;
            }
        }

        return [
            'Av. Josué Di Bernardi, 34',
            'Campinas, São José/SC  ·  CEP 88101-200',
            'Referência: Direto do Campo',
        ];
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

    private static function code(int $id): string
    {
        return 'CX-' . str_pad((string) max($id, 0), 5, '0', STR_PAD_LEFT);
    }

    private static function money(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    private static function qty(float $value): string
    {
        if (abs($value - round($value)) < 0.0001) {
            return (string) (int) round($value);
        }

        return rtrim(rtrim(number_format($value, 3, ',', '.'), '0'), ',');
    }

    private static function when(string $value): string
    {
        $stamp = strtotime($value);

        return $stamp ? date('d/m/Y H:i', $stamp) : $value;
    }

    private static function cnpj(string $digits): string
    {
        $digits = preg_replace('/\D/', '', $digits) ?? '';
        if (strlen($digits) !== 14) {
            return trim($digits);
        }

        return substr($digits, 0, 2) . '.' . substr($digits, 2, 3) . '.' . substr($digits, 5, 3) . '/' . substr($digits, 8, 4) . '-' . substr($digits, 12, 2);
    }

    private function movementLabel(string $type): string
    {
        return match ($type) {
            'sale' => 'Venda em dinheiro',
            'supply' => 'Suprimento',
            'withdrawal' => 'Sangria',
            'sale_cancel' => 'Estorno',
            default => $type,
        };
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'cash' => 'Dinheiro',
            'pix' => 'PIX',
            'debit' => 'Débito',
            'credit' => 'Crédito',
            default => $type !== '' ? $type : 'Outro',
        };
    }
}
