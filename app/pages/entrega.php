<header class="page-hero">
  <p class="kicker">Entrega</p>
  <h1>Retirada em Campinas ou entrega combinada</h1>
  <p class="lead">A SmartPlanet está no bairro Campinas, em São José/SC. Dá para buscar a peça na loja. A entrega existe para quem não vem até aqui. Bairro, prazo e eventual taxa são confirmados no WhatsApp, antes de separar.</p>
</header>

<section class="two">
  <article>
    <h2>Retirar na loja</h2>
    <p><?= e(SITE_STREET) ?>, <?= e(SITE_PLACE) ?>, CEP <?= e(SITE_CEP) ?>. Referência: <?= e(SITE_REF) ?>. Quem já está em Campinas, ou vem do Kobrasol e de outros bairros de São José, costuma levar na hora.</p>
    <a class="text-link" href="/onde-estamos">Ver rota até a loja</a>
  </article>
  <article>
    <h2>Receber em outro endereço</h2>
    <p>A loja entrega na Grande Florianópolis. São José é a base. Florianópolis, Palhoça e Biguaçu entram na consulta, assim como outros bairros da região. O nome do município sozinho não fecha o pedido: precisa do bairro.</p>
    <a class="btn gold" href="<?= e(wa_link('Oi, quero consultar a entrega da SmartPlanet. Meu bairro é ')) ?>">Consultar entrega no meu bairro</a>
  </article>
</section>

<section class="answers" aria-labelledby="perguntas">
  <h2 id="perguntas">O que a loja confirma na conversa</h2>
  <dl>
    <div>
      <dt>Vocês entregam no meu bairro?</dt>
      <dd>Depende do endereço. Informe o bairro e o município. A resposta diz se a entrega sai para lá.</dd>
    </div>
    <div>
      <dt>Quanto custa?</dt>
      <dd>A taxa, quando existe, não é um valor fixo neste site. Ela é informada junto com a peça, no WhatsApp <?= e(SITE_PHONE_HUMAN) ?>.</dd>
    </div>
    <div>
      <dt>Quando recebo?</dt>
      <dd>O prazo sai na mesma confirmação. Muda conforme o bairro e o horário em que você chama. A loja não publica um prazo único para a região inteira.</dd>
    </div>
  </dl>
</section>

<section class="note">
  <h2>O que mandar junto</h2>
  <p>Marca e modelo do celular, o acessório e se alguém recebe no endereço. Se a cor ou o modelo acabar entre a mensagem e a saída, a loja avisa antes de trocar a peça.</p>
  <p><a class="btn gold" href="/contato">Montar a mensagem</a></p>
</section>
