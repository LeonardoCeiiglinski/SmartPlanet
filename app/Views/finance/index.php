<?php use App\Core\Auth; use App\Core\Csrf; use App\Core\View; ?>
<div class="page-header"><h1>Financeiro</h1></div>
<div class="grid two">
  <div class="stat"><span>Contas a receber em aberto</span><strong>R$ <?= number_format($openIn, 2, ',', '.') ?></strong></div>
  <div class="stat"><span>Contas a pagar em aberto</span><strong>R$ <?= number_format($openOut, 2, ',', '.') ?></strong></div>
</div>
<?php if (Auth::can('finance.manage')): ?>
<form method="post" action="/finance/create" class="panel"><?= Csrf::field() ?>
  <div class="panel-header">Novo lançamento</div>
  <div class="panel-body grid three">
    <div class="field"><label>Direção</label><select name="direction" required><option value="">Selecione uma opção...</option><option value="in">Receita / a receber</option><option value="out">Despesa / a pagar</option></select></div>
    <div class="field"><label>Descrição</label><input name="description" required placeholder="Ex.: Conta de energia da loja"></div>
    <div class="field"><label>Valor</label><input name="amount" data-mask="money" required placeholder="Ex.: 150,00"></div>
    <div class="field"><label>Emissão</label><input type="date" name="issue_date" value="<?= date('Y-m-d') ?>" title="Ex.: <?= date('d/m/Y') ?>"></div>
    <div class="field"><label>Vencimento</label><input type="date" name="due_date" value="<?= date('Y-m-d') ?>" title="Ex.: <?= date('d/m/Y') ?>"></div>
    <div class="field"><label>Cliente/fornecedor</label><select name="party_id"><option value="">Selecione uma opção...</option><?php foreach ($customers as $c): ?><option value="<?= (int) $c['id'] ?>">Cliente: <?= View::e($c['name']) ?></option><?php endforeach ?><?php foreach ($suppliers as $s): ?><option value="<?= (int) $s['id'] ?>">Fornecedor: <?= View::e($s['name']) ?></option><?php endforeach ?></select></div>
  </div>
  <button class="primary">Lançar</button>
</form>
<?php endif ?>
<div class="panel"><div class="panel-body table-wrap"><table>
<thead><tr><th>Vencimento</th><th>Descrição</th><th>Direção</th><th>Status</th><th class="num">Valor</th><th class="num">Liquidado</th><th>Ação</th></tr></thead>
<tbody><?php foreach ($entries as $e): ?>
<tr>
  <td><?= View::e($e['due_date']) ?></td>
  <td><?= View::e($e['description']) ?><?php if ($e['customer_name'] || $e['supplier_name']): ?><br><small><?= View::e($e['customer_name'] ?: $e['supplier_name']) ?></small><?php endif ?></td>
  <td><?= $e['direction'] === 'in' ? 'Entrada' : 'Saída' ?></td>
  <td><?= View::e($e['status']) ?></td>
  <td class="num">R$ <?= number_format((float) $e['amount'], 2, ',', '.') ?></td>
  <td class="num">R$ <?= number_format((float) $e['settled_amount'], 2, ',', '.') ?></td>
  <td>
    <?php if (Auth::can('finance.manage') && in_array($e['status'], ['open', 'partial'], true)): ?>
    <form method="post" action="/finance/settle" class="actions"><?= Csrf::field() ?>
      <input type="hidden" name="entry_id" value="<?= (int) $e['id'] ?>">
      <input type="hidden" name="idempotency_key" value="liq-<?= (int) $e['id'] ?>-<?= View::e(bin2hex(random_bytes(3))) ?>">
      <select name="account_id" required><option value="">Selecione uma opção...</option><?php foreach ($accounts as $a): ?><option value="<?= (int) $a['id'] ?>"><?= View::e($a['name']) ?></option><?php endforeach ?></select>
      <input name="amount" data-mask="money" value="<?= number_format((float) $e['amount'] - (float) $e['settled_amount'], 2, ',', '.') ?>" style="width:90px" placeholder="Ex.: 150,00">
      <button class="small">Liquidar</button>
    </form>
    <?php endif ?>
  </td>
</tr>
<?php endforeach ?></tbody>
</table></div></div>
<p class="muted">Faturamento, recebimento, lucro e saldo de caixa são conceitos distintos. Este módulo registra contas e liquidações; o caixa físico é controlado em Caixa.</p>
