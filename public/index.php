<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Router;
use App\Core\View;
use App\Http\AuthController;
use App\Http\PeopleController;
use App\Http\ProductController;
use App\Http\SettingsController;
use App\Http\UserController;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (str_starts_with($path, '/legacy/')) {
    $relative = substr($path, strlen('/legacy/'));
    $allowed = preg_match('#^(css/styles\.css|js/(app|layout|barcode|render|tspl)\.js)$#', $relative);
    $file = dirname(__DIR__) . '/' . $relative;
    if (!$allowed || !is_file($file)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . (str_ends_with($file, '.css') ? 'text/css' : 'text/javascript') . '; charset=UTF-8');
    header('Cache-Control: public, max-age=3600');
    readfile($file);
    exit;
}

try {
    Database::connection()->query('SELECT 1 FROM schema_migrations LIMIT 1');
} catch (Throwable $exception) {
    http_response_code(503);
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Instalação necessária</title>';
    echo '<body><h1>Instalação necessária</h1><p>Execute <code>php bin/migrate.php</code> e depois <code>php bin/install.php ...</code>.</p></body></html>';
    exit;
}

$router = new Router();
$auth = new AuthController();
$products = new ProductController();
$people = new PeopleController();
$users = new UserController();
$settings = new SettingsController();

$router->get('/login', [$auth, 'loginForm']);
$router->post('/login', [$auth, 'login']);
$router->post('/logout', [$auth, 'logout']);
$router->get('/forgot', [$auth, 'forgotForm']);
$router->post('/forgot', [$auth, 'forgot']);
$router->get('/reset', [$auth, 'resetForm']);
$router->post('/reset', [$auth, 'reset']);

$router->get('/', static function (): void {
    Auth::requireLogin();
    $user = Auth::user();
    $pdo = Database::connection();
    $company = (int) $user['company_id'];
    $branch = (int) $user['branch_id'];
    $today = date('Y-m-d');
    $stmt = $pdo->prepare(
        "SELECT
           (SELECT COUNT(*) FROM sales WHERE company_id=:company AND branch_id=:branch AND status='completed' AND completed_at>=:today) AS sales_count,
           (SELECT COALESCE(SUM(total),0) FROM sales WHERE company_id=:company2 AND branch_id=:branch2 AND status='completed' AND completed_at>=:today2) AS sales_total,
           (SELECT COUNT(*) FROM products WHERE company_id=:company3 AND active=1) AS products_count,
           (SELECT COUNT(*) FROM cash_sessions WHERE company_id=:company4 AND branch_id=:branch4 AND status='open') AS open_cash"
    );
    $stmt->execute([
        'company' => $company, 'branch' => $branch, 'today' => $today . ' 00:00:00',
        'company2' => $company, 'branch2' => $branch, 'today2' => $today . ' 00:00:00',
        'company3' => $company, 'company4' => $company, 'branch4' => $branch,
    ]);
    View::render('dashboard', ['title' => 'Painel', 'stats' => $stmt->fetch()]);
});

$router->get('/products', [$products, 'index']);
$router->get('/products/new', [$products, 'form']);
$router->get('/products/edit', [$products, 'form']);
$router->post('/products/save', [$products, 'save']);
$router->post('/products/status', [$products, 'status']);
$router->get('/people', [$people, 'index']);
$router->post('/people/save', [$people, 'save']);
$router->get('/users', [$users, 'index']);
$router->get('/users/new', [$users, 'form']);
$router->get('/users/edit', [$users, 'form']);
$router->post('/users/save', [$users, 'save']);
$router->get('/settings', [$settings, 'index']);
$router->post('/settings/company', [$settings, 'company']);
$router->post('/settings/dictionary', [$settings, 'dictionary']);
$router->get('/audit', [$settings, 'audit']);

$router->dispatch(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), rtrim($path, '/') ?: '/');

