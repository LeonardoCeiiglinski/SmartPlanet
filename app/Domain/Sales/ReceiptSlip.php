<?php

declare(strict_types=1);

namespace App\Domain\Sales;

/**
 * Texto das duas vias de 32 colunas (papel 58 mm, impressão 48 mm).
 * O HTML da venda usa estas linhas, sem depender do canvas do navegador.
 */
final class ReceiptSlip
{
    public const COLUMNS = 32;
    public const DOMAIN = 'lojadeeletronicossaojose.com.br';

    /** @param array<string,mixed> $receipt @return list<array{id:string,title:string,lines:list<array{text:string,bold:bool}>}> */
    public static function vias(array $receipt): array
    {
        return [
            ['id' => 'cliente', 'title' => 'VIA DO CLIENTE', 'lines' => self::lines($receipt, 'VIA DO CLIENTE', 'Fica com o cliente')],
            ['id' => 'empresa', 'title' => 'VIA DA LOJA', 'lines' => self::lines($receipt, 'VIA DA LOJA', 'Fica com a loja')],
        ];
    }

    /** @param array<string,mixed> $receipt @return list<array{text:string,bold:bool}> */
    public static function lines(array $receipt, string $viaTitle, string $keeper = ''): array
    {
        $out = [];
        $add = static function (array $items) use (&$out): void {
            foreach ($items as $item) {
                $out[] = is_string($item) ? self::line($item, false) : $item;
            }
        };
        if ($viaTitle !== '') {
            $add(array_map(static fn (string $t): array => self::line($t, true, true), self::center($viaTitle)));
        }
        if ($keeper !== '') {
            $add(array_map(static fn (string $t): array => self::line($t, true), self::center($keeper)));
        }
        $brand = trim((string) ($receipt['tradeName'] ?? 'SmartPlanet'));
        if ($brand === '') {
            $brand = 'SmartPlanet';
        }
        $brandKey = mb_strtolower($brand);
        $add(array_map(static fn (string $t): array => self::line($t, true), self::center($brand)));
        if (!str_contains($brandKey, 'smartplanet')) {
            $add(self::center('SmartPlanet'));
        }
        if (!str_contains($brandKey, 'boutique')) {
            $add(self::center('a Boutique do Celular'));
        }
        $phone = trim((string) ($receipt['phone'] ?? ''));
        if ($phone !== '') {
            $add(self::center($phone));
        }
        foreach ($receipt['address'] ?? [] as $address) {
            $add(self::center((string) $address));
        }
        $add([self::line(str_repeat('-', self::COLUMNS), false)]);
        $heading = ((string) ($receipt['status'] ?? '')) === 'cancelled' ? 'RECIBO CANCELADO' : 'RECIBO NÃO FISCAL';
        $add(array_map(static fn (string $t): array => self::line($t, true), self::center($heading)));
        $add(array_map(static fn (string $t): array => self::line($t, false), self::wrap((string) ($receipt['saleNumber'] ?? ''), self::COLUMNS)));
        $when = (string) ($receipt['completedAt'] ?? '');
        if ($when !== '') {
            $add([self::line(self::dateTime($when), false)]);
        }
        $operator = trim((string) ($receipt['operator'] ?? ''));
        if ($operator !== '') {
            $add(array_map(static fn (string $t): array => self::line($t, false), self::wrap('Operador: ' . $operator, self::COLUMNS)));
        }
        $customer = trim((string) ($receipt['customer'] ?? ''));
        if ($customer === '') {
            $customer = 'Consumidor final';
        }
        $add(array_map(static fn (string $t): array => self::line($t, false), self::wrap('Cliente: ' . $customer, self::COLUMNS)));
        $add([self::line(str_repeat('-', self::COLUMNS), false)]);
        foreach ($receipt['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $add(array_map(static fn (string $t): array => self::line($t, false), self::wrap((string) ($item['description'] ?? 'Item'), self::COLUMNS)));
            $add(self::columns(self::qty($item['quantity'] ?? 0) . ' x ' . self::money($item['unitPrice'] ?? 0), self::money($item['subtotal'] ?? 0), false));
        }
        $add([self::line(str_repeat('-', self::COLUMNS), false)]);
        if ((float) ($receipt['discount'] ?? 0) > 0) {
            $add(self::columns('Desconto', self::money($receipt['discount']), false));
        }
        $add(self::columns('TOTAL', self::money($receipt['total'] ?? 0), true));
        foreach ($receipt['payments'] ?? [] as $payment) {
            if (!is_array($payment)) {
                continue;
            }
            $name = (string) ($payment['name'] ?? 'Pagamento');
            $installments = (int) ($payment['installments'] ?? 1);
            if ($installments > 1) {
                $name .= ' ' . $installments . 'x';
            }
            $add(self::columns($name, self::money($payment['amount'] ?? 0), false));
        }
        $add([self::line(str_repeat('-', self::COLUMNS), false)]);
        if ($viaTitle !== '') {
            $add(array_map(static fn (string $t): array => self::line($t, true), self::center($viaTitle)));
        }
        $add(self::center('Não é documento fiscal'));
        $add(self::center(self::DOMAIN));
        return $out;
    }

    /** @return list<string> */
    private static function chars(string $value): array
    {
        if ($value === '') {
            return [];
        }
        return mb_str_split($value);
    }

    private static function money(mixed $value): string
    {
        $n = (int) round((float) $value * 100);
        $abs = abs($n);
        $whole = intdiv($abs, 100);
        $cents = str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
        $body = number_format($whole, 0, '', '.');
        return ($n < 0 ? '-' : '') . 'R$ ' . $body . ',' . $cents;
    }

    private static function qty(mixed $value): string
    {
        $n = (float) $value;
        if (!is_finite($n)) {
            return (string) $value;
        }
        if (abs($n - round($n)) < 1e-6) {
            return (string) (int) round($n);
        }
        return rtrim(rtrim(number_format($n, 3, ',', ''), '0'), ',');
    }

    private static function dateTime(string $value): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/', $value, $m) !== 1) {
            return $value;
        }
        return $m[3] . '/' . $m[2] . '/' . $m[1] . ' ' . $m[4] . ':' . $m[5];
    }

    /** @return list<string> */
    private static function wrap(string $text, int $limit): array
    {
        $limit = max(1, $limit);
        $words = array_values(array_filter(explode(' ', trim((string) preg_replace('/\s+/', ' ', $text))), static fn (string $w): bool => $w !== ''));
        if ($words === []) {
            return [];
        }
        $lines = [];
        $current = '';
        $push = static function (string $s) use (&$lines): void {
            if ($s !== '') {
                $lines[] = $s;
            }
        };
        foreach ($words as $word) {
            while (count(self::chars($word)) > $limit) {
                if ($current !== '') {
                    $push($current);
                    $current = '';
                }
                $push(implode('', array_slice(self::chars($word), 0, $limit)));
                $word = implode('', array_slice(self::chars($word), $limit));
            }
            $attempt = $current !== '' ? $current . ' ' . $word : $word;
            if (count(self::chars($attempt)) <= $limit) {
                $current = $attempt;
            } else {
                $push($current);
                $current = $word;
            }
        }
        $push($current);
        return $lines;
    }

    private static function fill(string $left, string $right): string
    {
        $l = self::chars($left);
        $r = self::chars($right);
        if (count($r) >= self::COLUMNS) {
            return implode('', array_slice($r, 0, self::COLUMNS));
        }
        $maxLeft = self::COLUMNS - count($r);
        $cut = array_slice($l, 0, $maxLeft);
        return implode('', $cut) . str_repeat(' ', self::COLUMNS - count($cut) - count($r)) . implode('', $r);
    }

    /** @return list<string> */
    private static function center(string $text): array
    {
        return array_map(static function (string $part): string {
            $chars = self::chars($part);
            $rest = self::COLUMNS - count($chars);
            $left = intdiv($rest, 2);
            return str_repeat(' ', $left) . implode('', $chars) . str_repeat(' ', $rest - $left);
        }, self::wrap($text, self::COLUMNS));
    }

    /** @return array{text:string,bold:bool,via:bool} */
    private static function line(string $text, bool $bold, bool $via = false): array
    {
        $chars = self::chars($text);
        $base = count($chars) === self::COLUMNS ? implode('', $chars) : self::fill(implode('', $chars), '');
        return ['text' => $base, 'bold' => $bold, 'via' => $via];
    }

    /** @return list<array{text:string,bold:bool}> */
    private static function columns(string $left, string $right, bool $bold): array
    {
        $widthRight = count(self::chars($right));
        $maxLeft = max(1, self::COLUMNS - $widthRight - 1);
        $blocks = self::wrap($left, $maxLeft);
        if ($blocks === []) {
            $blocks = [''];
        }
        $last = count($blocks) - 1;
        $out = [];
        foreach ($blocks as $i => $block) {
            $out[] = self::line($i === $last ? self::fill($block, $right) : $block, $bold);
        }
        return $out;
    }
}
