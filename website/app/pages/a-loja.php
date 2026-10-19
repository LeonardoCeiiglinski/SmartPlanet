<header class="page-hero" data-reveal="fade">
  <p class="kicker">A loja</p>
  <h1>Loja de celular em Campinas, São José SC</h1>
  <p class="lead">A SmartPlanet vende acessórios para celular na <?= e(SITE_STREET) ?>, CEP <?= e(SITE_CEP) ?>. O atendimento é no balcão e também pelo WhatsApp.</p>
</header>

<section class="store">
  <figure class="store-photo" data-reveal="photo">
    <img src="/assets/img/loja.jpg" width="330" height="437" alt="Balcão da loja SmartPlanet com capinhas e cabos, em Campinas, São José/SC" loading="lazy">
    <figcaption>Interior da loja SmartPlanet, no bairro Campinas.</figcaption>
  </figure>
  <div class="store-copy" data-reveal>
    <h2>Como encontrar a SmartPlanet</h2>
    <p>O ponto de referência é o mercado Direto do Campo, no número 34 da mesma avenida. Campinas é o bairro, em São José/SC.</p>
    <dl class="nap">
      <div><dt>Endereço</dt><dd><?= e(SITE_STREET) ?><br><?= e(SITE_PLACE) ?><br><?= e(SITE_CEP) ?></dd></div>
      <div><dt>Referência</dt><dd><?= e(SITE_REF) ?></dd></div>
      <div><dt>WhatsApp</dt><dd><a href="<?= e(wa_link('Oi, vim pelo site da SmartPlanet. Quero ir até a loja.')) ?>" rel="noopener noreferrer" title="Chamar no WhatsApp"><?= e(SITE_PHONE_HUMAN) ?></a></dd></div>
      <div><dt>Horário</dt><dd>O horário de funcionamento não está neste site. Consulte pelo WhatsApp antes de ir.</dd></div>
    </dl>
    <p><a class="btn gold" href="/onde-estamos">Ver como chegar</a></p>
  </div>
</section>

<section class="prose visit-note" data-reveal="fade">
  <h2>Atendimento no balcão e no WhatsApp</h2>
  <p>No balcão, leve o celular. A loja confere se a capinha, a película, o cabo ou o suporte servem no seu aparelho.</p>
  <p>Na mensagem, escreva a marca, o modelo e o acessório. Se não souber o nome do celular, mande uma foto da parte de trás. O preço e a disponibilidade são confirmados nesse atendimento.</p>
  <p>A retirada é neste endereço. Quando você não puder vir, a entrega é combinada pelo WhatsApp. <a href="/entrega">Ver retirada e entrega</a>.</p>
</section>
