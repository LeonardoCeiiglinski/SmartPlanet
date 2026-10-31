<?php use App\Core\Auth; use App\Core\Csrf; use App\Core\Security; use App\Core\View; use App\Domain\Sales\ReceiptSlip;
$receiptPayload = null;
if ($receipt) {
    $receiptPayload = [
        'tradeName' => (string) ($company['trade_name'] ?? 'SmartPlanet'),
        'phone' => (string) ($company['phone'] ?? ''),
        'address' => $receiptAddress ?? [],
        'saleNumber' => (string) $receipt['sale_number'],
        'completedAt' => (string) ($receipt['completed_at'] ?: $receipt['created_at']),
        'operator' => (string) ($receipt['operator_name'] ?? ''),
        'customer' => (string) ($receipt['customer_name'] ?? ''),
        'status' => (string) $receipt['status'],
        'discount' => (float) $receipt['discount_amount'],
        'total' => (float) $receipt['total'],
        'items' => array_map(static fn (array $i): array => [
            'description' => (string) $i['description'],
            'quantity' => (float) $i['quantity'],
            'unitPrice' => (float) $i['unit_price'],
            'subtotal' => (float) $i['subtotal'],
        ], $receipt['items']),
        'payments' => array_map(static fn (array $p): array => [
            'name' => (string) $p['method_name'],
            'amount' => (float) $p['amount'],
            'installments' => (int) $p['installments'],
        ], $receipt['payments']),
    ];
}
$vReceipt = (int) (@filemtime(dirname(__DIR__, 3) . '/public/assets/receipt.js') ?: time());
$vReceipt58 = (int) (@filemtime(dirname(__DIR__, 3) . '/public/assets/receipt-58.js') ?: time());
?>
<div class="page-header"><h1>Vendas</h1><a class="button primary" href="/pos">Nova venda</a></div>
<?php if ($receiptPayload): ?>
<section class="receipt" aria-label="Recibo não fiscal">
  <div class="receipt-vias">
    <?php foreach (ReceiptSlip::vias($receiptPayload) as $via): ?>
    <div class="receipt-col">
      <label class="receipt-pick"><input type="checkbox" data-receipt-pick value="<?= View::e($via['id']) ?>" checked> <?= View::e($via['title']) ?></label>
      <article class="receipt-slip" data-via="<?= View::e($via['id']) ?>">
        <div class="receipt-watermark" aria-hidden="true"><?php for ($w = 0; $w < 3; $w++): ?><span>SmartPlanet</span><span><?= View::e(ReceiptSlip::DOMAIN) ?></span><?php endfor ?></div>
        <div class="receipt-text"><?php foreach ($via['lines'] as $line): ?><div class="receipt-line<?= !empty($line['bold']) ? ' is-bold' : '' ?><?= !empty($line['via']) ? ' is-via' : '' ?>"><?= View::e($line['text']) ?></div><?php endforeach ?></div>
      </article>
    </div>
    <?php endforeach ?>
  </div>
  <div class="receipt-actions">
    <button type="button" class="primary" data-receipt-usb>Imprimir</button>
    <button type="button" data-receipt-window>Pelo Windows</button>
  </div>
  <p class="receipt-status" role="status"></p>
  <p class="receipt-note">Marque a via do cliente, a da loja ou as duas. Cada cupom sai com o nome da via. Papel 58 mm.</p>
  <script type="application/json" id="receipt-payload"><?= json_encode($receiptPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR) ?></script>
  <script<?= Security::nonceAttribute() ?> src="/assets/receipt-58.js?v=<?= $vReceipt58 ?>"></script>
  <script<?= Security::nonceAttribute() ?> src="/assets/receipt.js?v=<?= $vReceipt ?>"></script>
</section>
<?php endif ?>
<div class="panel"><div class="panel-body table-wrap"><table><thead><tr><th>Número</th><th>Data</th><th>Operador</th><th>Cliente</th><th>Status</th><th class="num">Total</th><th>Ações</th></tr></thead><tbody><?php foreach($sales as $s): ?><tr><td><a href="/sales?receipt=<?= (int)$s['id'] ?>"><?= View::e($s['sale_number']) ?></a></td><td><?= View::e($s['created_at']) ?></td><td><?= View::e($s['operator_name']) ?></td><td><?= View::e($s['customer_name']??'Consumidor final') ?></td><td><?= View::e($s['status']) ?></td><td class="num">R$ <?= number_format((float)$s['total'],2,',','.') ?></td><td><?php if($s['status']==='completed'&&Auth::can('sales.cancel')): ?><form method="post" action="/sales/cancel" class="actions" data-confirm-twice="cancelar" data-confirm-item="<?= View::e($s['sale_number']) ?>"><?= Csrf::field() ?><input type="hidden" name="sale_id" value="<?= (int)$s['id'] ?>"><input name="reason" minlength="5" placeholder="Ex.: Cliente desistiu da compra" required><button class="danger small">Cancelar/estornar</button></form><?php endif ?></td></tr><?php endforeach ?></tbody></table></div></div>
<?php if($receipt && $receipt['status']==='completed' && Auth::can('sales.return')): ?>
<form method="post" action="/sales/return" class="panel" data-confirm-twice="devolver" data-confirm-item="<?= View::e($receipt['sale_number']) ?>"><?= Csrf::field() ?>
  <input type="hidden" name="sale_id" value="<?= (int)$receipt['id'] ?>">
  <input type="hidden" name="idempotency_key" value="ret-<?= (int)$receipt['id'] ?>-<?= View::e(bin2hex(random_bytes(4))) ?>">
  <div class="panel-header">Devolução parcial ou total</div>
  <div class="panel-body">
    <table><thead><tr><th></th><th>Item</th><th>Qtd. disponível</th><th>Devolver</th><th>Estoque</th></tr></thead><tbody>
    <?php foreach($receipt['items'] as $i): $available=(float)$i['quantity']-(float)$i['returned_quantity']; if($available<=0) continue; ?>
      <tr>
        <td><input type="checkbox" name="items[<?= (int)$i['id'] ?>][selected]" value="1"></td>
        <td><?= View::e($i['description']) ?><input type="hidden" name="items[<?= (int)$i['id'] ?>][sale_item_id]" value="<?= (int)$i['id'] ?>"></td>
        <td class="num"><?= number_format($available,3,',','.') ?></td>
        <td><input name="items[<?= (int)$i['id'] ?>][quantity]" value="<?= number_format($available,3,'.','') ?>" placeholder="Ex.: 1"></td>
        <td><label class="checkbox"><input type="checkbox" name="items[<?= (int)$i['id'] ?>][return_to_stock]" value="1" checked> Devolver ao estoque</label></td>
      </tr>
    <?php endforeach ?></tbody></table>
    <div class="grid two"><div class="field"><label>Motivo</label><input name="reason" minlength="5" required placeholder="Ex.: Produto com defeito de fábrica"></div><label class="checkbox"><input type="checkbox" name="refund_cash" value="1"> Estornar em dinheiro no caixa aberto</label></div>
    <button class="danger">Confirmar devolução</button>
    <p class="muted">A devolução referencia esta venda. Não é possível devolver quantidade acima do saldo ainda não devolvido.</p>
  </div>
</form>
<?php endif ?>
