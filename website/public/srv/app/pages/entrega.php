<header class="page-hero" data-reveal>
  <p class="kicker">Entrega</p>
  <h1>Da loja em Campinas para a Grande Florianópolis.</h1>
  <p class="lead">A SmartPlanet está em São José, no bairro Campinas. A entrega atende a região em volta: São José, Florianópolis, Palhoça e Biguaçu. O horário sai na conversa, porque um bairro na ilha e um bairro aqui do lado não são o mesmo caminho.</p>
</header>

<section class="steps">
  <article data-reveal>
    <span>1</span>
    <h2>O aparelho</h2>
    <p>Modelo e a linha: capinha, película, cabo, fone ou suporte. Foto da traseira evita peça de outro tamanho. Sem isso, a gente pergunta antes de separar.</p>
  </article>
  <article data-reveal>
    <span>2</span>
    <h2>O bairro</h2>
    <p>Com o bairro dá para dizer se sai no mesmo período ou no seguinte. “Florianópolis” sozinho é ilha inteira. “São José” também. O nome do bairro encurta a resposta.</p>
  </article>
  <article data-reveal>
    <span>3</span>
    <h2>A mesma peça do balcão</h2>
    <p>Não trocamos o que foi escolhido por um parecido de catálogo. Se a cor ou o modelo acabou entre a mensagem e a saída, a gente avisa e espera o ok.</p>
  </article>
</section>

<section class="lines" aria-labelledby="cidades">
  <div class="section-head" data-reveal>
    <p class="kicker">Para onde sai</p>
    <h2 id="cidades">Quatro cidades, um combinado só.</h2>
  </div>
  <ol>
    <li data-reveal>
      <span>SJ</span>
      <h3>São José</h3>
      <p>A loja já está aqui, em Campinas. Retirada no <?= e(SITE_STREET) ?> costuma ser o caminho mais curto. Entrega no município entra no mesmo WhatsApp.</p>
    </li>
    <li data-reveal>
      <span>FLN</span>
      <h3>Florianópolis</h3>
      <p>Ilha e os bairros do continente. Diz qual dos dois. A ponte muda o tempo, e a gente prefere falar isso antes de prometer “já sai”.</p>
    </li>
    <li data-reveal>
      <span>PAL</span>
      <h3>Palhoça</h3>
      <p>Segue para o sul da Grande Florianópolis. Bairro e um ponto de referência deixam a entrega objetiva.</p>
    </li>
    <li data-reveal>
      <span>BIG</span>
      <h3>Biguaçu</h3>
      <p>Segue para o norte. O mesmo combinado: o que você quer, o bairro, e se alguém recebe no endereço.</p>
    </li>
  </ol>
</section>

<section class="note" data-reveal>
  <h2>Prefere retirar em Campinas?</h2>
  <p>O endereço é <?= e(SITE_STREET) ?>, <?= e(SITE_PLACE) ?>, CEP <?= e(SITE_CEP) ?>. A referência é o <?= e(SITE_REF) ?>. Quem já está no mercado não precisa de entrega para uma capinha.</p>
  <p><a class="btn gold" href="<?= e(wa_link('Oi, quero entrega da SmartPlanet. Meu bairro é ')) ?>">Combinar bairro e peça</a> <a class="text-link" href="/onde-estamos">Ir de carro até a loja</a></p>
</section>
