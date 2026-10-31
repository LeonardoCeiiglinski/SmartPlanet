<?php $faq = site_faqs('/peliculas'); ?>
<header class="page-hero" data-reveal="fade">
  <p class="kicker">Películas</p>
  <h1>Películas para celular em São José/SC</h1>
  <p class="lead">A SmartPlanet vende película de tela e película de lente no bairro Campinas, em São José. A peça precisa ser do seu modelo. O preço e a disponibilidade saem no balcão ou no WhatsApp <?= e(SITE_PHONE_HUMAN) ?>.</p>
</header>

<section class="prose visit-note" data-reveal>
  <h2>Tela e lente não são a mesma peça</h2>
  <p>A película de tela cobre o vidro da frente. A película de lente cobre a câmera. As duas têm recorte diferente. Dizer só “quero uma película” deixa a loja sem saber qual das duas separar.</p>
  <p>No balcão, o caminho mais simples é levar o celular. Quem chama pelo WhatsApp escreve a marca, o modelo e se precisa da tela, da lente ou das duas. Se o nome do aparelho não estiver à mão, uma foto da parte de trás já ajuda.</p>
  <p>A loja trabalha com película de vidro e com película de privacidade. A de privacidade escurece a tela para quem olha de lado. Ela também precisa ser do modelo certo. O site não publica qual versão está no estoque hoje.</p>
  <p>Este site não descreve serviço de aplicação. A conversa confirma a película. Se você quiser saber como a loja entrega a peça, pergunte no WhatsApp junto com o modelo.</p>
</section>

<section class="two" data-reveal="fade">
  <article>
    <h2>O que muda o encaixe</h2>
    <p>iPhone, Samsung, Motorola e Xiaomi usam recortes diferentes, e o mesmo vale dentro de cada marca. Um modelo do ano passado não recebe a película do modelo novo. Por isso a SmartPlanet pede o nome completo do celular, não só a marca.</p>
  </article>
  <article>
    <h2>Retirada ou entrega</h2>
    <p>A retirada é na <?= e(SITE_STREET) ?>. A referência é o Direto do Campo, no mesmo endereço do Grauvi Empório Natural. Se você não puder vir, a entrega é combinada no WhatsApp, com bairro e cidade. Taxa e prazo não estão nesta página.</p>
  </article>
</section>

<section class="answers" data-reveal aria-labelledby="duvida-pelicula">
  <h2 id="duvida-pelicula">Pergunta frequente</h2>
  <dl>
    <div>
      <dt><?= e($faq[0]['q']) ?></dt>
      <dd><?= e($faq[0]['a']) ?></dd>
    </div>
  </dl>
</section>

<p class="section-more"><a class="btn gold" href="<?= e(wa_link('Oi, vim pelo site da SmartPlanet. Quero consultar película. Meu celular é ')) ?>" rel="noopener noreferrer" title="Consultar película no WhatsApp">Consultar película no WhatsApp</a></p>
