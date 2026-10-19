<?php

use App\Core\Csrf;
use App\Core\View;

$u = $editingUser ?? [];
$selected = static fn (string $key, mixed $value): string => (string) ($u[$key] ?? '') === (string) $value ? ' selected' : '';
$masterLocked = !empty($masterLocked);
?>
<div class="page-header"><h1><?= $editingUser ? 'Editar funcionário' : 'Novo funcionário' ?></h1><a class="button" href="/users">Voltar</a></div>
<form method="post" action="/users/save" class="panel">
  <div class="panel-body">
    <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) ($u['id'] ?? 0) ?>">
    <?php if ($masterLocked): ?>
      <p>Este é o administrador master, dono da loja. O perfil e o acesso dele não são transferidos para um funcionário.</p>
    <?php else: ?>
      <p>Informe o e-mail que o funcionário usará para entrar e uma senha inicial de pelo menos 10 caracteres. Entregue esses dados pessoalmente. O funcionário não cria a própria conta.</p>
    <?php endif; ?>
    <div class="grid two">
      <div class="field"><label for="name">Nome *</label><input type="text" id="name" name="name" required value="<?= View::e($u['name'] ?? '') ?>" placeholder="Ex.: João da Silva"></div>
      <div class="field"><label for="email">E-mail de login *</label><input type="email" id="email" name="email" required value="<?= View::e($u['email'] ?? '') ?>" placeholder="Ex.: helena.prado@smartplanet.exemplo"></div>
      <?php if ($masterLocked): ?>
        <div class="field"><label>Perfil</label><input type="text" value="Administrador master" disabled></div>
      <?php else: ?>
        <div class="field"><label for="role_id">Perfil *</label><select id="role_id" name="role_id" required><?php if (!$editingUser): ?><option value="">Selecione uma opção...</option><?php endif; ?><?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>"<?= $selected('role_id', $role['id']) ?>><?= View::e($role['name']) ?></option><?php endforeach; ?></select></div>
      <?php endif; ?>
      <div class="field"><label for="branch_id">Filial *</label><select id="branch_id" name="branch_id" required><?php foreach ($branches as $branch): ?><option value="<?= (int) $branch['id'] ?>"<?= $selected('branch_id', $branch['id']) ?>><?= View::e($branch['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="password"><?= $editingUser ? 'Nova senha (opcional)' : 'Senha inicial *' ?></label><input type="password" id="password" name="password" minlength="10" <?= $editingUser ? '' : 'required' ?> autocomplete="new-password" placeholder="<?= $editingUser ? 'Deixe em branco para manter a senha atual' : 'Mínimo de 10 caracteres' ?>"></div>
      <?php if (!$masterLocked): ?>
        <label class="checkbox"><input type="checkbox" name="active" value="1" <?= !isset($u['active']) || $u['active'] ? 'checked' : '' ?>> Login ativo</label>
      <?php endif; ?>
    </div>
    <div class="actions"><button class="primary" type="submit">Salvar</button><a class="button" href="/users">Cancelar</a></div>
  </div>
</form>
