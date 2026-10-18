<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
?>
<div class="page-header"><h1>Estoque</h1><span class="badge <?= $overview['low'] ? 'danger' : 'ok' ?>"><?= (int) $overview['low'] ?> alerta(s) de mínimo</span></div>
<?php if (Auth::can('stock.adjust')): ?>
<div class="grid two">
  <div class="panel"><div class="panel-header">Ajuste autorizado</div><div class="panel-body">
    <form method="post" action="/stock/adjust"><?= Csrf::field() ?>
      <div class="grid two"><div class="field"><label>Produto</label><select name="product_id" required><option value="">Selecione uma opção...</option>
        <?php foreach ($overview['items'] as $item): ?><option value="<?= (int) $item['id'] ?>"><?= View::e($item['internal_code'] . ' · ' . $item['description']) ?></option><?php endforeach; ?>
      </select></div><div class="field"><label>Local</label><select name="location_id" required><option value="">Selecione uma opção...</option>
        <?php foreach ($locations as $location): ?><option value="<?= (int) $location['id'] ?>"><?= View::e($location['name']) ?></option><?php endforeach; ?>
      </select></div></div>
      <div class="grid two"><div class="field"><label>Quantidade (+ entrada / - saída)</label><input name="quantity" required placeholder="Ex.: 10 ou -3"></div>
      <div class="field"><label>Justificativa *</label><input name="reason" minlength="5" required placeholder="Ex.: Ajuste após conferência física"></div></div>
      <button class="primary">Registrar ajuste imutável</button>
    </form>
  </div></div>
  <div class="panel"><div class="panel-header">Novo inventário</div><div class="panel-body">
    <form method="post" action="/stock/inventory/start"><?= Csrf::field() ?>
      <div class="field"><label>Local</label><select name="location_id" required><option value="">Selecione uma opção...</option><?php foreach ($locations as $location): ?><option value="<?= (int) $location['id'] ?>"><?= View::e($location['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Observações</label><input name="notes" placeholder="Ex.: Inventário mensal da loja"></div><button class="primary">Iniciar contagem</button>
    </form>
  </div></div>
</div>
<?php endif; ?>
<div class="panel"><div class="panel-header">Saldos</div><div class="panel-body">
  <form class="filters" method="get" action="/stock"><div class="field"><label>Pesquisar</label><input name="q" data-scanner="search" value="<?= View::e($query) ?>" placeholder="Digite ou bipe o código de barras..."></div><button>Filtrar</button></form>
  <div class="table-wrap"><table><thead><tr><th>Código</th><th>Produto</th><th class="num">Mínimo</th><th class="num">Saldo</th><th></th></tr></thead><tbody>
  <?php foreach ($overview['items'] as $item): $low=(float)$item['quantity'] <= (float)$item['minimum_stock']; ?>
    <tr><td><?= View::e($item['internal_code']) ?></td><td><?= View::e($item['description']) ?> <?= $low ? '<span class="badge danger">Baixo</span>' : '' ?></td>
    <td class="num"><?= number_format((float)$item['minimum_stock'],4,',','.') ?></td><td class="num"><?= number_format((float)$item['quantity'],4,',','.') ?> <?= View::e($item['unit_code']) ?></td>
    <td>
      <div class="actions">
        <?php if (Auth::can('labels.print')): ?><a class="button small" href="/labels?product=<?= (int) $item['id'] ?>&amp;qty=9">Etiqueta</a><?php endif; ?>
        <a class="button small" href="/stock?product=<?= (int)$item['id'] ?>">Histórico</a>
      </div>
    </td></tr>
  <?php endforeach; ?></tbody></table></div>
</div></div>
<div class="panel"><div class="panel-header">Inventários</div><div class="panel-body">
<?php foreach ($inventories as $inventory): ?>
  <p><strong>#<?= (int)$inventory['id'] ?> · <?= View::e($inventory['location_name']) ?></strong> <span class="badge"><?= View::e($inventory['status']) ?></span>
    <?php if ($inventory['status']==='open'): ?><a href="/stock?inventory=<?= (int)$inventory['id'] ?>">Contar</a><?php endif; ?></p>
  <?php if ($inventory['items']): ?><form method="post" action="/stock/inventory/close"><?= Csrf::field() ?><input type="hidden" name="inventory_id" value="<?= (int)$inventory['id'] ?>">
    <div class="table-wrap"><table><thead><tr><th>Produto</th><th class="num">Esperado</th><th>Contado</th></tr></thead><tbody>
    <?php foreach ($inventory['items'] as $item): ?><tr><td><?= View::e($item['description']) ?></td><td class="num"><?= number_format((float)$item['expected_quantity'],4,',','.') ?></td>
      <td><input name="counts[<?= (int)$item['product_id'] ?>]" required placeholder="Ex.: 10"></td></tr><?php endforeach; ?></tbody></table></div><button class="primary">Fechar inventário</button></form><?php endif; ?>
<?php endforeach; ?>
</div></div>
<div class="panel"><div class="panel-header">Histórico de movimentos</div><div class="panel-body"><div class="table-wrap"><table>
<thead><tr><th>Data</th><th>Produto</th><th>Tipo</th><th class="num">Quantidade</th><th>Origem / justificativa</th><th>Usuário</th></tr></thead><tbody>
<?php foreach ($history as $movement): ?><tr><td><?= View::e($movement['occurred_at']) ?></td><td><?= View::e($movement['description']) ?></td><td><?= View::e($movement['movement_type']) ?></td>
<td class="num"><?= number_format((float)$movement['quantity'],4,',','.') ?></td><td><?= View::e($movement['source_type'].' #'.$movement['source_id'].' · '.($movement['reason'] ?: '—')) ?></td><td><?= View::e($movement['user_name'] ?: 'Sistema') ?></td></tr><?php endforeach; ?>
</tbody></table></div></div></div>
