<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Domain\Catalog\ProductService;

final class ProductController
{
    public function __construct(private readonly ProductService $service = new ProductService())
    {
    }

    public function index(): void
    {
        Auth::authorize('products.view');
        $search = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        View::render('products/index', [
            'title' => 'Produtos e serviços',
            'result' => $this->service->paginate($search, $page),
            'search' => $search,
            'choices' => $this->service->choices(),
        ]);
    }

    public function form(): void
    {
        Auth::authorize('products.manage');
        $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
        $product = $id ? $this->service->find($id) : null;
        if ($id && $product === null) {
            http_response_code(404);
            throw new \RuntimeException('Produto não encontrado.');
        }
        View::render('products/form', [
            'title' => $id ? 'Editar produto' : 'Novo produto',
            'product' => $product,
            ...$this->options(),
        ]);
    }

    public function save(): void
    {
        Auth::authorize('products.manage');
        Csrf::enforce();
        $id = (int) ($_POST['id'] ?? 0) ?: null;
        try {
            $saved = $this->service->save($_POST, $id);
            View::redirect('/products/edit?id=' . $saved, 'sucesso', $id ? 'Produto atualizado.' : 'Produto cadastrado.');
        } catch (\InvalidArgumentException $exception) {
            Session::flash('erro', $exception->getMessage());
            View::redirect($id ? '/products/edit?id=' . $id : '/products/new');
        }
    }

    public function status(): void
    {
        Auth::authorize('products.manage');
        Csrf::enforce();
        $id = (int) ($_POST['id'] ?? 0);
        $active = ($_POST['active'] ?? '0') === '1';
        $this->service->setActive($id, $active);
        View::redirect('/products', 'sucesso', $active ? 'Produto ativado.' : 'Produto inativado.');
    }

    /** @return array<string,array<int,array<string,mixed>>> */
    private function options(): array
    {
        $company = Auth::user()['company_id'];
        $pdo = Database::connection();
        $queries = [
            'units' => 'SELECT id, code, name FROM units WHERE company_id=:company AND active=1 ORDER BY name',
            'categories' => 'SELECT id, name FROM categories WHERE company_id=:company AND active=1 ORDER BY name',
            'brands' => 'SELECT id, name FROM brands WHERE company_id=:company AND active=1 ORDER BY name',
            'suppliers' => 'SELECT id, name FROM suppliers WHERE company_id=:company AND active=1 ORDER BY name',
        ];
        $result = [];
        foreach ($queries as $key => $sql) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['company' => $company]);
            $result[$key] = $stmt->fetchAll();
        }
        return $result;
    }
}

