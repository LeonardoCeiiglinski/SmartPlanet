<?php

use App\Core\View;
?>
<div class="page-header"><h1>Funcionários</h1><a class="button primary" href="/users/new">Novo funcionário</a></div>
<div class="panel"><div class="panel-body">
  <p>Somente o administrador master, dono da SmartPlanet, cria e altera o login de quem trabalha na loja. O funcionário não escolhe o próprio acesso: recebe e-mail, senha inicial e perfil definidos aqui.</p>
  <p class="muted">Gerente acompanha a loja, menos usuários e configurações. Operador de caixa vende, consulta e opera o caixa. O dono permanece o único administrador master.</p>
</div></div>
<div class="panel"><div class="panel-body table-wrap">
  <table>
    <thead><tr><th>Nome</th><th>E-mail de login</th><th>Perfil</th><th>Filial</th><th>Último acesso</th><th>Situação</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($users as $item): ?>
        <tr class="<?= $item['active'] ? '' : 'inactive' ?>">
          <td><?= View::e($item['name']) ?><?php if ((int) ($item['is_master'] ?? 0) === 1): ?> <span class="badge ok">Dono da loja</span><?php endif; ?></td>
          <td><?= View::e($item['email']) ?></td>
          <td><?= View::e($item['role_name']) ?></td><td><?= View::e($item['branch_name']) ?></td>
          <td><?= $item['last_login_at'] ? View::e(date('d/m/Y H:i', strtotime($item['last_login_at']))) : '—' ?></td>
          <td><span class="badge <?= $item['active'] ? 'ok' : 'danger' ?>"><?= $item['active'] ? 'Ativo' : 'Inativo' ?></span></td>
          <td><a class="button small" href="/users/edit?id=<?= (int) $item['id'] ?>">Editar</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div></div>
