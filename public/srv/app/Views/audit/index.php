<?php

use App\Core\View;
?>
<div class="page-header"><h1>Auditoria</h1></div>
<div class="panel"><div class="panel-body table-wrap">
  <table>
    <thead><tr><th>Data</th><th>Usuário</th><th>Ação</th><th>Entidade</th><th>ID</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $log): ?>
      <tr><td><?= View::e(date('d/m/Y H:i:s', strtotime($log['created_at']))) ?></td>
        <td><?= View::e($log['user_name'] ?: 'Sistema') ?></td><td><?= View::e($log['action']) ?></td>
        <td><?= View::e($log['entity_type']) ?></td><td><?= View::e($log['entity_id'] ?? '—') ?></td><td><?= View::e($log['ip_address'] ?? '—') ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div></div>

