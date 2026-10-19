<?php $faq = site_delivery_faq(); ?>
<header class="page-hero" data-reveal="fade">
  <p class="kicker">Entrega</p>
  <h1>Retirada na loja em Campinas, São José</h1>
  <p class="lead">Você pode buscar o acessório na SmartPlanet. Se não puder vir, a entrega é combinada pelo WhatsApp, com o bairro e a cidade.</p>
</header>

<section class="two" data-reveal>
  <article>
    <h2>Retirar o pedido na loja</h2>
    <p><?= e(SITE_STREET) ?>, <?= e(SITE_PLACE) ?>, CEP <?= e(SITE_CEP) ?>. A referência é o <?= e(SITE_REF) ?>, no mesmo número. Campinas é um bairro de São José/SC.</p>
    <a class="text-link" href="/onde-estamos">Ver como chegar</a>
  </article>
  <article>
    <h2>Entrega combinada pelo WhatsApp</h2>
    <p>Informe seu bairro e sua cidade para a loja confirmar se a entrega sai para esse endereço. O prazo e a taxa, se houver, também são respondidos nessa conversa.</p>
    <a class="btn gold" href="<?= e(wa_link('Oi, vim pelo site da SmartPlanet. Quero consultar a entrega. Meu bairro e minha cidade são ')) ?>" rel="noopener noreferrer" title="Consultar entrega no WhatsApp">Consultar entrega pelo WhatsApp</a>
  </article>
</section>

<section class="answers" data-reveal="fade" aria-labelledby="perguntas">
  <h2 id="perguntas">Perguntas sobre a entrega</h2>
  <dl>
    <div>
      <dt><?= e($faq[0]['q']) ?></dt>
      <dd><?= e($faq[0]['a']) ?></dd>
    </div>
    <div>
      <dt><?= e($faq[1]['q']) ?></dt>
      <dd><?= e($faq[1]['a']) ?></dd>
    </div>
    <div>
      <dt><?= e($faq[2]['q']) ?></dt>
      <dd><?= e($faq[2]['a']) ?></dd>
    </div>
  </dl>
</section>

<section class="note" data-reveal>
  <h2>O que enviar na mensagem</h2>
  <p>Mande a marca e o modelo do celular, o nome do acessório e o bairro com a cidade. Se outra pessoa for receber, diga o nome dela.</p>
  <p><a class="btn gold" href="/contato">Escrever a mensagem</a></p>
</section>
