<?php

use App\Core\View;
?>
<div class="page-header"><h1>Procedimentos da SmartPlanet</h1></div>
<div class="panel"><div class="panel-body">
  <p>Cada menu tem um jeito certo de ser usado. O funcionário segue o procedimento do que o perfil dele libera. O dono da loja é quem configura o login em Funcionários.</p>
  <p class="muted">Recibo do PDV não é nota fiscal. O código impresso na etiqueta precisa ser o mesmo código do produto no cadastro.</p>
</div></div>
<?php foreach ($procedures as $procedure): ?>
  <section class="panel" id="<?= View::e($procedure['id']) ?>">
    <div class="panel-header"><?= View::e($procedure['menu']) ?></div>
    <div class="panel-body">
      <p><?= View::e($procedure['objetivo']) ?></p>
      <ol>
        <?php foreach ($procedure['passos'] as $step): ?>
          <li><?= View::e($step) ?></li>
        <?php endforeach; ?>
      </ol>
      <p><strong>Regra:</strong> <?= View::e($procedure['regra']) ?></p>
    </div>
  </section>
<?php endforeach; ?>
