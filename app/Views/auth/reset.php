<?php

use App\Core\Csrf;
use App\Core\View;
?>
<p class="vault-kicker">Nova senha</p>
<h2>Definir nova senha</h2>
<form method="post" action="/reset">
  <?= Csrf::field() ?>
  <input type="hidden" name="token" value="<?= View::e($token) ?>">
  <div class="field">
    <label for="password">Nova senha</label>
    <input type="password" id="password" name="password" minlength="10" required autofocus autocomplete="new-password" placeholder="Mínimo de 10 caracteres">
  </div>
  <div class="field">
    <label for="confirmation">Confirmar senha</label>
    <input type="password" id="confirmation" name="password_confirmation" minlength="10" required autocomplete="new-password" placeholder="Repita a nova senha">
  </div>
  <button type="submit" class="primary">Alterar senha</button>
</form>

