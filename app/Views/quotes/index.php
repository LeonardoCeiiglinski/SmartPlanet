<?php use App\Core\Auth; use App\Core\Csrf; use App\Core\View; ?>
<div class="page-header"><h1>Orçamentos</h1></div>
<form method="post" action="/quotes/create" class="panel">
  <?= Csrf::field() ?>
  <input type="hidden" name="idempotency_key" value="<?= View::e(bin2hex(random_bytes(8))) ?>">
  <div class="panel-header">Novo orçamento</div>
  <div class="panel-body">
    <div class="grid three">
      <div class="field"><label>Cliente</label><select name="customer_id"><option value="">Consumidor final</option><?php foreach ($customers as $c): ?><option value="<?= (int) $c['id'] ?>"><?= View::e($c['name']) ?></option><?php endforeach ?></select></div>
      <div class="field"><label>Validade</label><input type="date" name="valid_until"></div>
      <div class="field"><label>Observação</label><input name="notes"></div>
    </div>
    <table><thead><tr><th>Produto</th><th>Qtd.</th><th>Desconto</th></tr></thead><tbody>
      <?php for ($i = 0; $i < 4; $i++): ?>
      <tr>
        <td><select name="items[<?= $i ?>][product_id]"><option value="">—</option><?php foreach ($products as $p): ?><option value="<?= (int) $p['id'] ?>"><?= View::e($p['description']) ?> · R$ <?= number_format((float) $p['sale_price'], 2, ',', '.') ?></option><?php endforeach ?></select></td>
        <td><input name="items[<?= $i ?>][quantity]" value="<?= $i === 0 ? '1' : '' ?>"></td>
        <td><input name="items[<?= $i ?>][discount]" value="0"></td>
      </tr>
      <?php endfor ?>
    </tbody></table>
    <button class="primary">Salvar orçamento</button>
  </div>
</form>
<?php if ($detail): ?>
<div class="panel"><div class="panel-header"><?= View::e($detail['quote_number']) ?> · <?= View::e($detail['status']) ?></div>
<div class="panel-body">
  <table><tbody><?php foreach ($detail['items'] as $item): ?><tr><td><?= View::e($item['description']) ?></td><td class="num"><?= number_format((float) $item['quantity'], 3, ',', '.') ?></td><td class="num">R$ <?= number_format((float) $item['subtotal'], 2, ',', '.') ?></td></tr><?php endforeach ?></tbody></table>
  <p class="num"><strong>Total R$ <?= number_format((float) $detail['total'], 2, ',', '.') ?></strong></p>
  <?php if (Auth::can('pos.sell') && in_array($detail['status'], ['open', 'approved'], true)): ?>
  <form method="post" action="/quotes/convert"><?= Csrf::field() ?>
    <input type="hidden" name="quote_id" value="<?= (int) $detail['id'] ?>">
    <input type="hidden" name="idempotency_key" value="quote-<?= (int) $detail['id'] ?>-<?= View::e(bin2hex(random_bytes(4))) ?>">
    <div class="grid two"><div class="field"><label>Pagamento</label><select name="method_id"><?php foreach ($methods as $m): ?><option value="<?= (int) $m['id'] ?>"><?= View::e($m['name']) ?></option><?php endforeach ?></select></div>
    <div class="field"><label>Valor</label><input name="amount" value="<?= View::e(number_format((float) $detail['total'], 2, ',', '.')) ?>"></div></div>
    <button class="primary">Converter em venda</button>
  </form>
  <?php endif ?>
</div></div>
<?php endif ?>
<div class="panel"><div class="panel-body table-wrap"><table>
<thead><tr><th>Número</th><th>Cliente</th><th>Status</th><th>Validade</th><th class="num">Total</th></tr></thead>
<tbody><?php foreach ($quotes as $q): ?><tr><td><a href="/quotes?id=<?= (int) $q['id'] ?>"><?= View::e($q['quote_number']) ?></a></td><td><?= View::e($q['customer_name'] ?? 'Consumidor final') ?></td><td><?= View::e($q['status']) ?></td><td><?= View::e($q['valid_until'] ?? '') ?></td><td class="num">R$ <?= number_format((float) $q['total'], 2, ',', '.') ?></td></tr><?php endforeach ?></tbody>
</table></div></div>
