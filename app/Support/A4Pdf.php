<?php

declare(strict_types=1);

namespace App\Support;

/**
 * PDF de uma ou mais folhas A4, com Helvetica e acentos em Windows-1252.
 */
final class A4Pdf
{
    public const WIDTH = 595.28;
    public const HEIGHT = 841.89;

    /** @var list<string> */
    private array $pages = [];
    private string $ops = '';

    public function addPage(): void
    {
        if ($this->ops !== '') {
            $this->pages[] = $this->ops;
        }
        $this->ops = '';
    }

    public function rect(float $x, float $y, float $w, float $h, array $rgb, bool $stroke = false): void
    {
        $this->ops .= sprintf("%.3F %.3F %.3F rg\n", $rgb[0], $rgb[1], $rgb[2]);
        $this->ops .= sprintf("%.2F %.2F %.2F %.2F re %s\n", $x, $y, $w, $h, $stroke ? 'B' : 'f');
    }

    public function line(float $x1, float $y1, float $x2, float $y2, array $rgb, float $width = 0.6): void
    {
        $this->ops .= sprintf("%.2F w\n%.3F %.3F %.3F RG\n%.2F %.2F m %.2F %.2F l S\n", $width, $rgb[0], $rgb[1], $rgb[2], $x1, $y1, $x2, $y2);
    }

    /** @param array{0:float,1:float,2:float} $rgb */
    public function text(float $x, float $y, string $text, float $size, bool $bold, array $rgb): void
    {
        $font = $bold ? 'F2' : 'F1';
        $this->ops .= sprintf("BT /%s %.2F Tf %.3F %.3F %.3F rg 1 0 0 1 %.2F %.2F Tm %s Tj ET\n", $font, $size, $rgb[0], $rgb[1], $rgb[2], $x, $y, self::literal($text));
    }

    public function textRight(float $right, float $y, string $text, float $size, bool $bold, array $rgb): void
    {
        $this->text($right - $this->width($text, $size, $bold), $y, $text, $size, $bold, $rgb);
    }

    public function width(string $text, float $size, bool $bold): float
    {
        $bytes = self::latin($text);
        $sum = 0;
        $len = strlen($bytes);
        for ($i = 0; $i < $len; $i++) {
            $sum += self::GLYPH[$bytes[$i]] ?? 560;
        }
        return $sum / 1000 * $size * ($bold ? 1.06 : 1);
    }

    /** @return list<string> */
    public function wrap(string $text, float $max, float $size, bool $bold): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($text === '') {
            return [''];
        }
        $words = explode(' ', $text);
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $try = $current === '' ? $word : $current . ' ' . $word;
            if ($this->width($try, $size, $bold) <= $max) {
                $current = $try;
                continue;
            }
            if ($current !== '') {
                $lines[] = $current;
            }
            $current = $word;
        }
        if ($current !== '') {
            $lines[] = $current;
        }
        return $lines === [] ? [''] : $lines;
    }

    public function output(string $title): string
    {
        if ($this->ops !== '') {
            $this->pages[] = $this->ops;
            $this->ops = '';
        }
        if ($this->pages === []) {
            $this->pages[] = '';
        }
        $count = count($this->pages);
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $font1 = 3;
        $font2 = 4;
        $objects[$font1] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[$font2] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $next = 5;
        for ($i = 0; $i < $count; $i++) {
            $pageId = $next++;
            $streamId = $next++;
            $kids[] = $pageId . ' 0 R';
            $stream = $this->pages[$i];
            $objects[$streamId] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $streamId . ' 0 R >>';
        }
        $objects[2] = '<< /Type /Pages /Count ' . $count . ' /Kids [' . implode(' ', $kids) . '] >>';
        $info = $next;
        $objects[$info] = '<< /Title ' . self::literal($title) . ' /Author (SmartPlanet) /Creator (SmartPlanet) >>';

        $body = "%PDF-1.4\n";
        $offsets = [];
        $max = max(array_keys($objects));
        for ($id = 1; $id <= $max; $id++) {
            $offsets[$id] = strlen($body);
            $body .= $id . " 0 obj\n" . $objects[$id] . "\nendobj\n";
        }
        $start = strlen($body);
        $body .= "xref\n0 " . ($max + 1) . "\n";
        $body .= "0000000000 65535 f \n";
        for ($id = 1; $id <= $max; $id++) {
            $body .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $body .= 'trailer << /Size ' . ($max + 1) . ' /Root 1 0 R /Info ' . $info . " 0 R >>\n";
        $body .= "startxref\n{$start}\n%%EOF";
        return $body;
    }

    private static function literal(string $text): string
    {
        $bytes = self::latin($text);
        return '(' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $bytes) . ')';
    }

    private static function latin(string $text): string
    {
        $converted = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
        return $converted === '' && $text !== '' ? $text : $converted;
    }

    /** @var array<string,int> */
    private const GLYPH = [
        ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889, '&' => 667, "'" => 191,
        '(' => 333, ')' => 333, '*' => 389, '+' => 584, ',' => 278, '-' => 333, '.' => 278, '/' => 278,
        '0' => 556, '1' => 556, '2' => 556, '3' => 556, '4' => 556, '5' => 556, '6' => 556, '7' => 556, '8' => 556, '9' => 556,
        ':' => 278, ';' => 278, '<' => 584, '=' => 584, '>' => 584, '?' => 556, '@' => 1015,
        'A' => 667, 'B' => 667, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778, 'H' => 722, 'I' => 278, 'J' => 500,
        'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722, 'O' => 778, 'P' => 667, 'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611,
        'U' => 722, 'V' => 667, 'W' => 944, 'X' => 667, 'Y' => 667, 'Z' => 611,
        'a' => 556, 'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278, 'g' => 556, 'h' => 556, 'i' => 222, 'j' => 222,
        'k' => 500, 'l' => 222, 'm' => 833, 'n' => 556, 'o' => 556, 'p' => 556, 'q' => 556, 'r' => 333, 's' => 500, 't' => 278,
        'u' => 556, 'v' => 500, 'w' => 722, 'x' => 500, 'y' => 500, 'z' => 500,
    ];
}
