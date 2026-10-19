<header class="page-hero">
  <p class="kicker">A loja</p>
  <h1>A SmartPlanet fica em Campinas, São José/SC</h1>
  <p class="lead">É loja de acessórios para celular, com balcão, no mesmo endereço do <?= e(SITE_REF) ?>: <?= e(SITE_STREET) ?>, CEP <?= e(SITE_CEP) ?>. Campinas é o bairro. O município é São José.</p>
</header>

<section class="store">
  <figure class="store-photo">
    <img src="/assets/img/loja.jpg" width="330" height="437" alt="Parede de acessórios para celular e atendimento no balcão da SmartPlanet, em Campinas, São José/SC" loading="lazy">
    <figcaption>Capinhas, cabos, fones e carregadores no ponto. A foto é desta loja, não de um mostruário genérico.</figcaption>
  </figure>
  <div class="store-copy">
    <h2>Como encontrar</h2>
    <p>Quem já entra no <?= e(SITE_REF) ?> de Campinas está no número 34 da avenida. O mercado tem outras unidades, na ilha e em Coqueiros. A SmartPlanet é a deste endereço, em São José.</p>
    <dl class="nap">
      <div><dt>Endereço</dt><dd><?= e(SITE_STREET) ?><br><?= e(SITE_PLACE) ?><br><?= e(SITE_CEP) ?></dd></div>
      <div><dt>Referência</dt><dd><?= e(SITE_REF) ?></dd></div>
      <div><dt>WhatsApp</dt><dd><a href="<?= e(wa_link('Oi, quero ir até a SmartPlanet.')) ?>"><?= e(SITE_PHONE_HUMAN) ?></a></dd></div>
      <div><dt>Horário</dt><dd>Ainda não está publicado neste site. Confirme no WhatsApp antes de ir.</dd></div>
    </dl>
    <p><a class="btn gold" href="/onde-estamos">Ver rota até a loja</a></p>
  </div>
</section>

<section class="prose visit-note">
  <h2>No balcão e antes de sair de casa</h2>
  <p>Presencial, o celular faz o teste: a capinha fecha, a película cobre o recorte certo, o cabo encaixa, o suporte segura. O que não passa nisso não vai para a sacola.</p>
  <p>De casa, a consulta é pelo WhatsApp. Informe a marca, o modelo e o acessório. Se não souber o nome do aparelho, uma foto da traseira resolve. A resposta diz o que tem na loja naquele momento. Preço e estoque não ficam listados aqui, porque a parede muda.</p>
  <p>Quem mora no Kobrasol, no Centro ou em outro bairro de São José vem até Campinas para retirar, ou pergunta se a entrega chega no endereço. <a href="/entrega">A página de entrega explica a diferença.</a></p>
</section>
