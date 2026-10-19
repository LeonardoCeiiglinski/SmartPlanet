<header class="page-hero" data-reveal="fade">
  <p class="kicker">Onde estamos</p>
  <h1>Endereço da SmartPlanet em Campinas, São José</h1>
  <p class="lead">A loja fica na <?= e(SITE_STREET) ?>, CEP <?= e(SITE_CEP) ?>. Campinas é um bairro de São José/SC, não a cidade de Campinas em São Paulo.</p>
</header>

<section class="map-block">
  <div data-reveal>
    <h2><?= e(SITE_STREET) ?></h2>
    <address class="nap-block">
      <strong>SmartPlanet</strong><br>
      <?= e(SITE_STREET) ?><br>
      Bairro Campinas, São José/SC<br>
      CEP <?= e(SITE_CEP) ?><br>
      Referência: <?= e(SITE_REF) ?><br>
      <a href="<?= e(wa_link('Oi, vim pelo site da SmartPlanet. Estou a caminho da loja.')) ?>" rel="noopener noreferrer" title="Chamar no WhatsApp">WhatsApp <?= e(SITE_PHONE_HUMAN) ?></a>
    </address>
    <p>Use o mercado Direto do Campo, no número 34, como ponto de referência. A SmartPlanet fica nesse endereço.</p>
    <p>Antes de sair de casa, pergunte o horário pelo WhatsApp. Ele ainda não está publicado nesta página.</p>
    <p class="actions">
      <a class="btn gold" href="<?= e(maps_link()) ?>" rel="noopener noreferrer" title="Rota até a Av. Josué Di Bernardi, 34, São José, SC">Ver como chegar</a>
      <a class="text-link" href="/contato">Avisar que estou a caminho</a>
    </p>
  </div>
  <div class="map-frame" data-reveal="photo">
    <iframe title="Mapa da SmartPlanet na Av. Josué Di Bernardi, 34, São José, SC" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.openstreetmap.org/export/embed.html?bbox=-48.6125%2C-27.6035%2C-48.5985%2C-27.5942&amp;layer=mapnik&amp;marker=-27.598923%2C-48.605613"></iframe>
  </div>
</section>

<section class="two tight" data-reveal="fade">
  <article>
    <h2>Como chegar no endereço</h2>
    <p>No mapa, busque <?= e(SITE_STREET) ?>, São José, SC. Assim a rota não cai em Campinas, no estado de São Paulo. A retirada é só neste ponto.</p>
  </article>
  <article>
    <h2>Se você não puder vir</h2>
    <p>Chame no WhatsApp e informe o bairro e a cidade. A loja confirma a entrega, o prazo e a taxa. <a href="/entrega">Ver como combinar a entrega</a>.</p>
  </article>
</section>
