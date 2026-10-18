<?php

use App\Core\View;
?>
<script>
window.LABELS_BOOTSTRAP = <?= json_encode(
    ['products' => $products, 'csrf' => $csrf],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;
</script>
<main class="conteudo labels-content">
  <div class="coluna">
    <section class="cartao">
      <h2>Produtos do cadastro central</h2>
      <p class="dica">A lista é somente leitura e vem diretamente do banco SQL. Quantidades e seleção valem apenas para este trabalho.</p>
      <div class="barra-lista">
        <input id="busca-produto" type="search" placeholder="Buscar por nome, código interno ou barras">
        <div class="escopo">
          <label><input type="radio" name="escopo" value="todos" checked> Imprimir todos</label>
          <label><input type="radio" name="escopo" value="selecionados"> Somente selecionados</label>
        </div>
      </div>
      <div class="tabela-envoltorio">
        <table>
          <thead><tr><th><input type="checkbox" id="selecionar-todos" aria-label="Selecionar todos"></th><th>Produto</th><th>Preço</th><th>Código</th><th>Qtd.</th><th>Ação</th></tr></thead>
          <tbody id="lista-produtos"></tbody>
        </table>
        <div id="lista-vazia" class="vazio-lista">Nenhum produto ativo no cadastro central.</div>
      </div>
      <div id="totais" class="totais"></div>
      <div class="botoes">
        <button type="button" class="destaque" id="botao-imprimir">Imprimir etiquetas</button>
        <button type="button" id="botao-migrar" hidden>Revisar produtos do app antigo</button>
      </div>
      <div id="status" class="status" role="status" aria-live="polite"></div>
    </section>

    <section class="cartao">
      <h2>Impressora</h2>
      <div class="campo"><label for="cfg-metodo">Método</label>
        <select id="cfg-metodo" data-cfg="metodo"><option value="direto">Direto via USB</option><option value="navegador">Janela do navegador</option></select>
      </div>
      <div data-metodo="direto">
        <p>Status: <strong id="status-conexao">não conectada</strong></p>
        <div class="botoes">
          <button type="button" id="botao-conectar">Conectar impressora USB</button>
          <button type="button" id="botao-calibrar-sensor">Calibrar sensor</button>
          <button type="button" id="botao-avancar">Avançar 1 fileira</button>
          <button type="button" id="botao-baixar-prn">Baixar .prn</button>
        </div>
      </div>
    </section>

    <section class="cartao">
      <h2>Ajustes de impressão <button type="button" class="pequeno" id="botao-restaurar">Restaurar padrões</button></h2>
      <div class="grade-config">
        <div class="campo"><label>Largura da página (mm)</label><input type="number" data-cfg="larguraPagina"></div>
        <div class="campo"><label>Espaço horizontal (mm)</label><input type="number" data-cfg="espacoHorizontal"></div>
        <div class="campo"><label>Espaço vertical (mm)</label><input type="number" data-cfg="espacoVertical"></div>
        <div class="campo"><label>Deslocamento X (mm)</label><input type="number" data-cfg="deslocX"></div>
        <div class="campo"><label>Deslocamento Y (mm)</label><input type="number" data-cfg="deslocY"></div>
        <div class="campo"><label>Margem interna (mm)</label><input type="number" data-cfg="margemInterna"></div>
        <div class="campo"><label>Fonte nome (pt)</label><input type="number" data-cfg="fonteNome"></div>
        <div class="campo"><label>Fonte preço (pt)</label><input type="number" data-cfg="fontePreco"></div>
        <div class="campo"><label>Altura código (mm)</label><input type="number" data-cfg="alturaCodigo"></div>
        <div class="campo"><label>Largura código (%)</label><input type="number" data-cfg="larguraCodigo"></div>
        <div class="campo"><label>Velocidade TSPL</label><input type="number" data-cfg="tsplVelocidade"></div>
        <div class="campo"><label>Escurecimento TSPL</label><input type="number" data-cfg="tsplDensidade"></div>
        <div class="campo"><label>Coluna de teste</label><select data-cfg="colunaTeste"><option value="1">1</option><option value="2">2</option><option value="3">3</option></select></div>
        <div class="campo" data-metodo="navegador"><label>Modo de página</label><select data-cfg="modoPagina"><option value="fileira">Uma página por fileira</option><option value="continuo">Página contínua</option></select></div>
      </div>
      <label><input type="checkbox" data-cfg="mostrarDigitos"> Mostrar números do código</label>
      <label><input type="checkbox" data-cfg="novaFileiraPorProduto"> Nova fileira por produto</label>
      <label><input type="checkbox" data-cfg="tsplGirar"> Girar 180°</label>
      <label><input type="checkbox" data-cfg="tsplInverter"> Inverter cores</label>
      <div class="botoes">
        <button type="button" id="botao-teste-unico">Teste 1 etiqueta</button>
        <button type="button" id="botao-teste-fileira">Teste 3 etiquetas</button>
      </div>
    </section>
  </div>

  <div class="coluna">
    <section class="cartao">
      <h2>Prévia da impressão</h2>
      <div id="resumo-previa" class="resumo-previa"></div>
      <div class="palco-previa"><div id="previa" class="escala-previa"></div></div>
    </section>
  </div>
</main>

<dialog id="dialogo-impressao"><h2>Confirmar impressão</h2><p id="dialogo-resumo"></p>
  <div class="dialogo-rodape"><button id="dialogo-cancelar">Cancelar</button><button class="destaque" id="dialogo-confirmar">Imprimir</button></div>
</dialog>
<dialog id="dialogo-migracao"><h2>Migração opcional do app antigo</h2><div id="migracao-resumo"></div>
  <div class="dialogo-rodape"><button id="migracao-cancelar">Cancelar</button><button class="destaque" id="migracao-confirmar">Importar somente novos</button></div>
</dialog>
<div id="area-impressao" aria-hidden="true"></div>
