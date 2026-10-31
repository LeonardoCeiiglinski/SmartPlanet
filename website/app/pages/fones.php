<?php $faq = site_faqs('/fones'); ?>
<header class="page-hero" data-reveal="fade">
  <p class="kicker">Fones</p>
  <h1>Fones de ouvido em São José/SC</h1>
  <p class="lead">A SmartPlanet, loja de eletrônicos no bairro Campinas, confirma fone com fio no balcão. Fone sem fio, inclusive bluetooth, é consultado no WhatsApp <?= e(SITE_PHONE_HUMAN) ?>, porque o estoque do dia não está neste site.</p>
</header>

<section class="prose visit-note" data-reveal>
  <h2>Com fio e sem fio</h2>
  <p>O fone com fio usa a entrada do celular ou um adaptador, conforme o aparelho. Em muitos modelos novos não há entrada de fone, e o som sai pelo mesmo USB-C da carga. Diga o modelo para a loja não separar um plugue que o seu celular não tem.</p>
  <p>Fone bluetooth não está publicado como item fixo desta página. Se você procura um, escreva isso na mensagem e diga se o uso é ligação, música ou os dois. A loja responde se tem no balcão.</p>
  <p>Headset de jogos fica no <a href="/linha-gamer">painel GAMER</a>, não nesta prateleira de fone do dia a dia. São peças diferentes: uma é para o celular, a outra aparece no painel ao lado do balcão.</p>
  <p>A retirada é na <?= e(SITE_STREET) ?>, referência Direto do Campo, no mesmo endereço do Grauvi Empório Natural. Horário: <?= e(SITE_HOURS) ?></p>
</section>

<section class="answers" data-reveal aria-labelledby="duvida-fone">
  <h2 id="duvida-fone">Pergunta frequente</h2>
  <dl>
    <div>
      <dt><?= e($faq[0]['q']) ?></dt>
      <dd><?= e($faq[0]['a']) ?></dd>
    </div>
  </dl>
</section>

<p class="section-more"><a class="btn gold" href="<?= e(wa_link('Oi, vim pelo site da SmartPlanet. Quero consultar um fone. Meu celular é ')) ?>" rel="noopener noreferrer" title="Consultar fone no WhatsApp">Consultar fone no WhatsApp</a></p>
