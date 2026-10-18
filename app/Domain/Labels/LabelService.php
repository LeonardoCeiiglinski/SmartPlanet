<?php

declare(strict_types=1);

namespace App\Domain\Labels;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Domain\Catalog\ProductService;
use PDO;

final class LabelService
{
    /** @return array<int,array<string,mixed>> */
    public function products(): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.id, p.internal_code, p.description, p.sale_price,
                    (SELECT pb.barcode FROM product_barcodes pb
                     WHERE pb.product_id=p.id AND pb.is_primary=1 LIMIT 1) AS barcode
             FROM products p
             WHERE p.company_id=:company AND p.active=1
             ORDER BY p.description'
        );
        $stmt->execute(['company' => Auth::user()['company_id']]);
        return array_map(static fn (array $row): array => [
            'id' => (string) $row['id'],
            'nome' => (string) $row['description'],
            'precoCentavos' => (int) round((float) $row['sale_price'] * 100),
            'codigo' => (string) ($row['barcode'] ?? ''),
            'codigoInterno' => (string) $row['internal_code'],
        ], $stmt->fetchAll());
    }

    /** @param array<int,mixed> $legacy
     *  @return array{novos:array<int,array<string,mixed>>,conflitos:array<int,array<string,mixed>>,invalidos:array<int,array<string,mixed>>}
     */
    public function previewMigration(array $legacy): array
    {
        $central = $this->products();
        $byBarcode = [];
        foreach ($central as $product) {
            if ($product['codigo'] !== '') {
                $byBarcode[$product['codigo']] = $product;
            }
        }
        $legacyBarcodes = [];
        $result = ['novos' => [], 'conflitos' => [], 'invalidos' => []];
        foreach (array_slice($legacy, 0, 1000) as $index => $item) {
            $clean = $this->sanitizeLegacy($item);
            if ($clean === null) {
                $result['invalidos'][] = ['indice' => $index, 'motivo' => 'Nome, preço, quantidade ou código inválido.'];
                continue;
            }
            $existing = $clean['codigo'] !== '' ? ($byBarcode[$clean['codigo']] ?? null) : null;
            if ($existing !== null) {
                $same = $existing['nome'] === $clean['nome']
                    && $existing['precoCentavos'] === $clean['precoCentavos'];
                $result['conflitos'][] = [
                    'indice' => $index, 'legado' => $clean, 'central' => $existing,
                    'motivo' => $same ? 'Código já cadastrado no catálogo central.' : 'Código coincide, mas nome ou preço diverge.',
                ];
                continue;
            }
            if ($clean['codigo'] !== '' && isset($legacyBarcodes[$clean['codigo']])) {
                $result['conflitos'][] = [
                    'indice' => $index, 'legado' => $clean, 'central' => null,
                    'motivo' => 'Código repetido em outro produto do armazenamento antigo.',
                ];
                continue;
            }
            if ($clean['codigo'] !== '') {
                $legacyBarcodes[$clean['codigo']] = true;
            }
            $result['novos'][] = ['indice' => $index, 'produto' => $clean];
        }
        return $result;
    }

    /** @param array<int,mixed> $legacy
     *  @param array<int,int> $indices
     *  @return array{importados:int,ignorados:int}
     */
    public function migrate(array $legacy, array $indices): array
    {
        $preview = $this->previewMigration($legacy);
        $allowed = [];
        foreach ($preview['novos'] as $candidate) {
            $allowed[(int) $candidate['indice']] = $candidate['produto'];
        }
        $wanted = array_fill_keys(array_map('intval', $indices), true);
        $unit = Database::connection()->prepare(
            'SELECT id FROM units WHERE company_id=:company AND active=1 ORDER BY id LIMIT 1'
        );
        $unit->execute(['company' => Auth::user()['company_id']]);
        $unitId = (int) $unit->fetchColumn();
        if ($unitId < 1) {
            throw new \RuntimeException('Cadastre ao menos uma unidade antes de migrar produtos.');
        }
        $service = new ProductService();
        $imported = 0;
        foreach ($allowed as $index => $product) {
            if (!isset($wanted[$index])) {
                continue;
            }
            $service->save([
                'description' => $product['nome'],
                'sale_price' => number_format($product['precoCentavos'] / 100, 2, ',', ''),
                'cost_price' => '0',
                'minimum_stock' => '0',
                'initial_stock' => '0',
                'barcode' => $product['codigo'],
                'unit_id' => $unitId,
                'type' => 'product',
                'active' => '1',
                'track_stock' => '1',
            ]);
            $imported++;
        }
        Audit::log('labels.legacy_migrated', 'products', null, null, ['count' => $imported]);
        return ['importados' => $imported, 'ignorados' => count($indices) - $imported];
    }

    /** @param array<int,array<string,mixed>> $items */
    public function recordPrint(array $items, string $method, array $settings): void
    {
        $user = Auth::user();
        $validMethods = ['direto', 'navegador', 'prn'];
        if (!in_array($method, $validMethods, true) || !$items || count($items) > 1000) {
            throw new \InvalidArgumentException('Histórico de impressão inválido.');
        }
        $productIds = [];
        foreach ($items as $item) {
            $id = (int) ($item['productId'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($id < 1 || $quantity < 1 || $quantity > 9999) {
                throw new \InvalidArgumentException('Produto ou quantidade inválida.');
            }
            $productIds[$id] = ($productIds[$id] ?? 0) + $quantity;
        }
        Database::transactional(function (PDO $pdo) use ($user, $productIds, $method, $settings): void {
            $check = $pdo->prepare('SELECT 1 FROM products WHERE id=:id AND company_id=:company AND active=1');
            $insert = $pdo->prepare(
                'INSERT INTO print_history
                 (company_id,branch_id,user_id,product_id,quantity,method,settings_json,printed_at)
                 VALUES (:company,:branch,:user,:product,:quantity,:method,:settings,:at)'
            );
            $encoded = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (strlen($encoded) > 20000) {
                throw new \InvalidArgumentException('Ajustes de impressão inválidos.');
            }
            foreach ($productIds as $id => $quantity) {
                $check->execute(['id' => $id, 'company' => $user['company_id']]);
                if (!$check->fetchColumn()) {
                    throw new \InvalidArgumentException('Produto não pertence ao catálogo central.');
                }
                $insert->execute([
                    'company' => $user['company_id'], 'branch' => $user['branch_id'], 'user' => $user['id'],
                    'product' => $id, 'quantity' => $quantity, 'method' => $method,
                    'settings' => $encoded, 'at' => date('Y-m-d H:i:s'),
                ]);
            }
        });
        Audit::log('labels.printed', 'print_history', null, null, [
            'method' => $method, 'products' => count($productIds), 'labels' => array_sum($productIds),
        ]);
    }

    /** @return array<string,mixed>|null */
    private function sanitizeLegacy(mixed $item): ?array
    {
        if (!is_array($item)) {
            return null;
        }
        $name = mb_substr(trim((string) ($item['nome'] ?? '')), 0, 400);
        $price = filter_var($item['precoCentavos'] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($item['quantidade'] ?? null, FILTER_VALIDATE_INT);
        $barcode = trim((string) ($item['codigo'] ?? ''));
        if ($name === '' || $price === false || $price < 0 || $quantity === false || $quantity < 1
            || strlen($barcode) > 64 || ($barcode !== '' && !preg_match('/^[\x20-\x7E]+$/', $barcode))) {
            return null;
        }
        return ['nome' => $name, 'precoCentavos' => $price, 'codigo' => $barcode, 'quantidade' => min(9999, $quantity)];
    }
}
