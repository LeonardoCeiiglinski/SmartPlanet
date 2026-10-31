<?php

declare(strict_types=1);

namespace App\Domain\Management;

use App\Support\A4Pdf;

/**
 * Orçamento em folha A4 para download.
 */
final class QuoteDocument
{
    private const LEFT = 40.0;
    private const RIGHT = 555.0;
    private const GOLD = [0.776, 0.631, 0.353];
    private const INK = [0.067, 0.067, 0.067];
    private const MUTED = [0.427, 0.392, 0.337];
    private const PAPER = [1, 1, 1];
    private const BAND = [0.965, 0.953, 0.925];
    private const LINE = [0.894, 0.843, 0.722];

    /** @param array<string,mixed> $quote @param list<array<string,mixed>> $items @param array<string,mixed> $company @param array<string,mixed>|null $customer @param list<string> $storeAddress @param list<string> $customerAddress */
    public static function render(array $quote, array $items, array $company, ?array $customer, array $storeAddress, array $customerAddress): string
    {
        $pdf = new A4Pdf();
        $doc = new self($pdf, $quote, $items, $company, $customer, $storeAddress, $customerAddress);
        $doc->draw();
        $number = (string) ($quote['quote_number'] ?? 'orcamento');
        return $pdf->output('Orçamento ' . $number);
    }

    /** @return list<string> */
    public static function addressLines(mixed $json): array
    {
        if (is_string($json) && $json !== '') {
            $decoded = json_decode($json, true);
        } elseif (is_array($json)) {
            $decoded = $json;
        } else {
            return [];
        }
        if (!is_array($decoded)) {
            return [];
        }
        $lines = [];
        foreach (['line', 'street', 'address', 'logradouro'] as $key) {
            if (!empty($decoded[$key]) && is_string($decoded[$key])) {
                $lines[] = $decoded[$key];
            }
        }
        $city = trim(implode(' - ', array_filter([
            is_string($decoded['district'] ?? null) ? $decoded['district'] : (is_string($decoded['bairro'] ?? null) ? $decoded['bairro'] : ''),
            trim(implode('/', array_filter([
                is_string($decoded['city'] ?? null) ? $decoded['city'] : (is_string($decoded['cidade'] ?? null) ? $decoded['cidade'] : ''),
                is_string($decoded['state'] ?? null) ? $decoded['state'] : (is_string($decoded['uf'] ?? null) ? $decoded['uf'] : ''),
            ], static fn (string $part): bool => $part !== ''))),
        ], static fn (string $part): bool => $part !== '')));
        if ($city !== '') {
            $lines[] = $city;
        }
        $zip = $decoded['zip'] ?? $decoded['cep'] ?? '';
        if (is_string($zip) && trim($zip) !== '') {
            $lines[] = 'CEP ' . trim($zip);
        }
        return $lines;
    }

    /** @param array<string,mixed> $quote @param list<array<string,mixed>> $items @param array<string,mixed> $company @param array<string,mixed>|null $customer @param list<string> $storeAddress @param list<string> $customerAddress */
    private function __construct(
        private readonly A4Pdf $pdf,
        private readonly array $quote,
        private readonly array $items,
        private readonly array $company,
        private readonly ?array $customer,
        private readonly array $storeAddress,
        private readonly array $customerAddress,
    ) {
    }

    private function draw(): void
    {
        $this->pdf->addPage();
        $top = $this->header(true);
        $top = $this->parties($top);
        $top = $this->tableHead($top);
        foreach ($this->items as $index => $item) {
            $lines = $this->pdf->wrap((string) ($item['description'] ?? 'Item'), 248, 9, false);
            $height = max(18, count($lines) * 12 + 6);
            if ($top + $height > 730) {
                $this->footer();
                $this->pdf->addPage();
                $top = $this->header(false);
                $top = $this->tableHead($top);
            }
            $this->row($top, $height, $index, $item, $lines);
            $top += $height;
        }
        if ($top + 64 > 720) {
            $this->footer();
            $this->pdf->addPage();
            $top = $this->header(false);
        }
        $this->totals($top + 16);
        $this->footer();
    }

    private function header(bool $full): float
    {
        $this->pdf->rect(0, A4Pdf::HEIGHT - 8, A4Pdf::WIDTH, 8, self::GOLD);
        $trade = trim((string) ($this->company['trade_name'] ?? 'SmartPlanet'));
        if ($trade === '') {
            $trade = 'SmartPlanet';
        }
        $legal = trim((string) ($this->company['legal_name'] ?? ''));
        $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - 36, 'SmartPlanet', 16, true, self::INK);
        $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - 52, 'a Boutique do Celular', 9, false, self::MUTED);
        $line = 68;
        if (mb_strtolower($trade) !== 'smartplanet') {
            $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - $line, $trade, 10, true, self::INK);
            $line += 14;
        }
        if ($legal !== '' && mb_strtolower($legal) !== mb_strtolower($trade)) {
            $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - $line, $legal, 8, false, self::MUTED);
            $line += 12;
        }
        $contact = array_values(array_filter([
            trim((string) ($this->company['phone'] ?? '')),
            trim((string) ($this->company['email'] ?? '')),
            trim((string) ($this->company['document'] ?? '')) !== '' ? 'CNPJ ' . trim((string) $this->company['document']) : '',
        ], static fn (string $part): bool => $part !== ''));
        if ($contact !== []) {
            $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - $line, implode('  ·  ', $contact), 8, false, self::MUTED);
            $line += 12;
        }
        foreach (array_slice($this->storeAddress, 0, 3) as $address) {
            $this->pdf->text(self::LEFT, A4Pdf::HEIGHT - $line, $address, 8, false, self::MUTED);
            $line += 11;
        }

        $this->pdf->textRight(self::RIGHT, A4Pdf::HEIGHT - 36, 'ORÇAMENTO', 18, true, self::INK);
        $this->pdf->textRight(self::RIGHT, A4Pdf::HEIGHT - 54, (string) ($this->quote['quote_number'] ?? ''), 11, true, self::INK);
        $this->pdf->textRight(self::RIGHT, A4Pdf::HEIGHT - 70, 'Emitido em ' . self::date((string) ($this->quote['created_at'] ?? '')), 9, false, self::MUTED);
        $valid = trim((string) ($this->quote['valid_until'] ?? ''));
        $this->pdf->textRight(self::RIGHT, A4Pdf::HEIGHT - 84, $valid !== '' ? 'Válido até ' . self::date($valid) : 'Sem data de validade', 9, false, self::MUTED);
        $this->pdf->textRight(self::RIGHT, A4Pdf::HEIGHT - 98, self::status((string) ($this->quote['status'] ?? '')), 9, true, self::INK);

        $bottom = $full ? max($line + 8, 118) : max($line + 8, 112);
        $this->pdf->line(self::LEFT, A4Pdf::HEIGHT - $bottom, self::RIGHT, A4Pdf::HEIGHT - $bottom, self::GOLD, 1.2);
        return $bottom + 16;
    }

    private function parties(float $top): float
    {
        $name = trim((string) ($this->customer['name'] ?? ''));
        if ($name === '') {
            $name = 'Consumidor final';
        }
        $facts = array_values(array_filter([
            trim((string) ($this->customer['document'] ?? '')),
            trim((string) ($this->customer['phone'] ?? '')),
            trim((string) ($this->customer['email'] ?? '')),
            ...$this->customerAddress,
        ], static fn (string $part): bool => $part !== ''));
        $boxH = 36 + count($facts) * 12;
        $this->pdf->rect(self::LEFT, A4Pdf::HEIGHT - $top - $boxH, self::RIGHT - self::LEFT, $boxH, self::BAND);
        $this->pdf->text(self::LEFT + 12, A4Pdf::HEIGHT - $top - 16, 'CLIENTE', 8, true, self::MUTED);
        $this->pdf->text(self::LEFT + 12, A4Pdf::HEIGHT - $top - 32, $name, 12, true, self::INK);
        $y = $top + 46;
        foreach ($facts as $fact) {
            $this->pdf->text(self::LEFT + 12, A4Pdf::HEIGHT - $y, $fact, 9, false, self::INK);
            $y += 12;
        }
        return $top + $boxH + 18;
    }

    private function tableHead(float $top): float
    {
        $this->pdf->rect(self::LEFT, A4Pdf::HEIGHT - $top - 18, self::RIGHT - self::LEFT, 18, self::INK);
        $this->pdf->text(self::LEFT + 8, A4Pdf::HEIGHT - $top - 13, 'Descrição', 8, true, self::PAPER);
        $this->pdf->textRight(330, A4Pdf::HEIGHT - $top - 13, 'Qtd.', 8, true, self::PAPER);
        $this->pdf->textRight(400, A4Pdf::HEIGHT - $top - 13, 'Unitário', 8, true, self::PAPER);
        $this->pdf->textRight(470, A4Pdf::HEIGHT - $top - 13, 'Desconto', 8, true, self::PAPER);
        $this->pdf->textRight(self::RIGHT - 8, A4Pdf::HEIGHT - $top - 13, 'Subtotal', 8, true, self::PAPER);
        return $top + 18;
    }

    /** @param array<string,mixed> $item @param list<string> $lines */
    private function row(float $top, float $height, int $index, array $item, array $lines): void
    {
        if ($index % 2 === 1) {
            $this->pdf->rect(self::LEFT, A4Pdf::HEIGHT - $top - $height, self::RIGHT - self::LEFT, $height, self::BAND);
        }
        $y = $top + 13;
        foreach ($lines as $line) {
            $this->pdf->text(self::LEFT + 8, A4Pdf::HEIGHT - $y, $line, 9, false, self::INK);
            $y += 12;
        }
        $base = A4Pdf::HEIGHT - ($top + 13);
        $this->pdf->textRight(330, $base, self::qty($item['quantity'] ?? 0), 9, false, self::INK);
        $this->pdf->textRight(400, $base, self::money($item['unit_price'] ?? 0), 9, false, self::INK);
        $this->pdf->textRight(470, $base, self::money($item['discount_amount'] ?? 0), 9, false, self::INK);
        $this->pdf->textRight(self::RIGHT - 8, $base, self::money($item['subtotal'] ?? 0), 9, true, self::INK);
        $this->pdf->line(self::LEFT, A4Pdf::HEIGHT - $top - $height, self::RIGHT, A4Pdf::HEIGHT - $top - $height, self::LINE, 0.4);
    }

    private function totals(float $top): void
    {
        $subtotal = (float) ($this->quote['subtotal'] ?? 0);
        $discount = (float) ($this->quote['discount_amount'] ?? 0);
        $total = (float) ($this->quote['total'] ?? 0);
        $this->pdf->textRight(470, A4Pdf::HEIGHT - $top, 'Subtotal', 9, false, self::MUTED);
        $this->pdf->textRight(self::RIGHT - 8, A4Pdf::HEIGHT - $top, self::money($subtotal), 9, false, self::INK);
        $this->pdf->textRight(470, A4Pdf::HEIGHT - ($top + 16), 'Desconto', 9, false, self::MUTED);
        $this->pdf->textRight(self::RIGHT - 8, A4Pdf::HEIGHT - ($top + 16), self::money($discount), 9, false, self::INK);
        $this->pdf->rect(360, A4Pdf::HEIGHT - $top - 48, self::RIGHT - 360, 24, self::INK);
        $this->pdf->text(372, A4Pdf::HEIGHT - $top - 40, 'TOTAL', 11, true, self::PAPER);
        $this->pdf->textRight(self::RIGHT - 8, A4Pdf::HEIGHT - $top - 40, self::money($total), 12, true, self::GOLD);
    }

    private function footer(): void
    {
        $this->pdf->line(self::LEFT, 46, self::RIGHT, 46, self::GOLD, 1);
        $valid = trim((string) ($this->quote['valid_until'] ?? ''));
        $note = $valid !== ''
            ? 'Proposta válida até ' . self::date($valid) . '. Este orçamento não é documento fiscal.'
            : 'Este orçamento não é documento fiscal.';
        $this->pdf->text(self::LEFT, 32, $note, 8, false, self::MUTED);
        $this->pdf->text(self::LEFT, 20, 'lojadeeletronicossaojose.com.br  ·  SmartPlanet, a Boutique do Celular', 8, false, self::INK);
    }

    private static function money(mixed $value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }

    private static function qty(mixed $value): string
    {
        $n = (float) $value;
        if (abs($n - round($n)) < 0.0001) {
            return (string) (int) round($n);
        }
        return rtrim(rtrim(number_format($n, 3, ',', '.'), '0'), ',');
    }

    private static function date(string $value): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m) !== 1) {
            return $value;
        }
        return $m[3] . '/' . $m[2] . '/' . $m[1];
    }

    private static function status(string $status): string
    {
        return match ($status) {
            'open' => 'Em aberto',
            'approved' => 'Aprovado',
            'converted' => 'Convertido em venda',
            'cancelled' => 'Cancelado',
            'draft' => 'Rascunho',
            default => $status !== '' ? $status : 'Em aberto',
        };
    }
}
