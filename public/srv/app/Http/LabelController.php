<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use App\Domain\Catalog\ProductService;
use App\Domain\Labels\LabelService;

final class LabelController
{
    public function __construct(
        private readonly LabelService $service = new LabelService(),
        private readonly ProductService $products = new ProductService()
    ) {
    }

    public function index(): void
    {
        Auth::authorize('labels.print');
        $user = Auth::user();
        $stmt = Database::connection()->prepare(
            'SELECT p.id, p.description, p.sale_price,
                    (SELECT pb.barcode FROM product_barcodes pb
                     WHERE pb.product_id = p.id AND pb.is_primary = 1 LIMIT 1) AS barcode
             FROM products p
             WHERE p.company_id = :company AND p.active = 1
             ORDER BY p.description
             LIMIT 400'
        );
        $stmt->execute(['company' => $user['company_id']]);

        $prefill = null;
        $productId = (int) ($_GET['product'] ?? 0);
        if ($productId > 0) {
            $found = $this->products->find($productId);
            if (is_array($found) && (int) ($found['active'] ?? 1) === 1) {
                $qty = (int) ($_GET['qty'] ?? 9);
                if ($qty < 1) {
                    $qty = 9;
                }
                if ($qty > 9999) {
                    $qty = 9999;
                }
                $prefill = [
                    'id' => (int) $found['id'],
                    'nome' => (string) $found['description'],
                    'precoCentavos' => (int) round(((float) $found['sale_price']) * 100),
                    'codigo' => (string) ($found['barcode'] ?? ''),
                    'quantidade' => $qty,
                ];
            }
        }

        View::render('labels/index', [
            'title' => 'Etiquetas',
            'bodyClass' => 'page-labels',
            'catalog' => $stmt->fetchAll(),
            'prefill' => $prefill,
        ]);
    }

    public function printHistory(): never
    {
        Auth::authorize('labels.print');
        Csrf::enforce();
        $body = $this->jsonBody();
        $this->service->recordPrint(
            is_array($body['items'] ?? null) ? $body['items'] : [],
            (string) ($body['method'] ?? ''),
            is_array($body['settings'] ?? null) ? $body['settings'] : []
        );
        View::json(['ok' => true], 201);
    }

    public function migrationPreview(): never
    {
        Auth::authorize('labels.print');
        Csrf::enforce();
        $body = $this->jsonBody();
        View::json($this->service->previewMigration(is_array($body['products'] ?? null) ? $body['products'] : []));
    }

    public function migrate(): never
    {
        Auth::authorize('labels.print');
        Csrf::enforce();
        $body = $this->jsonBody();
        $result = $this->service->migrate(
            is_array($body['products'] ?? null) ? $body['products'] : [],
            is_array($body['indices'] ?? null) ? $body['indices'] : []
        );
        View::json($result, 201);
    }

    /** @return array<string,mixed> */
    private function jsonBody(): array
    {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('JSON inválido.');
        }
        return $decoded;
    }
}
