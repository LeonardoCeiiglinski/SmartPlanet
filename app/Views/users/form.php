<?php

use App\Core\Csrf;
use App\Core\View;

$u = $editingUser ?? [];
$selected = static fn (string $key, mixed $value): string => (string) ($u[$key] ?? '') === (string) $value ? ' selected' : '';
?>
<div class="page-header"><h1><?= $editingUser ? 'Editar usuário' : 'Novo usuário' ?></h1><a class="button" href="/users">Voltar</a></div>
<form method="post" action="/users/save" class="panel">
  <div class="panel-body">
    <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) ($u['id'] ?? 0) ?>">
    <div class="grid two">
      <div class="field"><label for="name">Nome *</label><input type="text" id="name" name="name" required value="<?= View::e($u['name'] ?? '') ?>"></div>
      <div class="field"><label for="email">E-mail *</label><input type="email" id="email" name="email" required value="<?= View::e($u['email'] ?? '') ?>"></div>
      <div class="field"><label for="role_id">Perfil *</label><select id="role_id" name="role_id" required><?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>"<?= $selected('role_id', $role['id']) ?>><?= View::e($role['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="branch_id">Filial *</label><select id="branch_id" name="branch_id" required><?php foreach ($branches as $branch): ?><option value="<?= (int) $branch['id'] ?>"<?= $selected('branch_id', $branch['id']) ?>><?= View::e($branch['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="password"><?= $editingUser ? 'Nova senha (opcional)' : 'Senha inicial *' ?></label><input type="password" id="password" name="password" minlength="10" <?= $editingUser ? '' : 'required' ?> autocomplete="new-password"></div>
      <label class="checkbox"><input type="checkbox" name="active" value="1" <?= !isset($u['active']) || $u['active'] ? 'checked' : '' ?>> Usuário ativo</label>
    </div>
    <div class="actions"><button class="primary" type="submit">Salvar</button><a class="button" href="/users">Cancelar</a></div>
  </div>
</form>

