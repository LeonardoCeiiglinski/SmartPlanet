<?php

use App\Core\Csrf;
?>
<h2>Recuperar senha</h2>
<p class="muted">Informe o e-mail cadastrado. O link expira em uma hora.</p>
<form method="post" action="/forgot">
  <?= Csrf::field() ?>
  <div class="field">
    <label for="email">E-mail</label>
    <input type="email" id="email" name="email" required autofocus placeholder="Ex.: contato@empresa.com.br">
  </div>
  <div class="actions">
    <button type="submit" class="primary">Enviar instruções</button>
    <a href="/login">Voltar</a>
  </div>
</form>

