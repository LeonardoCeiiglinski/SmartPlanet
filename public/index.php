<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/site.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';
$pages = site_pages();

if ($path === '/robots.txt') {
    header('Content-Type: text/plain; charset=UTF-8');
    echo "User-agent: *\nAllow: /\nSitemap: " . site_url('/sitemap.xml') . "\n";
    exit;
}

if ($path === '/sitemap.xml') {
    header('Content-Type: application/xml; charset=UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($pages as $href => $page) {
        echo '<url><loc>' . e(site_url($href)) . '</loc><changefreq>weekly</changefreq></url>';
    }
    echo '</urlset>';
    exit;
}

$found = isset($pages[$path]);
$page = $found ? $pages[$path] : [
    'file' => '404.php',
    'title' => 'Página não encontrada · SmartPlanet',
    'description' => 'Esse endereço não existe na SmartPlanet. A loja fica em Campinas, São José/SC.',
    'crumb' => 'Não encontrada',
];

if (!$found) {
    http_response_code(404);
}

header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: inline');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');

$canonical = site_url($path);
$robots = $found ? 'index,follow,max-image-preview:large' : 'noindex,follow';
$description = $page['description'];
$title = $page['title'];
$ogImage = site_url('/assets/img/loja.jpg');

$crumbs = [['name' => 'Início', 'path' => '/']];
if ($path !== '/' && !empty($page['crumb'])) {
    $crumbs[] = ['name' => $page['crumb'], 'path' => $path];
}

$address = [
    '@type' => 'PostalAddress',
    'streetAddress' => SITE_STREET . ', Campinas',
    'addressLocality' => 'São José',
    'addressRegion' => 'SC',
    'postalCode' => SITE_CEP,
    'addressCountry' => 'BR',
];
$graph = [
    [
        '@type' => 'ElectronicsStore',
        '@id' => site_url('/') . '#loja',
        'name' => SITE_NAME,
        'alternateName' => SITE_TAG,
        'description' => 'Loja de acessórios para celular no bairro Campinas, São José/SC.',
        'url' => site_url('/'),
        'image' => [site_url('/assets/img/loja.jpg'), site_url('/assets/img/logo.png')],
        'telephone' => '+' . SITE_PHONE_E164,
        'address' => $address,
        'geo' => [
            '@type' => 'GeoCoordinates',
            'latitude' => SITE_LAT,
            'longitude' => SITE_LNG,
        ],
        'areaServed' => [
            '@type' => 'AdministrativeArea',
            'name' => 'Grande Florianópolis',
        ],
        'sameAs' => [SITE_INSTAGRAM, SITE_FACEBOOK],
        'hasMap' => maps_link(),
    ],
    [
        '@type' => 'Organization',
        '@id' => site_url('/') . '#organizacao',
        'name' => SITE_NAME,
        'url' => site_url('/'),
        'logo' => site_url('/assets/img/logo.png'),
        'telephone' => '+' . SITE_PHONE_E164,
        'address' => $address,
        'sameAs' => [SITE_INSTAGRAM, SITE_FACEBOOK],
    ],
    [
        '@type' => 'WebSite',
        '@id' => site_url('/') . '#site',
        'url' => site_url('/'),
        'name' => SITE_NAME,
        'inLanguage' => 'pt-BR',
        'publisher' => ['@id' => site_url('/') . '#organizacao'],
    ],
];
if (count($crumbs) > 1) {
    $items = [];
    foreach ($crumbs as $i => $crumb) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $crumb['name'],
            'item' => site_url($crumb['path']),
        ];
    }
    $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
}
$jsonLd = json_encode([
    '@context' => 'https://schema.org',
    '@graph' => $graph,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

ob_start();
require dirname(__DIR__) . '/app/pages/' . $page['file'];
$content = ob_get_clean();

require dirname(__DIR__) . '/app/layout.php';
