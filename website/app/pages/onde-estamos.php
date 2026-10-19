<header class="page-hero" data-reveal>
  <p class="kicker">Onde estamos</p>
  <h1>Campinas, no ponto do Direto do Campo.</h1>
  <p class="lead"><?= e(SITE_STREET) ?>. <?= e(SITE_PLACE) ?>. CEP <?= e(SITE_CEP) ?>. O mercado do número 34 é a referência que o bairro já usa. A SmartPlanet está nesse endereço.</p>
</header>

<section class="map-block">
  <div class="prose" data-reveal>
    <h2>Não é outra unidade do mercado.</h2>
    <p>O Direto do Campo tem loja na ilha e no Mercadão de Coqueiros. A de São José, em Campinas, é a da Av. Josué Di Bernardi, 34. É essa. Se o mapa sugerir um “José” parecido em outro bairro, confere o nome completo da avenida e o CEP <?= e(SITE_CEP) ?>.</p>
    <p>Quem já entrou no mercado está no ponto certo. Quem vem de carro procura a avenida, não um shopping. Quem pede aplicativo coloca o endereço inteiro, com São José, para não parar em Florianópolis.</p>
    <address>
      <strong>SmartPlanet · <?= e(SITE_TAG) ?></strong><br>
      <?= e(SITE_STREET) ?><br>
      <?= e(SITE_PLACE) ?><br>
      <?= e(SITE_CEP) ?><br>
      <a href="<?= e(wa_link('Oi, estou a caminho da loja.')) ?>"><?= e(SITE_PHONE_HUMAN) ?></a>
    </address>
    <p><a class="btn gold" href="<?= e(maps_link()) ?>" rel="noopener">Abrir no mapa</a></p>
  </div>
  <div class="map-frame" data-reveal>
    <iframe title="Mapa da SmartPlanet na Av. Josué Di Bernardi, 34, Campinas, São José" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.openstreetmap.org/export/embed.html?bbox=-48.6125%2C-27.6035%2C-48.5985%2C-27.5942&amp;layer=mapnik&amp;marker=-27.598923%2C-48.605613"></iframe>
  </div>
</section>

<section class="lines" aria-labelledby="chegada">
  <div class="section-head" data-reveal>
    <p class="kicker">De onde você vem</p>
    <h2 id="chegada">Três chegadas que a gente ouve todo dia.</h2>
  </div>
  <ol>
    <li data-reveal>
      <span>01</span>
      <h3>Já estou no mercado</h3>
      <p>Procura a SmartPlanet no mesmo endereço do Direto do Campo de Campinas. Não precisa de um segundo destino.</p>
    </li>
    <li data-reveal>
      <span>02</span>
      <h3>Estou em outro bairro de São José</h3>
      <p>O destino é Campinas, Av. Josué Di Bernardi, 34. Se for retirar algo separado, avisa no WhatsApp para a peça estar no balcão.</p>
    </li>
    <li data-reveal>
      <span>03</span>
      <h3>Estou em Florianópolis</h3>
      <p>A loja é em São José, não na ilha. Dá para vir até aqui ou pedir entrega e informar o bairro. As duas coisas passam pelo <?= e(SITE_PHONE_HUMAN) ?>.</p>
    </li>
  </ol>
</section>
