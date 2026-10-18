<?php

use App\Core\Csrf;
use App\Core\View;
?>
<div class="page-header"><h1>Configurações</h1></div>
<div class="panel">
  <div class="panel-header">Empresa</div>
  <div class="panel-body">
    <form method="post" action="/settings/company">
      <?= Csrf::field() ?>
      <div class="grid two">
        <div class="field"><label>Razão social *</label><input type="text" name="legal_name" required value="<?= View::e($company['legal_name']) ?>"></div>
        <div class="field"><label>Nome fantasia *</label><input type="text" name="trade_name" required value="<?= View::e($company['trade_name']) ?>"></div>
        <div class="field"><label>CNPJ</label><input type="text" name="document" value="<?= View::e($company['document'] ?? '') ?>"></div>
        <div class="field"><label>Telefone</label><input type="text" name="phone" value="<?= View::e($company['phone'] ?? '') ?>"></div>
        <div class="field"><label>E-mail</label><input type="email" name="email" value="<?= View::e($company['email'] ?? '') ?>"></div>
      </div>
      <button class="primary">Salvar empresa</button>
    </form>
  </div>
</div>
<div class="grid three">
  <?php foreach ([['categories','Categorias',$categories],['brands','Marcas',$brands],['units','Unidades',$units]] as [$key,$label,$items]): ?>
    <div class="panel">
      <div class="panel-header"><?= $label ?></div>
      <div class="panel-body">
        <form method="post" action="/settings/dictionary">
          <?= Csrf::field() ?><input type="hidden" name="dictionary" value="<?= $key ?>">
          <?php if ($key === 'units'): ?>
            <div class="field"><label>Código *</label><input type="text" name="code" maxlength="12" required></div>
            <div class="field"><label>Casas decimais</label><input type="number" name="decimal_places" min="0" max="4" value="0"></div>
          <?php endif; ?>
          <div class="field"><label>Nome *</label><input type="text" name="name" required></div>
          <button>Adicionar</button>
        </form>
        <ul><?php foreach ($items as $item): ?><li><?= View::e(($item['code'] ?? '') . (($item['code'] ?? '') ? ' — ' : '') . $item['name']) ?></li><?php endforeach; ?></ul>
      </div>
    </div>
  <?php endforeach; ?>
</div>

