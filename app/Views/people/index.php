<?php

use App\Core\Csrf;
use App\Core\View;

$label = $type === 'customers' ? 'cliente' : 'fornecedor';
?>
<div class="page-header"><h1><?= ucfirst($label) ?>s</h1></div>
<div class="panel">
  <div class="panel-header">Novo <?= $label ?></div>
  <div class="panel-body">
    <form method="post" action="/people/save">
      <?= Csrf::field() ?><input type="hidden" name="type" value="<?= View::e($type) ?>">
      <div class="grid three">
        <div class="field"><label>Nome / Razão social *</label><input type="text" name="name" required></div>
        <div class="field"><label>CPF/CNPJ</label><input type="text" name="document"></div>
        <div class="field"><label>Telefone</label><input type="text" name="phone"></div>
        <div class="field"><label>E-mail</label><input type="email" name="email"></div>
        <div class="field"><label>Observações</label><input type="text" name="notes"></div>
      </div>
      <button type="submit" class="primary">Cadastrar</button>
    </form>
  </div>
</div>
<div class="panel"><div class="panel-body">
  <form class="filters" method="get" action="/people">
    <input type="hidden" name="type" value="<?= View::e($type) ?>">
    <div class="field"><label>Pesquisar</label><input type="text" name="q" value="<?= View::e($query) ?>"></div>
    <button>Pesquisar</button>
  </form>
  <div class="table-wrap"><table>
    <thead><tr><th>Nome</th><th>CPF/CNPJ</th><th>Telefone</th><th>E-mail</th><th>Situação</th></tr></thead>
    <tbody>
    <?php foreach ($items as $item): ?>
      <tr><td><?= View::e($item['name']) ?></td><td><?= View::e($item['document'] ?: '—') ?></td>
        <td><?= View::e($item['phone'] ?: '—') ?></td><td><?= View::e($item['email'] ?: '—') ?></td>
        <td><span class="badge <?= $item['active'] ? 'ok' : 'danger' ?>"><?= $item['active'] ? 'Ativo' : 'Inativo' ?></span></td></tr>
    <?php endforeach; ?>
    <?php if (!$items): ?><tr><td colspan="5" class="empty">Nenhum cadastro.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div></div>

