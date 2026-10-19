<header class="page-hero">
  <p class="kicker">Contato</p>
  <h1>Fale com a loja pelo WhatsApp</h1>
  <p class="lead">Pedidos e consultas vão para o <?= e(SITE_PHONE_HUMAN) ?>. O formulário monta a mensagem e abre o WhatsApp no seu celular. Instagram e Facebook são os perfis da loja. A visita é em Campinas.</p>
</header>

<section class="contact">
  <form class="zap-form" data-zap action="<?= e(wa_link()) ?>">
    <div class="field">
      <label for="nome">Nome</label>
      <input id="nome" name="nome" type="text" maxlength="80" autocomplete="name">
    </div>
    <div class="field">
      <label for="modelo">Marca e modelo do celular</label>
      <input id="modelo" name="modelo" type="text" maxlength="80" required placeholder="Ex.: o modelo que aparece na configuração">
    </div>
    <div class="field">
      <label for="acessorio">Acessório</label>
      <input id="acessorio" name="acessorio" type="text" maxlength="120" required placeholder="Ex.: capinha transparente, película de tela, cabo, fone, suporte de carro">
    </div>
    <div class="field">
      <label for="bairro">Bairro e como prefere receber</label>
      <input id="bairro" name="bairro" type="text" maxlength="120" placeholder="Ex.: retirada em Campinas, ou entrega no Kobrasol">
    </div>
    <button class="btn gold" type="submit">Abrir conversa no WhatsApp</button>
  </form>
  <ul class="channels">
    <li>
      <h2>WhatsApp</h2>
      <p>Canal do pedido: modelo, acessório e bairro.</p>
      <a href="<?= e(wa_link('Oi, vim pelo site da SmartPlanet.')) ?>"><?= e(SITE_PHONE_HUMAN) ?></a>
    </li>
    <li>
      <h2>Instagram</h2>
      <p>Novidade da loja. A separação da peça continua no WhatsApp.</p>
      <a href="<?= e(SITE_INSTAGRAM) ?>" rel="noopener">@smartplanet_oficial</a>
    </li>
    <li>
      <h2>Facebook</h2>
      <p>Página da SmartPlanet.</p>
      <a href="<?= e(SITE_FACEBOOK) ?>" rel="noopener">Smart Planet</a>
    </li>
    <li>
      <h2>Loja</h2>
      <p><?= e(SITE_STREET) ?>, <?= e(SITE_PLACE) ?>.</p>
      <a href="/onde-estamos">Ver rota até a loja</a>
    </li>
  </ul>
</section>
