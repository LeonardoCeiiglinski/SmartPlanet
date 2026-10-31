<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use PDO;
use PDOException;

final class ProductService
{
    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,pages:int} */
    public function paginate(string $search, int $page = 1, int $perPage = 30): array
    {
        $user = Auth::user();
        $companyId = (int) $user['company_id'];
        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $where = 'p.company_id = :company';
        $params = ['company' => $companyId];
        if ($search !== '') {
            $like = '%' . mb_strtolower($search) . '%';
            $where .= ' AND (LOWER(p.description) LIKE :search OR LOWER(p.internal_code) LIKE :search_code
                        OR EXISTS (
                            SELECT 1 FROM product_barcodes search_pb
                            WHERE search_pb.product_id = p.id AND search_pb.barcode LIKE :raw
                        ))';
            $params['search'] = $like;
            $params['search_code'] = $like;
            $params['raw'] = '%' . $search . '%';
        }
        $pdo = Database::connection();
        $count = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE {$where}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $sql = "SELECT p.*, u.code AS unit_code, c.name AS category_name,
                       (SELECT pb.barcode FROM product_barcodes pb
                        WHERE pb.product_id = p.id AND pb.is_primary = 1 LIMIT 1) AS barcode,
                       COALESCE((SELECT SUM(sm.quantity) FROM stock_movements sm
                                 WHERE sm.product_id = p.id AND sm.company_id = p.company_id
                                   AND sm.branch_id = :stock_branch), 0) AS stock_quantity
                FROM products p
                JOIN units u ON u.id = p.unit_id
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE {$where}
                ORDER BY p.active DESC, p.description ASC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params + ['stock_branch' => (int) $user['branch_id']]);
        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function choices(): array
    {
        $user = Auth::user();
        $stmt = Database::connection()->prepare(
            'SELECT p.id, p.internal_code, p.description, p.active,
                    (SELECT pb.barcode FROM product_barcodes pb
                     WHERE pb.product_id = p.id AND pb.is_primary = 1 LIMIT 1) AS barcode
             FROM products p
             WHERE p.company_id = :company
             ORDER BY p.active DESC, p.description'
        );
        $stmt->execute(['company' => (int) $user['company_id']]);

        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $user = Auth::user();
        $stmt = Database::connection()->prepare(
            'SELECT p.*, pb.barcode
             FROM products p
             LEFT JOIN product_barcodes pb ON pb.product_id = p.id AND pb.is_primary = 1
             WHERE p.id = :id AND p.company_id = :company'
        );
        $stmt->execute(['id' => $id, 'company' => $user['company_id']]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @param array<string,mixed> $input */
    public function save(array $input, ?int $id = null): int
    {
        $user = Auth::user();
        $companyId = (int) $user['company_id'];
        $branchId = (int) $user['branch_id'];
        $description = trim((string) ($input['description'] ?? ''));
        $type = ($input['type'] ?? 'product') === 'service' ? 'service' : 'product';
        $barcode = trim((string) ($input['barcode'] ?? ''));
        $internal = trim((string) ($input['internal_code'] ?? ''));
        $unitId = (int) ($input['unit_id'] ?? 0);
        $salePrice = self::decimal($input['sale_price'] ?? '');
        $costPrice = self::decimal($input['cost_price'] ?? '0');
        $minimum = self::quantity($input['minimum_stock'] ?? '0');
        $initial = self::quantity($input['initial_stock'] ?? '0');
        if ($description === '' || mb_strlen($description) > 400 || $unitId < 1) {
            throw new \InvalidArgumentException('Descrição e unidade são obrigatórias.');
        }
        if ($salePrice === null || $costPrice === null || $minimum === null || $initial === null) {
            throw new \InvalidArgumentException('Preço ou quantidade inválidos.');
        }
        if ($barcode !== '' && (strlen($barcode) > 64 || !preg_match('/^[\x20-\x7E]+$/', $barcode))) {
            throw new \InvalidArgumentException('Código de barras inválido.');
        }
        if ($internal === '') {
            $internal = 'INT-' . strtoupper(bin2hex(random_bytes(6)));
        }
        if (strlen($internal) > 64) {
            throw new \InvalidArgumentException('Código interno muito longo.');
        }

        return Database::transactional(function (PDO $pdo) use (
            $input, $id, $user, $companyId, $branchId, $description, $type, $barcode,
            $internal, $unitId, $salePrice, $costPrice, $minimum, $initial
        ): int {
            $before = $id ? $this->find($id) : null;
            $now = date('Y-m-d H:i:s');
            $params = [
                'company' => $companyId,
                'category' => self::nullableId($input['category_id'] ?? null),
                'brand' => self::nullableId($input['brand_id'] ?? null),
                'unit' => $unitId,
                'supplier' => self::nullableId($input['main_supplier_id'] ?? null),
                'internal' => $internal,
                'type' => $type,
                'description' => $description,
                'complementary' => self::nullableText($input['complementary_name'] ?? null),
                'model' => self::nullableText($input['model'] ?? null),
                'cost' => $costPrice,
                'sale' => $salePrice,
                'promotional' => self::nullableDecimal($input['promotional_price'] ?? null),
                'minimum' => $minimum,
                'track' => isset($input['track_stock']) && $type === 'product' ? 1 : 0,
                'active' => isset($input['active']) ? 1 : 0,
                'notes' => self::nullableText($input['notes'] ?? null),
                'ncm' => self::nullableText($input['ncm'] ?? null),
                'cest' => self::nullableText($input['cest'] ?? null),
                'origin' => self::nullableText($input['origin_code'] ?? null),
                'cfop' => self::nullableText($input['cfop'] ?? null),
                'cst' => self::nullableText($input['cst_csosn'] ?? null),
                'updated' => $now,
            ];
            try {
                if ($id === null) {
                    $params['created'] = $now;
                    $pdo->prepare(
                        'INSERT INTO products
                         (company_id, category_id, brand_id, unit_id, main_supplier_id, internal_code, type,
                          description, complementary_name, model, cost_price, sale_price, promotional_price,
                          minimum_stock, track_stock, active, notes, ncm, cest, origin_code, cfop, cst_csosn,
                          created_at, updated_at)
                         VALUES
                         (:company, :category, :brand, :unit, :supplier, :internal, :type, :description,
                          :complementary, :model, :cost, :sale, :promotional, :minimum, :track, :active,
                          :notes, :ncm, :cest, :origin, :cfop, :cst, :created, :updated)'
                    )->execute($params);
                    $id = (int) $pdo->lastInsertId();
                } else {
                    if ($before === null) {
                        throw new \RuntimeException('Produto não encontrado.');
                    }
                    $params['id'] = $id;
                    $pdo->prepare(
                        'UPDATE products SET category_id=:category, brand_id=:brand, unit_id=:unit,
                         main_supplier_id=:supplier, internal_code=:internal, type=:type, description=:description,
                         complementary_name=:complementary, model=:model, cost_price=:cost, sale_price=:sale,
                         promotional_price=:promotional, minimum_stock=:minimum, track_stock=:track, active=:active,
                         notes=:notes, ncm=:ncm, cest=:cest, origin_code=:origin, cfop=:cfop, cst_csosn=:cst,
                         version=version+1, updated_at=:updated
                         WHERE id=:id AND company_id=:company'
                    )->execute($params);
                }

                $pdo->prepare('DELETE FROM product_barcodes WHERE product_id = :product AND is_primary = 1')
                    ->execute(['product' => $id]);
                if ($barcode !== '') {
                    $pdo->prepare(
                        'INSERT INTO product_barcodes (company_id, product_id, barcode, symbology, is_primary, created_at)
                         VALUES (:company, :product, :barcode, :symbology, 1, :created)'
                    )->execute([
                        'company' => $companyId,
                        'product' => $id,
                        'barcode' => $barcode,
                        'symbology' => self::symbology($barcode),
                        'created' => $now,
                    ]);
                }
            } catch (PDOException $exception) {
                if (str_contains($exception->getMessage(), 'UNIQUE') || (string) $exception->getCode() === '23000') {
                    throw new \InvalidArgumentException('Código interno ou código de barras já está cadastrado.');
                }
                throw $exception;
            }

            $after = $this->find($id);
            $pdo->prepare(
                'INSERT INTO product_history (product_id, user_id, snapshot_json, reason, created_at)
                 VALUES (:product, :user, :snapshot, :reason, :created)'
            )->execute([
                'product' => $id,
                'user' => $user['id'],
                'snapshot' => json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'reason' => $before === null ? 'Cadastro' : 'Alteração',
                'created' => $now,
            ]);

            if ($before === null && $initial !== '0.0000' && $type === 'product') {
                $location = $pdo->prepare(
                    'SELECT id FROM stock_locations WHERE company_id = :company AND branch_id = :branch AND active = 1 ORDER BY id LIMIT 1'
                );
                $location->execute(['company' => $companyId, 'branch' => $branchId]);
                $locationId = (int) $location->fetchColumn();
                $pdo->prepare(
                    'INSERT INTO stock_movements
                     (company_id, branch_id, location_id, product_id, movement_type, quantity, unit_cost,
                      source_type, source_id, source_line, reason, user_id, occurred_at)
                     VALUES (:company, :branch, :location, :product, :type, :quantity, :cost,
                             :source_type, :source_id, :line, :reason, :user, :at)'
                )->execute([
                    'company' => $companyId, 'branch' => $branchId, 'location' => $locationId,
                    'product' => $id, 'type' => 'opening', 'quantity' => $initial, 'cost' => $costPrice,
                    'source_type' => 'product_opening', 'source_id' => (string) $id, 'line' => '1',
                    'reason' => 'Saldo inicial do cadastro', 'user' => $user['id'], 'at' => $now,
                ]);
            }

            Audit::log($before === null ? 'product.created' : 'product.updated', 'products', $id, $before, $after);
            return $id;
        });
    }

    public function setActive(int $id, bool $active): void
    {
        $before = $this->find($id);
        if ($before === null) {
            throw new \RuntimeException('Produto não encontrado.');
        }
        $stmt = Database::connection()->prepare(
            'UPDATE products SET active=:active, version=version+1, updated_at=:at
             WHERE id=:id AND company_id=:company'
        );
        $stmt->execute([
            'active' => $active ? 1 : 0, 'at' => date('Y-m-d H:i:s'),
            'id' => $id, 'company' => Auth::user()['company_id'],
        ]);
        Audit::log('product.status_changed', 'products', $id, $before, $this->find($id));
    }

    private static function decimal(mixed $value): ?string
    {
        $value = str_replace(['R$', ' ', '.'], '', trim((string) $value));
        $value = str_replace(',', '.', $value);
        if (!preg_match('/^\d{1,13}(?:\.\d{1,2})?$/', $value)) {
            return null;
        }
        return number_format((float) $value, 2, '.', '');
    }

    private static function nullableDecimal(mixed $value): ?string
    {
        return trim((string) $value) === '' ? null : self::decimal($value);
    }

    private static function quantity(mixed $value): ?string
    {
        $value = str_replace(',', '.', trim((string) $value));
        if (!preg_match('/^\d{1,11}(?:\.\d{1,4})?$/', $value)) {
            return null;
        }
        return number_format((float) $value, 4, '.', '');
    }

    private static function nullableId(mixed $value): ?int
    {
        $id = (int) $value;
        return $id > 0 ? $id : null;
    }

    private static function nullableText(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private static function symbology(string $barcode): string
    {
        if (preg_match('/^\d{13}$/', $barcode)) {
            return 'EAN13';
        }
        if (preg_match('/^\d{12}$/', $barcode)) {
            return 'UPCA';
        }
        return 'CODE128';
    }
}

