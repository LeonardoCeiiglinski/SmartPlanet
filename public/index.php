<?php

declare(strict_types=1);

$bootstrap = dirname(__DIR__) . '/app/bootstrap.php';
if (!is_file($bootstrap)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: inline');
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>SmartPlanet</title><body><h1>O sistema não abriu</h1><p>A Vercel está publicando só a pasta public, então o navegador recebia o programa em vez da página. Em Settings → General → Root Directory, apague public, salve e faça Redeploy.</p></body></html>';
    exit;
}
header('Content-Disposition: inline');
require $bootstrap;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Router;
use App\Core\View;
use App\Http\AuthController;
use App\Http\CashController;
use App\Http\ImportController;
use App\Http\LabelController;
use App\Http\ManagementController;
use App\Http\PeopleController;
use App\Http\PosController;
use App\Http\ProcedureController;
use App\Http\ProductController;
use App\Http\PurchaseController;
use App\Http\ReportController;
use App\Http\SettingsController;
use App\Http\SalesController;
use App\Http\StockController;
use App\Http\UserController;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$static = [
    '/favicon.png' => [__DIR__ . '/favicon.png', 'image/png'],
    '/favicon.ico' => [__DIR__ . '/favicon.png', 'image/png'],
];
if (isset($static[$path]) && is_file($static[$path][0])) {
    header('Content-Type: ' . $static[$path][1]);
    header('Cache-Control: public, max-age=86400');
    readfile($static[$path][0]);
    exit;
}
if (str_starts_with($path, '/assets/')) {
    $file = __DIR__ . $path;
    $real = realpath($file);
    $root = realpath(__DIR__ . '/assets');
    if ($real && $root && str_starts_with($real, $root) && is_file($real)) {
        $types = ['css' => 'text/css', 'js' => 'text/javascript', 'png' => 'image/png', 'svg' => 'image/svg+xml', 'woff2' => 'font/woff2'];
        $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream') . '; charset=UTF-8');
        header('Cache-Control: public, max-age=3600');
        readfile($real);
        exit;
    }
}

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
    echo '<body><h1>Instalação necessária</h1>';
    echo '<p>Configure o MySQL na Vercel e as variáveis <code>DATABASE_URL</code> (ou <code>DB_*</code>), <code>INSTALL_EMAIL</code> e <code>INSTALL_PASSWORD</code>.</p>';
    echo '<p>Em ambiente local: <code>php bin/migrate.php</code> e <code>php bin/install.php ...</code>.</p></body></html>';
    exit;
}

$router = new Router();
$auth = new AuthController();
$cash = new CashController();
$imports = new ImportController();
$labels = new LabelController();
$management = new ManagementController();
$products = new ProductController();
$people = new PeopleController();
$pos = new PosController();
$purchases = new PurchaseController();
$stock = new StockController();
$users = new UserController();
$settings = new SettingsController();
$reports = new ReportController();
$procedures = new ProcedureController();
$sales = new SalesController();

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
           (SELECT COUNT(*) FROM cash_sessions WHERE company_id=:company4 AND branch_id=:branch4 AND status='open') AS open_cash,
           (SELECT COUNT(*) FROM products p WHERE p.company_id=:company5 AND p.track_stock=1 AND p.active=1 AND COALESCE((SELECT SUM(quantity) FROM stock_movements m WHERE m.product_id=p.id),0) <= p.minimum_stock) AS low_stock,
           (SELECT COALESCE(SUM(amount-settled_amount),0) FROM financial_entries WHERE company_id=:company6 AND branch_id=:branch6 AND direction='in' AND status IN ('open','partial')) AS receivable,
           (SELECT COALESCE(SUM(amount-settled_amount),0) FROM financial_entries WHERE company_id=:company7 AND branch_id=:branch7 AND direction='out' AND status IN ('open','partial')) AS payable"
    );
    $stmt->execute([
        'company' => $company, 'branch' => $branch, 'today' => $today . ' 00:00:00',
        'company2' => $company, 'branch2' => $branch, 'today2' => $today . ' 00:00:00',
        'company3' => $company, 'company4' => $company, 'branch4' => $branch,
        'company5' => $company, 'company6' => $company, 'branch6' => $branch,
        'company7' => $company, 'branch7' => $branch,
    ]);
    View::render('dashboard', ['title' => 'Painel', 'stats' => $stmt->fetch()]);
});

$router->get('/products', [$products, 'index']);
$router->get('/products/new', [$products, 'form']);
$router->get('/products/edit', [$products, 'form']);
$router->post('/products/save', [$products, 'save']);
$router->post('/products/status', [$products, 'status']);
$router->get('/products/export', [$imports, 'export']);
$router->get('/products/import', [$imports, 'productsForm']);
$router->get('/products/import/template', [$imports, 'template']);
$router->post('/products/import/upload', [$imports, 'upload']);
$router->post('/products/import/confirm', [$imports, 'confirm']);
$router->get('/labels', [$labels, 'index']);
$router->post('/labels/history', [$labels, 'printHistory']);
$router->post('/labels/migration/preview', [$labels, 'migrationPreview']);
$router->post('/labels/migration', [$labels, 'migrate']);
$router->get('/people', [$people, 'index']);
$router->post('/people/save', [$people, 'save']);
$router->get('/stock', [$stock, 'index']);
$router->post('/stock/adjust', [$stock, 'adjust']);
$router->post('/stock/inventory/start', [$stock, 'inventoryStart']);
$router->post('/stock/inventory/close', [$stock, 'inventoryClose']);
$router->get('/purchases', [$purchases, 'index']);
$router->post('/purchases/create', [$purchases, 'create']);
$router->post('/purchases/receive', [$purchases, 'receive']);
$router->post('/purchases/cancel', [$purchases, 'cancel']);
$router->get('/users', [$users, 'index']);
$router->get('/users/new', [$users, 'form']);
$router->get('/users/edit', [$users, 'form']);
$router->post('/users/save', [$users, 'save']);
$router->get('/settings', [$settings, 'index']);
$router->post('/settings/company', [$settings, 'company']);
$router->post('/settings/dictionary', [$settings, 'dictionary']);
$router->get('/audit', [$settings, 'audit']);
$router->get('/pos', [$pos, 'index']);
$router->get('/api/products/search', [$pos, 'search']);
$router->post('/pos/complete', [$pos, 'complete']);
$router->post('/pos/suspend', [$pos, 'suspend']);
$router->post('/pos/recover', [$pos, 'recover']);
$router->get('/sales', [$sales, 'index']);
$router->post('/sales/cancel', [$pos, 'cancel']);
$router->post('/sales/return', [$management, 'returnSale']);
$router->get('/quotes', [$management, 'quotes']);
$router->post('/quotes/create', [$management, 'quoteCreate']);
$router->post('/quotes/convert', [$management, 'quoteConvert']);
$router->post('/quotes/cancel', [$management, 'quoteCancel']);
$router->get('/finance', [$management, 'finance']);
$router->post('/finance/create', [$management, 'financeCreate']);
$router->post('/finance/settle', [$management, 'financeSettle']);
$router->post('/finance/cancel', [$management, 'financeCancel']);
$router->get('/reports', [$reports, 'index']);
$router->get('/procedures', [$procedures, 'index']);
$router->get('/cash', [$cash, 'index']);
$router->post('/cash/open', [$cash, 'open']);
$router->post('/cash/move', [$cash, 'move']);
$router->post('/cash/close', [$cash, 'close']);

$router->dispatch(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), rtrim($path, '/') ?: '/');

