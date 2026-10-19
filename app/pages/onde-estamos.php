<header class="page-hero">
  <p class="kicker">Onde estamos</p>
  <h1>Loja de acessórios para celular em São José/SC</h1>
  <p class="lead">O endereço da SmartPlanet é em Campinas, bairro de São José. Não é o município de Campinas, em São Paulo. O CEP <?= e(SITE_CEP) ?> e o nome completo da avenida evitam essa confusão no mapa.</p>
</header>

<section class="map-block">
  <div>
    <h2><?= e(SITE_STREET) ?></h2>
    <address class="nap-block">
      <strong>SmartPlanet</strong><br>
      <?= e(SITE_STREET) ?><br>
      Bairro Campinas, São José/SC<br>
      CEP <?= e(SITE_CEP) ?><br>
      Referência: <?= e(SITE_REF) ?><br>
      <a href="<?= e(wa_link('Oi, estou a caminho da SmartPlanet.')) ?>">WhatsApp <?= e(SITE_PHONE_HUMAN) ?></a>
    </address>
    <p>O <?= e(SITE_REF) ?> do número 34 é a referência que o bairro já usa. Há outras unidades desse mercado, na ilha e em Coqueiros. A SmartPlanet é a da <?= e(SITE_STREET) ?>.</p>
    <p>O horário de funcionamento não está publicado neste site. Confirme no WhatsApp antes de sair.</p>
    <p class="actions">
      <a class="btn gold" href="<?= e(maps_link()) ?>" rel="noopener">Ver rota até a loja</a>
      <a class="text-link" href="/contato">Avisar que estou indo</a>
    </p>
  </div>
  <div class="map-frame">
    <iframe title="Mapa da SmartPlanet na Av. Josué Di Bernardi, 34, Campinas, São José/SC" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.openstreetmap.org/export/embed.html?bbox=-48.6125%2C-27.6035%2C-48.5985%2C-27.5942&amp;layer=mapnik&amp;marker=-27.598923%2C-48.605613"></iframe>
  </div>
</section>

<section class="two tight">
  <article>
    <h2>Este é o endereço da loja</h2>
    <p>A visita e a retirada acontecem só neste ponto, em Campinas. Quem vem do Kobrasol, do Centro de São José ou de bairros vizinhos usa o mesmo destino: <?= e(SITE_STREET) ?>.</p>
  </article>
  <article>
    <h2>Entrega é outra conversa</h2>
    <p>Florianópolis, Palhoça, Biguaçu e outros bairros da Grande Florianópolis não são filiais. São lugares em que a entrega pode ser consultada. <a href="/entrega">Veja como a loja confirma bairro, prazo e taxa.</a></p>
  </article>
</section>
