<section class="hero">
  <div class="orb orb-a" aria-hidden="true"></div>
  <div class="orb orb-b" aria-hidden="true"></div>
  <div class="hero-copy">
    <p class="kicker">Campinas · São José/SC · <?= e(SITE_REF) ?></p>
    <h1>Capinha que fecha <em>direito</em>, no balcão.</h1>
    <p class="lead">A SmartPlanet fica na <?= e(SITE_STREET) ?>, no ponto do Direto do Campo. É loja de eletrônico e de acessório de celular: capinha, película, cabo, fone e suporte. Você encosta a peça no aparelho antes de levar. Quem não passa aqui combina a entrega pelo WhatsApp, na Grande Florianópolis.</p>
    <div class="actions">
      <a class="btn gold" href="<?= e(wa_link('Oi, quero ver capinha e acessório na SmartPlanet.')) ?>">Chamar no WhatsApp</a>
      <a class="btn ghost" href="/onde-estamos">Como chegar</a>
    </div>
    <dl class="facts">
      <div><dt>Endereço</dt><dd><?= e(SITE_STREET) ?></dd></div>
      <div><dt>Referência</dt><dd><?= e(SITE_REF) ?>, Campinas</dd></div>
      <div><dt>Entrega</dt><dd>São José, Florianópolis, Palhoça e Biguaçu</dd></div>
    </dl>
  </div>
  <div class="hero-mark" aria-hidden="true">
    <span class="ring"></span>
    <span class="ring ring-2"></span>
    <span class="ring ring-3"></span>
  </div>
</section>

<div class="marquee" aria-hidden="true">
  <div class="marquee-track">
    <?php for ($i = 0; $i < 2; $i++): ?>
      <span>Capinha</span><span>Película</span><span>Cabo</span><span>Carregador</span><span>Fone</span><span>Suporte</span><span>Áudio</span><span>Boutique do celular</span>
    <?php endfor ?>
  </div>
</div>

<section class="visit" aria-labelledby="visita">
  <div class="section-head" data-reveal>
    <p class="kicker">No balcão</p>
    <h2 id="visita">Quatro passos, sem catálogo no meio.</h2>
    <p class="lead">O site explica a loja. A escolha da peça acontece no aparelho, aqui ou na mensagem. Preço e o que chegou na semana não ficam presos numa grade que envelhece.</p>
  </div>
  <ol class="steps">
    <li data-reveal>
      <span>1</span>
      <h3>Chega com o celular</h3>
      <p>Se estiver no Direto do Campo, é atravessar até o número 34. Se estiver em casa, manda o modelo. Foto da traseira resolve quando o nome do aparelho não vem de cabeça.</p>
    </li>
    <li data-reveal>
      <span>2</span>
      <h3>A peça encosta no aparelho</h3>
      <p>Capinha no botão, película na tela e na lente, cabo na entrada que você usa. O que fica torto ou bambo não segue para a sacola.</p>
    </li>
    <li data-reveal>
      <span>3</span>
      <h3>Você decide ali</h3>
      <p>Transparente ou fechada, fina ou com apoio, cabo curto de carro ou longo de cabeceira. A diferença aparece na mão, não numa foto genérica.</p>
    </li>
    <li data-reveal>
      <span>4</span>
      <h3>Leva ou pede entrega</h3>
      <p>Quem está em Campinas sai com a peça. Quem está em São José, Florianópolis, Palhoça ou Biguaçu combina o bairro no WhatsApp <?= e(SITE_PHONE_HUMAN) ?>.</p>
    </li>
  </ol>
</section>

<section class="split">
  <div data-reveal>
    <p class="kicker">Por que este ponto</p>
    <h2>Campinas já tem o movimento. A loja entra nele.</h2>
    <p>O Direto do Campo de São José fica na mesma avenida, número 34. Quem já veio ao mercado não precisa atravessar a ponte atrás de uma capinha, nem descer até o continente em Coqueiros por um cabo.</p>
    <p>A SmartPlanet é a boutique do celular desse endereço. Eletrônico e acessório, atendimento de balcão. A parede muda conforme o que entrou. Por isso este site não finge uma vitrine fixa: modelo e valor do dia estão na loja e na conversa.</p>
    <a class="text-link" href="/a-loja">Como a loja atende</a>
  </div>
  <aside class="panel" data-reveal>
    <h3>O que dizer na primeira mensagem</h3>
    <ul>
      <li>O modelo do celular, do jeito que está na caixa ou na configuração.</li>
      <li>Se a busca é capinha, película, cabo, fone ou suporte.</li>
      <li>Se vai retirar em Campinas ou quer entrega, e em qual bairro.</li>
    </ul>
    <a class="btn gold" href="<?= e(wa_link('Oi, meu celular é ')) ?>">Montar essa mensagem</a>
  </aside>
</section>

<section class="lines" aria-labelledby="linhas">
  <div class="section-head" data-reveal>
    <p class="kicker">O que a casa trabalha</p>
    <h2 id="linhas">Acessório de uso, não de mostruário.</h2>
  </div>
  <ol>
    <li data-reveal>
      <span>01</span>
      <h3>Capinha e película</h3>
      <p>Protege e ainda tem que fechar no botão e liberar a câmera. Película de tela e de lente se vê com o aparelho ligado, no balcão.</p>
    </li>
    <li data-reveal>
      <span>02</span>
      <h3>Cabo, fonte e fone</h3>
      <p>A ponta certa para o que você já tem em casa e no carro. Cabo que carrega dos dois lados, sem o teatro de “só funciona virado”.</p>
    </li>
    <li data-reveal>
      <span>03</span>
      <h3>Suporte de carro, moto e mesa</h3>
      <p>O que segura o celular na lombada, no guidão e na cabeceira. Se solta no teste, não sai da loja como se estivesse resolvido.</p>
    </li>
  </ol>
  <p class="section-more"><a class="text-link" href="/acessorios">Ver o que cada linha resolve</a></p>
</section>

<section class="band">
  <div data-reveal>
    <p class="kicker">Entrega</p>
    <h2>Sai de Campinas para a Grande Florianópolis.</h2>
    <p>São José, Florianópolis, Palhoça e Biguaçu. O prazo não é um número de propaganda: depende do bairro e da hora em que você chama. A peça é a mesma que está no balcão.</p>
  </div>
  <a class="btn gold" href="/entrega">Como a entrega é combinada</a>
</section>

<section class="faq" aria-labelledby="duvidas">
  <div class="section-head" data-reveal>
    <p class="kicker">Antes de sair de casa</p>
    <h2 id="duvidas">O que este site responde, e o que fica para a loja.</h2>
  </div>
  <div class="faq-list">
    <details data-reveal>
      <summary>Vocês listam produto e preço aqui?</summary>
      <p>Não. A casa é de eletrônico e acessório, e o que tem na parede muda. Listar modelo e valor nesta página criaria uma vitrine errada. Isso se vê no balcão ou no WhatsApp, no dia.</p>
    </details>
    <details data-reveal>
      <summary>Preciso saber o nome certinho do celular?</summary>
      <p>Ajuda. Se não souber, uma foto da traseira e da entrada de carga já evita capinha de outro tamanho. No balcão, o próprio aparelho faz esse serviço.</p>
    </details>
    <details data-reveal>
      <summary>Dá para retirar sem pedir entrega?</summary>
      <p>Dá. O ponto é a <?= e(SITE_STREET) ?>, <?= e(SITE_PLACE) ?>, CEP <?= e(SITE_CEP) ?>, referência <?= e(SITE_REF) ?>. <a href="/onde-estamos">O mapa está na página Onde estamos</a>.</p>
    </details>
    <details data-reveal>
      <summary>Instagram substitui o estoque?</summary>
      <p>Não. O <a href="<?= e(SITE_INSTAGRAM) ?>" rel="noopener">@smartplanet_oficial</a> mostra o movimento da loja. Pedido, separação e entrega continuam no WhatsApp <?= e(SITE_PHONE_HUMAN) ?>.</p>
    </details>
  </div>
</section>
