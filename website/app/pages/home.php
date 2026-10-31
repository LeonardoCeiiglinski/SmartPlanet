<section class="hero">
  <div class="hero-copy">
    <p class="kicker enter">SmartPlanet · <?= e(SITE_TAG) ?></p>
    <h1 class="enter enter-2">Acessórios para celular e eletrônicos em São José/SC</h1>
    <p class="lead enter enter-3">A SmartPlanet é loja de capinha, película, carregador, cabo, fone e linha gamer. Fica na <?= e(SITE_STREET) ?>, no bairro Campinas, em São José/SC. Você retira no balcão ou consulta a entrega no WhatsApp.</p>
    <div class="actions enter enter-4">
      <a class="btn gold" href="<?= e(wa_link('Oi, vim pelo site da SmartPlanet. Quero consultar um acessório. Meu celular é ')) ?>" rel="noopener noreferrer" title="Chamar no WhatsApp">Chamar no WhatsApp</a>
      <a class="btn ghost" href="/onde-estamos">Ver como chegar</a>
    </div>
    <dl class="facts enter enter-5">
      <div><dt>Onde</dt><dd>Campinas, São José/SC</dd></div>
      <div><dt>WhatsApp</dt><dd><?= e(SITE_PHONE_HUMAN) ?></dd></div>
      <div><dt>Retirada</dt><dd>Na loja ou entrega combinada no WhatsApp</dd></div>
      <div><dt>Horário</dt><dd>Confirme no WhatsApp</dd></div>
    </dl>
  </div>
  <figure class="hero-photo enter-photo">
    <img src="/assets/img/loja-hero.jpg?v=4" width="1024" height="576" alt="Interior da SmartPlanet, com o balcão e a parede de capinhas e películas" fetchpriority="high">
    <figcaption>Interior da loja, com o balcão, a parede de capinhas e películas e o painel gamer.</figcaption>
  </figure>
</section>

<nav class="jumps" data-reveal="fade" aria-label="Páginas da loja">
  <a href="/acessorios">Capinhas, películas e cabos</a>
  <a href="/linha-gamer">Linha gamer</a>
  <a href="/entrega">Retirada na loja ou entrega</a>
  <a href="/onde-estamos">Endereço e mapa</a>
  <a href="/contato">Chamar no WhatsApp</a>
</nav>

<section class="shelf" aria-labelledby="encontra">
  <div class="section-head" data-reveal="fade">
    <h2 id="encontra">O que tem na loja</h2>
    <p class="lead">Cada peça depende do modelo do celular. Informe a marca e o modelo, ou traga o aparelho até o balcão.</p>
  </div>
  <ul class="shelf-list">
    <li data-reveal>
      <h3><a href="/acessorios#capinhas">Capinhas e capas</a></h3>
      <p>A capinha precisa deixar os botões e a câmera livres. Diga a marca e o modelo para o encaixe ficar certo.</p>
    </li>
    <li data-reveal>
      <h3><a href="/peliculas">Películas</a></h3>
      <p>Tem película de tela e de lente. Informe o modelo e qual das duas você precisa.</p>
    </li>
    <li data-reveal>
      <h3><a href="/carregadores">Cabos e carregadores</a></h3>
      <p>O cabo e o carregador seguem a entrada do seu celular. Diga se o uso é em casa, no carro ou na tomada.</p>
    </li>
    <li data-reveal>
      <h3><a href="/fones">Fones</a></h3>
      <p>Fones para ligação ou música. Diga se prefere com fio ou sem fio.</p>
    </li>
    <li data-reveal>
      <h3><a href="/acessorios#suportes">Suportes</a></h3>
      <p>Suporte de carro, de moto ou de mesa. Diga onde você vai usar o celular.</p>
    </li>
    <li data-reveal>
      <h3><a href="/linha-gamer">Linha gamer</a></h3>
      <p>O painel GAMER da loja tem headset e controle. Mouse USB também entra na consulta. O modelo do dia é confirmado no balcão.</p>
    </li>
  </ul>
</section>

<section class="split">
  <div data-reveal>
    <h2>Como consultar um acessório</h2>
    <p>Diga a marca e o modelo do celular. Depois, diga qual acessório você procura. Por último, combine a retirada na loja ou pergunte pela entrega.</p>
    <p>O endereço é <?= e(SITE_STREET) ?>, CEP <?= e(SITE_CEP) ?>. A referência é o Direto do Campo, no mesmo número, onde também fica o Grauvi Empório Natural. Campinas é o bairro da loja, em São José/SC.</p>
    <p>Pendrive, adaptador, teclado e fone bluetooth não têm estoque publicado nesta página. Se você procura esses eletrônicos, pergunte no WhatsApp se estão no balcão.</p>
    <p><a class="text-link" href="/a-loja">Como é o atendimento na loja</a></p>
  </div>
  <aside class="panel" data-reveal="fade">
    <h3>Exemplo de mensagem</h3>
    <p>Escreva o modelo, o acessório e se a retirada é na loja. Se for entrega, informe o bairro e a cidade para a loja confirmar.</p>
    <a class="btn gold" href="/contato">Preencher mensagem no WhatsApp</a>
  </aside>
</section>

<section class="band" data-reveal="fade">
  <div>
    <h2>Endereço da loja e WhatsApp</h2>
    <p><?= e(SITE_STREET) ?>, <?= e(SITE_PLACE) ?>, CEP <?= e(SITE_CEP) ?>. Referência: <?= e(SITE_REF) ?>. WhatsApp <?= e(SITE_PHONE_HUMAN) ?>.</p>
  </div>
  <a class="btn gold" href="/entrega">Ver retirada e entrega</a>
</section>
