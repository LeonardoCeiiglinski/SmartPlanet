<section class="hero">
  <div class="hero-copy">
    <p class="kicker">SmartPlanet · <?= e(SITE_TAG) ?></p>
    <h1>Acessórios para celular em São José, SC</h1>
    <p class="lead">Loja física no bairro Campinas, na <?= e(SITE_STREET) ?>, ponto do <?= e(SITE_REF) ?>. No balcão tem capinha, película, cabo, carregador, fone e suporte. Você retira aqui ou consulta a entrega pelo WhatsApp.</p>
    <div class="actions">
      <a class="btn gold" href="<?= e(wa_link('Oi, quero consultar um acessório na SmartPlanet. Meu celular é ')) ?>">Consultar disponibilidade</a>
      <a class="btn ghost" href="/onde-estamos">Ver rota até a loja</a>
    </div>
    <dl class="facts">
      <div><dt>Onde</dt><dd>Campinas, São José/SC</dd></div>
      <div><dt>WhatsApp</dt><dd><?= e(SITE_PHONE_HUMAN) ?></dd></div>
      <div><dt>Como leva</dt><dd>Retirada na loja ou entrega a combinar</dd></div>
    </dl>
  </div>
  <figure class="hero-photo">
    <img src="/assets/img/loja.jpg" width="330" height="437" alt="Interior da SmartPlanet em Campinas, São José, com acessórios para celular expostos na parede e atendimento no balcão" fetchpriority="high">
    <figcaption>O balcão em Campinas. O que está na parede muda conforme a reposição.</figcaption>
  </figure>
</section>

<nav class="jumps" aria-label="Nesta loja">
  <a href="/acessorios">Capinhas, películas e cabos</a>
  <a href="/entrega">Retirada ou entrega</a>
  <a href="/onde-estamos">Endereço e mapa</a>
  <a href="/contato">Falar no WhatsApp</a>
</nav>

<section class="shelf" aria-labelledby="encontra">
  <div class="section-head">
    <h2 id="encontra">O que dá para resolver aqui</h2>
    <p class="lead">A escolha depende do aparelho. Manda a marca e o modelo, ou traz o celular até o balcão.</p>
  </div>
  <ul class="shelf-list">
    <li>
      <h3><a href="/acessorios#capinhas">Capinhas e capas</a></h3>
      <p>Para fechar no botão e deixar a câmera livre. Diz se quer transparente, mais fechada ou com apoio.</p>
    </li>
    <li>
      <h3><a href="/acessorios#peliculas">Películas</a></h3>
      <p>Tela, lente ou as duas. A peça certa é a do seu modelo, não um adesivo genérico.</p>
    </li>
    <li>
      <h3><a href="/acessorios#cabos">Cabos e carregadores</a></h3>
      <p>A ponta do seu celular, para cabeceira, carro ou tomada. Conta onde o cabo atual falha.</p>
    </li>
    <li>
      <h3><a href="/acessorios#fones">Fones</a></h3>
      <p>Para ligação, música ou os dois. O encaixe se confirma no aparelho ou na mensagem.</p>
    </li>
    <li>
      <h3><a href="/acessorios#suportes">Suportes</a></h3>
      <p>Carro, moto ou mesa. Diz o veículo ou o uso, porque um suporte só não serve para os três.</p>
    </li>
  </ul>
</section>

<section class="split">
  <div>
    <h2>Antes de sair de casa</h2>
    <p>Campinas aqui é o bairro de São José, na <?= e(SITE_STREET) ?>, CEP <?= e(SITE_CEP) ?>. Quem já vai ao <?= e(SITE_REF) ?> está no mesmo número 34.</p>
    <p>Se a consulta for pelo WhatsApp <?= e(SITE_PHONE_HUMAN) ?>, três dados encurtam a resposta: marca e modelo do celular, o acessório, e se a ideia é retirar em Campinas ou receber em outro bairro.</p>
    <p><a class="text-link" href="/a-loja">Como funciona o atendimento</a></p>
  </div>
  <aside class="panel">
    <h3>Uma mensagem objetiva</h3>
    <p>Exemplo: “Capinha transparente para o modelo tal, retirada em Campinas.” Ou: “Película de tela, entrega no Kobrasol.” O bairro serve para a loja confirmar se a entrega sai.</p>
    <a class="btn gold" href="/contato">Montar a mensagem</a>
  </aside>
</section>

<section class="band">
  <div>
    <h2>Retira em Campinas ou pergunta a entrega</h2>
    <p>A loja está em São José. Para outros bairros e para Florianópolis, Palhoça e Biguaçu, o prazo e a eventual taxa dependem do endereço e são confirmados no WhatsApp.</p>
  </div>
  <a class="btn gold" href="/entrega">Consultar entrega no meu bairro</a>
</section>
