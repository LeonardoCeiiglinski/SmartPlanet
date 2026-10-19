<?php

use App\Core\Csrf;
?>
<p class="vault-kicker">Acesso protegido</p>
<h1>SmartPlanet</h1>
<p class="vault-lead">Entre com o usuário master da loja. A senha não fica gravada em texto puro.</p>
<form method="post" action="/login" data-vault autocomplete="on">
  <?= Csrf::field() ?>
  <div class="vault-trap" aria-hidden="true">
    <label for="sp_hp">Não preencha</label>
    <input id="sp_hp" name="sp_hp" type="text" tabindex="-1" autocomplete="off">
  </div>
  <div class="field">
    <label for="email">E-mail</label>
    <input type="email" id="email" name="email" required autofocus maxlength="191" autocomplete="username" inputmode="email" spellcheck="false" placeholder="Ex.: contato@empresa.com.br">
  </div>
  <div class="field">
    <label for="password">Senha</label>
    <div class="vault-password">
      <input type="password" id="password" name="password" required maxlength="200" autocomplete="current-password" placeholder="Digite sua senha">
      <button type="button" data-toggle-password="password">Mostrar</button>
    </div>
  </div>
  <div class="actions">
    <button type="submit" class="primary">Entrar</button>
  </div>
</form>
<ul class="vault-rules">
  <li>Cinco erros no mesmo e-mail travam a entrada por 15 minutos.</li>
  <li>O cookie da sessão é HttpOnly e não pode ser lido por script.</li>
  <li>A sessão encerra depois de 30 minutos sem uso, ou em 12 horas.</li>
</ul>
<p class="muted"><a href="/forgot">Esqueci minha senha</a></p>
