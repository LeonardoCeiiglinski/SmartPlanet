<?php $faq = site_faqs('/carregadores'); ?>
<header class="page-hero" data-reveal="fade">
  <p class="kicker">Carregadores e cabos</p>
  <h1>Carregadores e cabos em São José/SC</h1>
  <p class="lead">A SmartPlanet separa cabo e carregador pela entrada do celular. Na loja, em Campinas, São José, isso significa USB-C, Lightning e V8. O modelo e o preço são confirmados no WhatsApp <?= e(SITE_PHONE_HUMAN) ?>.</p>
</header>

<section class="prose visit-note" data-reveal>
  <h2>A entrada do celular decide a compra</h2>
  <p>USB-C é a entrada oval usada em boa parte dos aparelhos atuais, inclusive em iPhones mais novos. Lightning é a entrada dos iPhones anteriores. V8 é a entrada achatada de aparelhos mais antigos. Um cabo de outro encaixe não carrega.</p>
  <p>Além do cabo, a loja tem carregador de tomada e carregador veicular. “Turbo” aqui quer dizer um carregador de potência maior, não uma marca só. A potência que serve no seu celular depende do aparelho. Por isso o site não escolhe a potência no seu lugar.</p>
  <p>Na mensagem, diga a marca e o modelo, se o uso é em casa, no carro ou na tomada, e se você precisa do cabo, da fonte ou dos dois. Se o celular fica dentro da capinha o dia inteiro, avise: alguns cabos encostam na borda da capa.</p>
  <p>Preço e estoque não ficam nesta página. Os dois mudam no balcão. A retirada é na <?= e(SITE_STREET) ?>. A entrega, quando você não pode vir, é confirmada com o bairro e a cidade.</p>
</section>

<section class="answers" data-reveal="fade" aria-labelledby="duvida-cabo">
  <h2 id="duvida-cabo">Pergunta frequente</h2>
  <dl>
    <div>
      <dt><?= e($faq[0]['q']) ?></dt>
      <dd><?= e($faq[0]['a']) ?></dd>
    </div>
  </dl>
</section>

<p class="section-more"><a class="btn gold" href="<?= e(wa_link('Oi, vim pelo site da SmartPlanet. Quero consultar cabo ou carregador. Meu celular é ')) ?>" rel="noopener noreferrer" title="Consultar carregador no WhatsApp">Consultar carregador e cabo</a></p>
