<?php

use App\Core\Csrf;
use App\Core\View;
?>
<div class="page-header"><h1>Importar produtos e serviços</h1><a class="button" href="/products">Voltar</a></div>
<div class="panel">
  <div class="panel-header">1. Validar arquivo</div>
  <div class="panel-body">
    <p>Use CSV UTF-8 separado por ponto e vírgula. Abra e salve normalmente no Excel ou LibreOffice. Nenhum registro é gravado antes da confirmação.</p>
    <div class="actions" style="margin-bottom:12px"><a class="button" href="/products/import/template">Baixar modelo CSV</a><a class="button" href="/products/export">Exportar cadastros atuais</a></div>
    <form method="post" action="/products/import/upload" enctype="multipart/form-data">
      <?= Csrf::field() ?>
      <div class="field"><label for="file">Arquivo CSV (até 5 MB)</label><input type="file" id="file" name="file" accept=".csv,text/csv" required title="Selecione um arquivo CSV UTF-8 separado por ponto e vírgula."></div>
      <button type="submit" class="primary">Validar arquivo</button>
    </form>
  </div>
</div>
<?php if ($job): ?>
<div class="panel">
  <div class="panel-header">2. Resultado da validação</div>
  <div class="panel-body">
    <p><strong><?= (int) $job['total_rows'] ?></strong> linhas · <strong><?= (int) $job['valid_rows'] ?></strong> válidas · <strong><?= (int) $job['invalid_rows'] ?></strong> com erro.</p>
    <div class="table-wrap"><table>
      <thead><tr><th>Linha</th><th>Descrição</th><th>Código</th><th>Preço</th><th>Situação</th><th>Erros</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $row): $data=json_decode($row['data_json'], true); $errors=$row['errors_json'] ? json_decode($row['errors_json'], true) : []; ?>
        <tr><td><?= (int) $row['row_number'] ?></td><td><?= View::e($data['descricao'] ?? '') ?></td>
          <td><?= View::e($data['codigo_barras'] ?? '') ?></td><td><?= View::e($data['preco_venda'] ?? '') ?></td>
          <td><span class="badge <?= $row['status']==='valid' ? 'ok':'danger' ?>"><?= $row['status']==='valid' ? 'Válida':'Erro' ?></span></td>
          <td><?= View::e(implode('; ', $errors)) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php if ((int) $job['invalid_rows'] === 0 && $job['status'] === 'validated'): ?>
      <form method="post" action="/products/import/confirm" style="margin-top:12px" onsubmit="return confirm('Confirmar a gravação de <?= (int) $job['valid_rows'] ?> registros?')">
        <?= Csrf::field() ?><input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
        <button type="submit" class="primary">Confirmar importação</button>
      </form>
    <?php elseif ((int) $job['invalid_rows'] > 0): ?>
      <p class="flash erro">Corrija o arquivo e envie novamente. Nenhum produto foi gravado.</p>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

