<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
?>
<div class="page-header">
  <h1>Produtos e serviços</h1>
  <div class="actions">
    <a class="button" href="/products/export">Exportar CSV</a>
    <?php if (Auth::can('products.manage')): ?><a class="button" href="/products/import">Importar</a><a class="button primary" href="/products/new">Novo cadastro</a><?php endif; ?>
  </div>
</div>
<div class="panel">
  <div class="panel-body">
    <form method="get" action="/products" class="filters">
      <div class="field">
        <label for="q">Produto</label>
        <select id="q" name="q" data-scanner="search" data-submit-on-change="1">
          <option value="">Selecione uma opção...</option>
          <?php foreach ($choices as $choice): ?>
            <?php
              $choiceValue = trim((string) $choice['internal_code']) !== '' ? (string) $choice['internal_code'] : (string) $choice['description'];
              $choiceLabel = trim((string) $choice['internal_code']) !== '' ? $choice['internal_code'] . ' · ' . $choice['description'] : (string) $choice['description'];
              if (!(int) $choice['active']) $choiceLabel .= ' · inativo';
              $picked = $search !== '' && in_array($search, [(string) $choice['internal_code'], (string) ($choice['barcode'] ?? ''), (string) $choice['description'], $choiceValue], true);
            ?>
            <option value="<?= View::e($choiceValue) ?>" data-code="<?= View::e($choice['internal_code']) ?>" data-barcode="<?= View::e($choice['barcode'] ?? '') ?>"<?= $picked ? ' selected' : '' ?>><?= View::e($choiceLabel) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit">Pesquisar</button>
      <?php if ($search !== ''): ?><a class="button" href="/products">Limpar</a><?php endif; ?>
    </form>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Código</th><th>Descrição</th><th>Tipo</th><th>Un.</th><th>Código de barras</th><th class="num">Estoque</th><th class="num">Preço</th><th>Situação</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($result['items'] as $product): ?>
          <tr class="<?= $product['active'] ? '' : 'inactive' ?>">
            <td><?= View::e($product['internal_code']) ?></td>
            <td><strong><?= View::e($product['description']) ?></strong><?php if ($product['category_name']): ?><br><small><?= View::e($product['category_name']) ?></small><?php endif; ?></td>
            <td><?= $product['type'] === 'service' ? 'Serviço' : 'Produto' ?></td>
            <td><?= View::e($product['unit_code']) ?></td>
            <td><?= View::e($product['barcode'] ?: '—') ?></td>
            <td class="num"><?= number_format((float) $product['stock_quantity'], 4, ',', '.') ?></td>
            <td class="num">R$ <?= number_format((float) $product['sale_price'], 2, ',', '.') ?></td>
            <td><span class="badge <?= $product['active'] ? 'ok' : 'danger' ?>"><?= $product['active'] ? 'Ativo' : 'Inativo' ?></span></td>
            <td>
              <div class="actions">
                <?php if (Auth::can('products.manage')): ?><a class="button small" href="/products/edit?id=<?= (int) $product['id'] ?>">Editar</a><?php endif; ?>
                <?php if (Auth::can('labels.print') && $product['active']): ?><a class="button small" href="/labels?product=<?= (int) $product['id'] ?>&amp;qty=9">Etiqueta</a><?php endif; ?>
                <?php if (Auth::can('products.manage')): ?>
                  <form method="post" action="/products/status"<?php if ($product['active']): ?> data-confirm-twice="inativar" data-confirm-item="<?= View::e($product['description']) ?>"<?php endif; ?>>
                    <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                    <input type="hidden" name="active" value="<?= $product['active'] ? '0' : '1' ?>">
                    <button class="small" type="submit"><?= $product['active'] ? 'Inativar' : 'Ativar' ?></button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($result['items'] === []): ?><tr><td colspan="9" class="empty">Nenhum cadastro encontrado.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="pagination">
      <?php for ($page = 1; $page <= $result['pages']; $page++): ?>
        <a class="<?= $page === $result['page'] ? 'active' : '' ?>" href="/products?page=<?= $page ?>&q=<?= urlencode($search) ?>"><?= $page ?></a>
      <?php endfor; ?>
    </div>
  </div>
</div>

