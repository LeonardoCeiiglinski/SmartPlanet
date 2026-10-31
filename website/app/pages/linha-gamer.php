<?php $faq = site_faqs('/linha-gamer'); ?>
<header class="page-hero" data-reveal="fade">
  <p class="kicker">Linha gamer</p>
  <h1>Linha gamer em São José/SC</h1>
  <p class="lead">No interior da SmartPlanet, ao lado do balcão, há um painel escrito GAMER. Nele estão headset e controle. A loja fica na <?= e(SITE_STREET) ?>, bairro Campinas, em São José.</p>
</header>

<section class="prose visit-note" data-reveal>
  <h2>O que a foto da loja mostra</h2>
  <p>A parede de capinhas e películas ocupa o lado maior do salão. O painel GAMER fica na parede da esquerda, debaixo do letreiro SmartPlanet. O que se vê nesse painel são pacotes de headset e de controle. O modelo de cada um muda, então o site não crava marca nem compatibilidade com um console.</p>
  <p>Mouse USB também faz parte do que a loja cadastra, à parte do painel. Teclado, pendrive e adaptador não estão com estoque publicado aqui. Se a sua procura é um desses eletrônicos, pergunte no WhatsApp <?= e(SITE_PHONE_HUMAN) ?> se está no balcão no dia.</p>
  <p>Na mensagem, diga o que você quer usar: headset para jogo, controle, mouse, ou outro item. Se for controle, diga em qual aparelho ele precisa funcionar. A loja confirma a peça antes da retirada.</p>
  <p>A retirada é neste endereço. A referência para achar a galeria é o Direto do Campo, no número 34, onde também fica o Grauvi Empório Natural. A entrega, para quem não vem até Campinas, é combinada com bairro e cidade. Taxa e prazo saem nessa conversa, não nesta página. Horário: <?= e(SITE_HOURS) ?></p>
</section>

<section class="answers" data-reveal="fade" aria-labelledby="duvida-gamer">
  <h2 id="duvida-gamer">Pergunta frequente</h2>
  <dl>
    <div>
      <dt><?= e($faq[0]['q']) ?></dt>
      <dd><?= e($faq[0]['a']) ?></dd>
    </div>
  </dl>
</section>

<p class="section-more">
  <a class="btn gold" href="<?= e(wa_link('Oi, vim pelo site da SmartPlanet. Quero consultar a linha gamer. Procuro ')) ?>" rel="noopener noreferrer" title="Consultar linha gamer no WhatsApp">Consultar a linha gamer</a>
  <a class="text-link" href="/a-loja">Ver a foto do interior</a>
</p>
