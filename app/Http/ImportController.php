<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Domain\Catalog\ProductService;
use PDO;

final class ImportController
{
    public function productsForm(): void
    {
        Auth::authorize('products.manage');
        $job = null;
        $rows = [];
        $id = (int) ($_GET['job'] ?? 0);
        if ($id) {
            $stmt = Database::connection()->prepare(
                'SELECT * FROM import_jobs WHERE id=:id AND company_id=:company'
            );
            $stmt->execute(['id' => $id, 'company' => Auth::user()['company_id']]);
            $job = $stmt->fetch() ?: null;
            if ($job) {
                $rowStmt = Database::connection()->prepare(
                    'SELECT * FROM import_job_rows WHERE import_job_id=:job ORDER BY row_number LIMIT 500'
                );
                $rowStmt->execute(['job' => $id]);
                $rows = $rowStmt->fetchAll();
            }
        }
        View::render('imports/products', [
            'title' => 'Importar produtos',
            'job' => $job,
            'rows' => $rows,
        ]);
    }

    public function template(): void
    {
        Auth::authorize('products.manage');
        $this->csvHeaders('modelo-produtos.csv');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'codigo_interno', 'codigo_barras', 'tipo', 'descricao', 'unidade',
            'preco_custo', 'preco_venda', 'estoque_inicial', 'estoque_minimo',
            'movimenta_estoque', 'ativo', 'ncm', 'cest', 'origem', 'cfop', 'cst_csosn',
        ], ';');
        fclose($out);
        exit;
    }

    public function export(): void
    {
        Auth::authorize('products.view');
        $stmt = Database::connection()->prepare(
            "SELECT p.internal_code,p.type,p.description,u.code AS unit_code,p.cost_price,p.sale_price,
                    p.minimum_stock,p.track_stock,p.active,p.ncm,p.cest,p.origin_code,p.cfop,p.cst_csosn,
                    MAX(CASE WHEN pb.is_primary=1 THEN pb.barcode END) AS barcode,
                    COALESCE((SELECT SUM(sm.quantity) FROM stock_movements sm WHERE sm.product_id=p.id),0) AS stock
             FROM products p JOIN units u ON u.id=p.unit_id
             LEFT JOIN product_barcodes pb ON pb.product_id=p.id
             WHERE p.company_id=:company GROUP BY p.id,u.code ORDER BY p.description"
        );
        $stmt->execute(['company' => Auth::user()['company_id']]);
        $this->csvHeaders('produtos-' . date('Y-m-d') . '.csv');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'codigo_interno', 'codigo_barras', 'tipo', 'descricao', 'unidade',
            'preco_custo', 'preco_venda', 'estoque_atual', 'estoque_minimo',
            'movimenta_estoque', 'ativo', 'ncm', 'cest', 'origem', 'cfop', 'cst_csosn',
        ], ';');
        while ($row = $stmt->fetch()) {
            fputcsv($out, [
                $row['internal_code'], $row['barcode'], $row['type'], $row['description'], $row['unit_code'],
                $row['cost_price'], $row['sale_price'], $row['stock'], $row['minimum_stock'],
                $row['track_stock'] ? 'SIM' : 'NAO', $row['active'] ? 'SIM' : 'NAO',
                $row['ncm'], $row['cest'], $row['origin_code'], $row['cfop'], $row['cst_csosn'],
            ], ';');
        }
        fclose($out);
        exit;
    }

    public function upload(): void
    {
        Auth::authorize('products.manage');
        Csrf::enforce();
        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Session::flash('erro', 'Selecione um arquivo CSV válido.');
            View::redirect('/products/import');
        }
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            Session::flash('erro', 'O arquivo deve ter no máximo 5 MB.');
            View::redirect('/products/import');
        }
        $handle = fopen((string) $file['tmp_name'], 'rb');
        if (!$handle) {
            throw new \RuntimeException('Não foi possível ler o arquivo.');
        }
        $first = fgets($handle);
        rewind($handle);
        $delimiter = substr_count((string) $first, ';') >= substr_count((string) $first, ',') ? ';' : ',';
        $headers = fgetcsv($handle, 0, $delimiter);
        if (!$headers) {
            throw new \InvalidArgumentException('Cabeçalho ausente.');
        }
        $headers = array_map(static fn ($h) => self::normalizeHeader((string) $h), $headers);
        $required = ['tipo', 'descricao', 'unidade', 'preco_venda'];
        if (array_diff($required, $headers)) {
            Session::flash('erro', 'Colunas obrigatórias: tipo, descricao, unidade e preco_venda.');
            View::redirect('/products/import');
        }

        $pdo = Database::connection();
        $user = Auth::user();
        $now = date('Y-m-d H:i:s');
        $pdo->prepare(
            'INSERT INTO import_jobs
             (company_id,branch_id,user_id,import_type,original_name,status,created_at)
             VALUES (:company,:branch,:user,:type,:name,:status,:created)'
        )->execute([
            'company' => $user['company_id'], 'branch' => $user['branch_id'], 'user' => $user['id'],
            'type' => 'products_csv', 'name' => basename((string) $file['name']), 'status' => 'validated', 'created' => $now,
        ]);
        $jobId = (int) $pdo->lastInsertId();
        $insert = $pdo->prepare(
            'INSERT INTO import_job_rows (import_job_id,row_number,data_json,errors_json,status)
             VALUES (:job,:row,:data,:errors,:status)'
        );
        $seen = [];
        $total = $valid = $invalid = 0;
        $rowNumber = 1;
        while (($columns = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;
            if (count(array_filter($columns, static fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $total++;
            $columns = array_pad($columns, count($headers), '');
            $data = array_combine($headers, array_slice($columns, 0, count($headers))) ?: [];
            $errors = $this->validateRow($data, $seen);
            $status = $errors === [] ? 'valid' : 'invalid';
            $status === 'valid' ? $valid++ : $invalid++;
            $insert->execute([
                'job' => $jobId, 'row' => $rowNumber,
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'errors' => $errors === [] ? null : json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'status' => $status,
            ]);
        }
        fclose($handle);
        $pdo->prepare(
            'UPDATE import_jobs SET total_rows=:total,valid_rows=:valid,invalid_rows=:invalid WHERE id=:id'
        )->execute(['total' => $total, 'valid' => $valid, 'invalid' => $invalid, 'id' => $jobId]);
        Audit::log('import.validated', 'import_jobs', $jobId);
        View::redirect('/products/import?job=' . $jobId);
    }

    public function confirm(): void
    {
        Auth::authorize('products.manage');
        Csrf::enforce();
        $jobId = (int) ($_POST['job_id'] ?? 0);
        $pdo = Database::connection();
        $jobStmt = $pdo->prepare(
            "SELECT * FROM import_jobs WHERE id=:id AND company_id=:company AND status='validated'"
        );
        $jobStmt->execute(['id' => $jobId, 'company' => Auth::user()['company_id']]);
        $job = $jobStmt->fetch();
        if (!$job || (int) $job['invalid_rows'] > 0) {
            throw new \RuntimeException('A importação não pode ser confirmada enquanto houver erros.');
        }
        $rows = $pdo->prepare("SELECT data_json FROM import_job_rows WHERE import_job_id=:job AND status='valid' ORDER BY row_number");
        $rows->execute(['job' => $jobId]);
        $service = new ProductService();
        Database::transactional(static function () use ($pdo, $rows, $service, $jobId): void {
            while ($json = $rows->fetchColumn()) {
                $data = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
                $unit = $pdo->prepare('SELECT id FROM units WHERE company_id=:company AND UPPER(code)=UPPER(:code)');
                $unit->execute(['company' => Auth::user()['company_id'], 'code' => $data['unidade']]);
                $input = [
                    'internal_code' => $data['codigo_interno'] ?? '',
                    'barcode' => $data['codigo_barras'] ?? '',
                    'type' => mb_strtolower((string) $data['tipo']) === 'servico' || mb_strtolower((string) $data['tipo']) === 'service' ? 'service' : 'product',
                    'description' => $data['descricao'],
                    'unit_id' => $unit->fetchColumn(),
                    'cost_price' => $data['preco_custo'] ?? '0',
                    'sale_price' => $data['preco_venda'],
                    'initial_stock' => $data['estoque_inicial'] ?? '0',
                    'minimum_stock' => $data['estoque_minimo'] ?? '0',
                    'track_stock' => self::isYes($data['movimenta_estoque'] ?? 'SIM') ? '1' : null,
                    'active' => self::isYes($data['ativo'] ?? 'SIM') ? '1' : null,
                    'ncm' => $data['ncm'] ?? '', 'cest' => $data['cest'] ?? '',
                    'origin_code' => $data['origem'] ?? '', 'cfop' => $data['cfop'] ?? '',
                    'cst_csosn' => $data['cst_csosn'] ?? '',
                ];
                $service->save($input);
            }
            $pdo->prepare("UPDATE import_jobs SET status='confirmed',confirmed_at=:at WHERE id=:id")
                ->execute(['at' => date('Y-m-d H:i:s'), 'id' => $jobId]);
            Audit::log('import.confirmed', 'import_jobs', $jobId);
        });
        View::redirect('/products', 'sucesso', 'Produtos importados.');
    }

    /** @param array<string,mixed> $data @param array<string,bool> $seen */
    private function validateRow(array $data, array &$seen): array
    {
        $errors = [];
        if (trim((string) ($data['descricao'] ?? '')) === '') {
            $errors[] = 'Descrição obrigatória';
        }
        if (trim((string) ($data['unidade'] ?? '')) === '') {
            $errors[] = 'Unidade obrigatória';
        }
        if (!preg_match('/^\d+(?:[.,]\d{1,2})?$/', trim((string) ($data['preco_venda'] ?? '')))) {
            $errors[] = 'Preço de venda inválido';
        }
        $barcode = trim((string) ($data['codigo_barras'] ?? ''));
        if ($barcode !== '') {
            if (isset($seen[$barcode])) {
                $errors[] = 'Código de barras duplicado no arquivo';
            }
            $seen[$barcode] = true;
            $stmt = Database::connection()->prepare(
                'SELECT 1 FROM product_barcodes WHERE company_id=:company AND barcode=:barcode'
            );
            $stmt->execute(['company' => Auth::user()['company_id'], 'barcode' => $barcode]);
            if ($stmt->fetchColumn()) {
                $errors[] = 'Código de barras já cadastrado';
            }
        }
        $unit = Database::connection()->prepare(
            'SELECT 1 FROM units WHERE company_id=:company AND UPPER(code)=UPPER(:code)'
        );
        $unit->execute(['company' => Auth::user()['company_id'], 'code' => trim((string) ($data['unidade'] ?? ''))]);
        if (!$unit->fetchColumn()) {
            $errors[] = 'Unidade não cadastrada';
        }
        return $errors;
    }

    private function csvHeaders(string $filename): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
    }

    private static function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', trim($header));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $header) ?: $header;
        return trim(preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($ascii)), '_');
    }

    private static function isYes(string $value): bool
    {
        return in_array(mb_strtoupper(trim($value)), ['SIM', 'S', '1', 'TRUE'], true);
    }
}

