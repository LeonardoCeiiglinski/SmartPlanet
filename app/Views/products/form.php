<?php

use App\Core\Csrf;
use App\Core\View;

$p = $product ?? [];
$selected = static fn (string $key, mixed $value): string => (string) ($p[$key] ?? '') === (string) $value ? ' selected' : '';
?>
<div class="page-header">
  <h1><?= $product ? 'Editar produto' : 'Novo produto ou serviço' ?></h1>
  <a class="button" href="/products">Voltar</a>
</div>
<form method="post" action="/products/save">
  <?= Csrf::field() ?>
  <input type="hidden" name="id" value="<?= (int) ($p['id'] ?? 0) ?>">
  <div class="panel">
    <div class="panel-header">Identificação</div>
    <div class="panel-body grid two">
      <div class="field"><label for="description">Descrição *</label><input type="text" id="description" name="description" maxlength="400" required value="<?= View::e($p['description'] ?? '') ?>"></div>
      <div class="field"><label for="complementary_name">Nome complementar</label><input type="text" id="complementary_name" name="complementary_name" value="<?= View::e($p['complementary_name'] ?? '') ?>"></div>
      <div class="field"><label for="internal_code">Código interno</label><input type="text" id="internal_code" name="internal_code" maxlength="64" value="<?= View::e($p['internal_code'] ?? '') ?>"><small>Em branco: gera código interno Code 128.</small></div>
      <div class="field"><label for="barcode">Código de barras</label><input type="text" id="barcode" name="barcode" maxlength="64" value="<?= View::e($p['barcode'] ?? '') ?>"><small>Texto: zeros à esquerda serão preservados. Deve ser único.</small></div>
      <div class="field"><label for="type">Tipo *</label><select id="type" name="type"><option value="product"<?= $selected('type', 'product') ?>>Produto</option><option value="service"<?= $selected('type', 'service') ?>>Serviço</option></select></div>
      <div class="field"><label for="unit_id">Unidade *</label><select id="unit_id" name="unit_id" required><option value="">Selecione</option><?php foreach ($units as $unit): ?><option value="<?= (int) $unit['id'] ?>"<?= $selected('unit_id', $unit['id']) ?>><?= View::e($unit['code'] . ' — ' . $unit['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="category_id">Categoria</label><select id="category_id" name="category_id"><option value="">Sem categoria</option><?php foreach ($categories as $item): ?><option value="<?= (int) $item['id'] ?>"<?= $selected('category_id', $item['id']) ?>><?= View::e($item['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="brand_id">Marca</label><select id="brand_id" name="brand_id"><option value="">Sem marca</option><?php foreach ($brands as $item): ?><option value="<?= (int) $item['id'] ?>"<?= $selected('brand_id', $item['id']) ?>><?= View::e($item['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="model">Modelo</label><input type="text" id="model" name="model" value="<?= View::e($p['model'] ?? '') ?>"></div>
      <div class="field"><label for="main_supplier_id">Fornecedor principal</label><select id="main_supplier_id" name="main_supplier_id"><option value="">Sem fornecedor</option><?php foreach ($suppliers as $item): ?><option value="<?= (int) $item['id'] ?>"<?= $selected('main_supplier_id', $item['id']) ?>><?= View::e($item['name']) ?></option><?php endforeach; ?></select></div>
    </div>
  </div>
  <div class="panel">
    <div class="panel-header">Preços e estoque</div>
    <div class="panel-body grid three">
      <div class="field"><label for="cost_price">Preço de custo</label><input type="text" inputmode="decimal" id="cost_price" name="cost_price" value="<?= View::e(number_format((float) ($p['cost_price'] ?? 0), 2, ',', '.')) ?>"></div>
      <div class="field"><label for="sale_price">Preço de venda *</label><input type="text" inputmode="decimal" id="sale_price" name="sale_price" required value="<?= View::e(number_format((float) ($p['sale_price'] ?? 0), 2, ',', '.')) ?>"></div>
      <div class="field"><label for="promotional_price">Preço promocional</label><input type="text" inputmode="decimal" id="promotional_price" name="promotional_price" value="<?= isset($p['promotional_price']) ? View::e(number_format((float) $p['promotional_price'], 2, ',', '.')) : '' ?>"></div>
      <div class="field"><label for="minimum_stock">Estoque mínimo</label><input type="text" inputmode="decimal" id="minimum_stock" name="minimum_stock" value="<?= View::e($p['minimum_stock'] ?? '0') ?>"></div>
      <?php if (!$product): ?><div class="field"><label for="initial_stock">Saldo inicial</label><input type="text" inputmode="decimal" id="initial_stock" name="initial_stock" value="0"><small>Depois do cadastro, use movimentos de estoque.</small></div><?php endif; ?>
      <label class="checkbox"><input type="checkbox" name="track_stock" value="1" <?= !isset($p['track_stock']) || $p['track_stock'] ? 'checked' : '' ?>> Movimentar estoque</label>
      <label class="checkbox"><input type="checkbox" name="active" value="1" <?= !isset($p['active']) || $p['active'] ? 'checked' : '' ?>> Ativo</label>
    </div>
  </div>
  <div class="panel">
    <div class="panel-header">Fiscal e observações</div>
    <div class="panel-body">
      <div class="grid three">
        <div class="field"><label for="ncm">NCM</label><input type="text" id="ncm" name="ncm" maxlength="10" value="<?= View::e($p['ncm'] ?? '') ?>"></div>
        <div class="field"><label for="cest">CEST</label><input type="text" id="cest" name="cest" maxlength="10" value="<?= View::e($p['cest'] ?? '') ?>"></div>
        <div class="field"><label for="origin_code">Origem</label><input type="text" id="origin_code" name="origin_code" maxlength="4" value="<?= View::e($p['origin_code'] ?? '') ?>"></div>
        <div class="field"><label for="cfop">CFOP</label><input type="text" id="cfop" name="cfop" maxlength="8" value="<?= View::e($p['cfop'] ?? '') ?>"></div>
        <div class="field"><label for="cst_csosn">CST/CSOSN</label><input type="text" id="cst_csosn" name="cst_csosn" maxlength="8" value="<?= View::e($p['cst_csosn'] ?? '') ?>"></div>
      </div>
      <div class="field"><label for="notes">Observações</label><textarea id="notes" name="notes" rows="3"><?= View::e($p['notes'] ?? '') ?></textarea></div>
      <p class="muted">Os campos fiscais devem ser fornecidos pelo contador. O sistema não calcula tributação nem emite documento fiscal nesta etapa.</p>
    </div>
  </div>
  <div class="actions"><button type="submit" class="primary">Salvar</button><a class="button" href="/products">Cancelar</a></div>
</form>

