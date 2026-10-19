<header class="page-hero" data-reveal="fade">
  <p class="kicker">Contato</p>
  <h1>WhatsApp da loja de acessórios em São José</h1>
  <p class="lead">O formulário junta nome, modelo, acessório e bairro. O botão abre o WhatsApp <?= e(SITE_PHONE_HUMAN) ?> com esse texto, para a loja responder. Campinas é o bairro da SmartPlanet, em São José/SC.</p>
</header>

<section class="contact">
  <form class="zap-form" data-zap data-reveal action="<?= e(wa_link()) ?>">
    <div class="field">
      <label for="nome">Nome</label>
      <input id="nome" name="nome" type="text" maxlength="80" autocomplete="name" placeholder="Como podemos te chamar">
    </div>
    <div class="field">
      <label for="modelo">Marca e modelo do celular</label>
      <input id="modelo" name="modelo" type="text" maxlength="80" required placeholder="Ex.: Motorola Moto G84">
    </div>
    <div class="field">
      <label for="acessorio">Acessório</label>
      <input id="acessorio" name="acessorio" type="text" maxlength="120" required placeholder="Ex.: película de tela">
    </div>
    <div class="field">
      <label for="bairro">Bairro e cidade</label>
      <input id="bairro" name="bairro" type="text" maxlength="120" placeholder="Ex.: Campinas, São José">
    </div>
    <button class="btn gold" type="submit">Chamar no WhatsApp</button>
  </form>
  <ul class="channels" data-reveal="fade">
    <li>
      <h2>WhatsApp</h2>
      <p>Número da loja para consultar acessório, retirada ou entrega.</p>
      <a href="<?= e(wa_link('Oi, vim pelo site da SmartPlanet.')) ?>" rel="noopener noreferrer" title="Chamar no WhatsApp <?= e(SITE_PHONE_HUMAN) ?>"><?= e(SITE_PHONE_HUMAN) ?></a>
    </li>
    <li>
      <h2>Instagram</h2>
      <p>Perfil da loja. O pedido segue no WhatsApp.</p>
      <a href="<?= e(SITE_INSTAGRAM) ?>" rel="me noopener noreferrer" title="Instagram da SmartPlanet">@smartplanet_oficial</a>
    </li>
    <li>
      <h2>Facebook</h2>
      <p>Página Smart Planet. O pedido segue no WhatsApp.</p>
      <a href="<?= e(SITE_FACEBOOK) ?>" rel="me noopener noreferrer" title="Facebook da SmartPlanet">Smart Planet</a>
    </li>
    <li>
      <h2>Loja</h2>
      <p><?= e(SITE_STREET) ?>, <?= e(SITE_PLACE) ?>, CEP <?= e(SITE_CEP) ?>.</p>
      <a href="/onde-estamos">Ver como chegar</a>
    </li>
  </ul>
</section>
