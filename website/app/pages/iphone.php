<?php $faq = site_faqs('/iphone'); ?>
<header class="page-hero" data-reveal="fade">
  <p class="kicker">iPhone</p>
  <h1>Capinhas e películas para iPhone em São José/SC</h1>
  <p class="lead">A SmartPlanet separa capinha, película e carregador de iPhone pelo modelo do aparelho. A loja fica no bairro Campinas, em São José. O encaixe é confirmado no balcão ou no WhatsApp <?= e(SITE_PHONE_HUMAN) ?>.</p>
</header>

<section class="prose visit-note" data-reveal>
  <h2>O modelo importa mais do que a marca</h2>
  <p>Dizer só “iPhone” não basta. A capinha de um modelo não fecha no outro: a câmera, os botões e a espessura mudam. Envie o nome como está nas configurações, por exemplo o número depois da palavra iPhone.</p>
  <p>A loja trabalha com capinha para iPhone, inclusive capa de silicone, e com película, inclusive película de privacidade. A película de privacidade escurece a visão lateral da tela. As duas precisam do recorte daquele modelo.</p>
  <p>No carregador, iPhones mais antigos usam Lightning. Modelos mais novos usam USB-C. A SmartPlanet tem carregador para os dois encaixes, incluindo versões turbo. Qual deles serve no seu aparelho é uma confirmação, não um chute desta página.</p>
  <p>Samsung, Motorola e Xiaomi seguem outra conversa: <a href="/acessorios">a página de acessórios</a> pede a marca e o modelo, sem uma página separada para cada uma. O estoque de capinha muda, e o site não finge que todas as marcas estão sempre na parede.</p>
  <p>A retirada é na <?= e(SITE_STREET) ?>, CEP <?= e(SITE_CEP) ?>. A referência é o Direto do Campo, no mesmo endereço do Grauvi Empório Natural. Horário: <?= e(SITE_HOURS) ?> Se a entrega for melhor, informe o bairro e a cidade no WhatsApp.</p>
</section>

<section class="answers" data-reveal aria-labelledby="duvida-iphone">
  <h2 id="duvida-iphone">Pergunta frequente</h2>
  <dl>
    <div>
      <dt><?= e($faq[0]['q']) ?></dt>
      <dd><?= e($faq[0]['a']) ?></dd>
    </div>
  </dl>
</section>

<p class="section-more"><a class="btn gold" href="<?= e(wa_link('Oi, vim pelo site da SmartPlanet. Quero consultar acessório para iPhone. O modelo é ')) ?>" rel="noopener noreferrer" title="Consultar acessório de iPhone no WhatsApp">Consultar para o meu iPhone</a></p>
