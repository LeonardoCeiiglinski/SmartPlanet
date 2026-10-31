<?php
/** @var string $title */
/** @var string $description */
/** @var string $canonical */
/** @var string $ogImage */
/** @var string $content */
/** @var string $path */
/** @var string $jsonLd */
/** @var array<int, array{name:string,path:string}> $crumbs */
$nav = array_filter(site_pages(), static fn (array $item): bool => isset($item['nav']));
$robots = $robots ?? 'index,follow';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e($description) ?>">
  <link rel="canonical" href="<?= e($canonical) ?>">
  <meta name="robots" content="<?= e($robots) ?>">
  <meta name="theme-color" content="#070707">
  <meta name="author" content="SmartPlanet">
  <meta name="geo.region" content="BR-SC">
  <meta name="geo.placename" content="São José">
  <meta name="geo.position" content="<?= SITE_LAT ?>;<?= SITE_LNG ?>">
  <meta name="ICBM" content="<?= SITE_LAT ?>, <?= SITE_LNG ?>">
  <meta property="og:locale" content="pt_BR">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
  <meta property="og:title" content="<?= e($title) ?>">
  <meta property="og:description" content="<?= e($description) ?>">
  <meta property="og:url" content="<?= e($canonical) ?>">
  <meta property="og:image" content="<?= e($ogImage) ?>">
  <meta property="og:image:alt" content="<?= e($imageAlt ?? 'Interior da SmartPlanet, em Campinas, São José/SC') ?>">
  <meta property="og:image:type" content="image/jpeg">
  <meta property="og:image:width" content="<?= (int) ($ogWidth ?? 1200) ?>">
  <meta property="og:image:height" content="<?= (int) ($ogHeight ?? 630) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($title) ?>">
  <meta name="twitter:description" content="<?= e($description) ?>">
  <meta name="twitter:image" content="<?= e($ogImage) ?>">
  <link rel="icon" type="image/png" href="/assets/img/logo.png?v=3">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,480;0,6..72,620;1,6..72,480&family=Schibsted+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/site.css?v=4">
  <?php if ($jsonLd !== ''): ?><script type="application/ld+json"><?= $jsonLd ?></script><?php endif ?>
  <script>document.documentElement.classList.add('js')</script>
</head>
<body class="<?= $path === '/' ? 'is-home' : 'is-inner' ?><?= $path === '/contato' ? ' is-contact' : '' ?>">
  <a class="skip" href="#conteudo">Ir para o conteúdo</a>
  <div class="progress" data-progress hidden></div>
  <header class="top" data-top>
    <div class="top-bar">
      <p><?= e(SITE_PLACE) ?> · <?= e(SITE_REF) ?> · Horário: confirme no WhatsApp</p>
      <a href="<?= e(wa_link('Oi, vim pelo site da SmartPlanet.')) ?>" rel="noopener noreferrer" title="Chamar no WhatsApp <?= e(SITE_PHONE_HUMAN) ?>">WhatsApp <?= e(SITE_PHONE_HUMAN) ?></a>
    </div>
    <div class="nav-wrap">
      <a class="brand" href="/" aria-label="SmartPlanet – a Boutique do Celular, início">
        <img src="/assets/img/logo.png?v=3" alt="SmartPlanet – a Boutique do Celular" width="54" height="54">
        <span>Smart<span>Planet</span></span>
      </a>
      <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="menu">Menu</button>
      <nav id="menu" class="menu" aria-label="Menu principal">
        <?php foreach ($nav as $href => $item): ?>
          <a href="<?= e($href) ?>"<?= $href === $path ? ' aria-current="page"' : '' ?>><?= e($item['nav']) ?></a>
        <?php endforeach ?>
      </nav>
    </div>
  </header>
  <main id="conteudo">
    <?php if ($path !== '/'): ?>
      <nav class="crumbs" aria-label="Caminho da página">
        <?php foreach ($crumbs as $i => $crumb): ?>
          <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif ?>
          <?php if ($crumb['path'] === $path): ?>
            <span><?= e($crumb['name']) ?></span>
          <?php else: ?>
            <a href="<?= e($crumb['path']) ?>"><?= e($crumb['name']) ?></a>
          <?php endif ?>
        <?php endforeach ?>
      </nav>
    <?php endif ?>
    <?= $content ?>
  </main>
  <footer class="foot">
    <div class="foot-brand">
      <img src="/assets/img/logo.png?v=3" alt="SmartPlanet – a Boutique do Celular" width="92" height="92">
      <p><strong>SmartPlanet</strong><br><?= e(SITE_TAG) ?></p>
    </div>
    <address>
      <?= e(SITE_STREET) ?><br>
      <?= e(SITE_PLACE) ?> · <?= e(SITE_CEP) ?><br>
      Referência: <?= e(SITE_REF) ?>, onde fica o Grauvi Empório Natural<br>
      Horário: <?= e(SITE_HOURS) ?><br>
      <a href="<?= e(wa_link()) ?>" rel="noopener noreferrer" title="Chamar no WhatsApp <?= e(SITE_PHONE_HUMAN) ?>"><?= e(SITE_PHONE_HUMAN) ?></a>
    </address>
    <nav aria-label="Rodapé">
      <a href="/a-loja">A loja</a>
      <a href="/acessorios">Acessórios</a>
      <a href="/peliculas">Películas</a>
      <a href="/carregadores">Carregadores e cabos</a>
      <a href="/fones">Fones</a>
      <a href="/linha-gamer">Linha gamer</a>
      <a href="/iphone">iPhone</a>
      <a href="/entrega">Entrega</a>
      <a href="/onde-estamos">Onde estamos</a>
      <a href="/contato">Contato</a>
      <a href="<?= e(SITE_INSTAGRAM) ?>" rel="me noopener noreferrer">Instagram</a>
      <a href="<?= e(SITE_FACEBOOK) ?>" rel="me noopener noreferrer">Facebook</a>
      <a href="<?= e(maps_link()) ?>" rel="noopener noreferrer">Mapa</a>
      <a href="/privacidade">Privacidade</a>
    </nav>
    <p class="fine">© <?= date('Y') ?> SmartPlanet. <?= e(SITE_STREET) ?>, <?= e(SITE_PLACE) ?>.</p>
  </footer>
  <a class="zap" href="<?= e(wa_link('Oi, vim pelo site da SmartPlanet.')) ?>" rel="noopener noreferrer" title="Chamar no WhatsApp" aria-label="Chamar no WhatsApp, <?= e(SITE_PHONE_HUMAN) ?>">WhatsApp</a>
  <script src="/assets/js/site.js" defer></script>
</body>
</html>
