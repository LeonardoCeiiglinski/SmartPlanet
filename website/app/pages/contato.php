<header class="page-hero" data-reveal>
  <p class="kicker">Contato</p>
  <h1>Cada canal tem um uso. O pedido é no WhatsApp.</h1>
  <p class="lead">O <?= e(SITE_PHONE_HUMAN) ?> separa capinha, película, cabo, fone, suporte e a entrega. O Instagram mostra o dia da loja. O Facebook é a página da SmartPlanet. O formulário abaixo só abre essa conversa no seu celular. Nada fica gravado neste site.</p>
</header>

<section class="contact">
  <form class="zap-form" data-zap action="<?= e(wa_link()) ?>">
    <div class="field">
      <label for="nome">Seu nome</label>
      <input id="nome" name="nome" type="text" maxlength="80" autocomplete="name" placeholder="Como a gente te chama">
    </div>
    <div class="field">
      <label for="mensagem">O que você precisa</label>
      <textarea id="mensagem" name="mensagem" rows="5" maxlength="500" required placeholder="Ex.: película de tela e de lente, retirada em Campinas. O aparelho é o que está na foto que eu mando em seguida."></textarea>
    </div>
    <button class="btn gold" type="submit">Abrir no WhatsApp</button>
    <p class="fine">A mensagem abre no seu WhatsApp, para o <?= e(SITE_PHONE_HUMAN) ?>. Este site não guarda nome nem texto.</p>
  </form>
  <ul class="channels" data-reveal>
    <li>
      <h2>WhatsApp</h2>
      <p>Modelo do celular, o que você quer e se é retirada ou entrega. É o canal do pedido.</p>
      <a href="<?= e(wa_link('Oi, vim pelo site da SmartPlanet.')) ?>"><?= e(SITE_PHONE_HUMAN) ?></a>
    </li>
    <li>
      <h2>Instagram</h2>
      <p>O movimento da boutique, não a tabela de estoque. Para separar peça, volta para o WhatsApp.</p>
      <a href="<?= e(SITE_INSTAGRAM) ?>" rel="noopener">@smartplanet_oficial</a>
    </li>
    <li>
      <h2>Facebook</h2>
      <p>A página da loja, com o mesmo nome. Endereço e novidade de porta, não checkout.</p>
      <a href="<?= e(SITE_FACEBOOK) ?>" rel="noopener">Smart Planet</a>
    </li>
    <li>
      <h2>Balcão</h2>
      <p><?= e(SITE_STREET) ?>, referência <?= e(SITE_REF) ?>. CEP <?= e(SITE_CEP) ?>.</p>
      <a href="/onde-estamos">Como chegar</a>
    </li>
  </ul>
</section>

<section class="note" data-reveal>
  <h2>Uma mensagem que a loja consegue responder</h2>
  <p>“Oi, capinha transparente para o modelo tal, retirada hoje em Campinas.” Ou: “Oi, suporte de moto, entrega no bairro tal, em Palhoça.” Com isso a resposta vem objetiva. “Quanto custa uma capinha?” sem o aparelho vira outra pergunta: qual celular.</p>
</section>
