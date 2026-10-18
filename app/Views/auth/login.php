<?php

use App\Core\Csrf;
?>
<h2>Entrar</h2>
<form method="post" action="/login" autocomplete="on">
  <?= Csrf::field() ?>
  <div class="field">
    <label for="email">E-mail</label>
    <input type="email" id="email" name="email" required autofocus autocomplete="username">
  </div>
  <div class="field">
    <label for="password">Senha</label>
    <input type="password" id="password" name="password" required autocomplete="current-password">
  </div>
  <div class="actions">
    <button type="submit" class="primary">Entrar</button>
    <a href="/forgot">Esqueci minha senha</a>
  </div>
</form>

