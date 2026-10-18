<?php

use App\Core\View;

$catalogJson = [];
foreach ($catalog as $item) {
    $catalogJson[] = [
        'id' => (int) $item['id'],
        'nome' => (string) $item['description'],
        'preco' => number_format((float) $item['sale_price'], 2, ',', '.'),
        'precoCentavos' => (int) round(((float) $item['sale_price']) * 100),
        'codigo' => (string) ($item['barcode'] ?? ''),
        'label' => trim($item['description'] . ' · ' . ($item['barcode'] ?: 'sem código')),
    ];
}

$headExtra = <<<HTML
  <link rel="stylesheet" href="/legacy/css/styles.css">
  <link rel="stylesheet" href="/assets/labels-embed.css">
  <style id="estilo-pagina">@page { size: 104mm 21mm; margin: 0; }</style>
HTML;

$vPage = (string) (@filemtime(BASE_PATH . '/public/assets/labels-page.js') ?: time());
$vApp = (string) (@filemtime(BASE_PATH . '/js/app.js') ?: time());
$bodyEnd = <<<HTML
<div id="area-impressao" aria-hidden="true"></div>
<script src="/legacy/js/layout.js"></script>
<script src="/legacy/js/barcode.js"></script>
<script src="/legacy/js/render.js"></script>
<script src="/legacy/js/tspl.js"></script>
<script src="/assets/labels-page.js?v={$vPage}"></script>
<script src="/legacy/js/app.js?v={$vApp}"></script>
HTML;
?>
<script>
window.LABELS_CATALOG = <?= json_encode($catalogJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.LABELS_PREFILL = <?= json_encode($prefill, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>

<div class="labels-page">
  <div class="page-header">
    <div>
      <h1>Etiquetas</h1>
      <p class="muted">33 × 21 mm · 3 colunas · impressora 4B-2054N</p>
    </div>
  </div>

  <div id="aviso-armazenamento" class="aviso-armazenamento" hidden>
    Não foi possível salvar no armazenamento do navegador. Os produtos ficarão disponíveis apenas enquanto esta página estiver aberta.
  </div>

  <div class="conteudo">
    <div class="coluna">
      <section class="cartao" aria-labelledby="titulo-montar">
        <h2 id="titulo-montar">Montar etiquetas</h2>
        <?php if ($catalogJson === []): ?>
          <p class="dica">Nenhum produto cadastrado. <a href="/products">Cadastre o produto primeiro</a> para gerar etiquetas.</p>
        <?php else: ?>
          <div class="campo">
            <label for="busca-catalogo">Produto cadastrado</label>
            <input type="search" id="busca-catalogo" list="lista-catalogo" placeholder="Digite para pesquisar o produto cadastrado..." autocomplete="off">
            <datalist id="lista-catalogo">
              <?php foreach ($catalogJson as $item): ?>
                <option value="<?= View::e($item['label']) ?>"></option>
              <?php endforeach; ?>
            </datalist>
            <span class="dica">A etiqueta só é gerada para produto já cadastrado em <a href="/products">Produtos e serviços</a>.</span>
          </div>
          <div id="resumo-catalogo" class="resumo-catalogo" hidden></div>
          <div class="linha-campos">
            <div class="campo">
              <label for="quantidade-nova">Quantidade de etiquetas</label>
              <input type="number" id="quantidade-nova" min="1" max="9999" step="1" value="9" placeholder="Ex.: 9">
            </div>
          </div>
          <div class="botoes">
            <button type="button" class="primario" id="botao-adicionar-catalogo">Adicionar 9 à impressão</button>
          </div>
        <?php endif; ?>
        <div class="status" id="status" role="status" aria-live="polite"></div>
      </section>

      <section class="cartao" aria-labelledby="titulo-lista">
        <h2 id="titulo-lista">Fila de impressão <button type="button" class="destaque" id="botao-imprimir">Imprimir etiquetas</button></h2>
        <div class="barra-lista">
          <div class="escopo" role="radiogroup" aria-label="O que imprimir">
            <label><input type="radio" name="escopo" value="todos"> Todas</label>
            <label><input type="radio" name="escopo" value="selecionados"> Somente selecionadas</label>
          </div>
          <button type="button" class="pequeno perigo" id="botao-remover-todos">Limpar fila</button>
        </div>
        <div class="tabela-envoltorio">
          <table>
            <thead>
              <tr>
                <th scope="col"><input type="checkbox" id="selecionar-todos" aria-label="Selecionar todos"></th>
                <th scope="col">Produto</th>
                <th scope="col" class="num">Preço</th>
                <th scope="col">Código</th>
                <th scope="col" class="num">Qtd.</th>
                <th scope="col" class="num">Ações</th>
              </tr>
            </thead>
            <tbody id="lista-produtos"></tbody>
          </table>
          <div id="lista-vazia" class="vazio-lista">Nenhum produto na fila. Busque um produto já cadastrado para gerar as etiquetas.</div>
        </div>
        <div class="totais" id="totais"></div>
      </section>

      <details class="cartao">
        <summary>Impressora e teste</summary>
        <div class="campo">
          <label for="cfg-metodo">Método de impressão</label>
          <select id="cfg-metodo" data-cfg="metodo">
            <option value="direto">Direto na impressora via USB (recomendado)</option>
            <option value="navegador">Pela janela de impressão do navegador</option>
          </select>
        </div>
        <div data-metodo="direto">
          <p class="dica">Envia a etiqueta em comandos TSPL para a 4B-2054N, no tamanho 33 × 21 mm. Use Chrome ou Edge.</p>
          <p class="conexao">Status: <strong id="status-conexao">não conectada</strong></p>
          <div class="botoes">
            <button type="button" class="primario" id="botao-conectar">Conectar impressora USB</button>
            <button type="button" id="botao-calibrar-sensor">Calibrar sensor</button>
            <button type="button" id="botao-avancar">Avançar 1 fileira</button>
            <button type="button" id="botao-baixar-prn">Baixar arquivo .prn</button>
          </div>
          <div class="grade-config" style="margin-top:12px">
            <div class="campo"><label for="cfg-tsplVelocidade">Velocidade (1–6)</label><input type="number" id="cfg-tsplVelocidade" data-cfg="tsplVelocidade" step="1" placeholder="Ex.: 4"></div>
            <div class="campo"><label for="cfg-tsplDensidade">Escurecimento (0–15)</label><input type="number" id="cfg-tsplDensidade" data-cfg="tsplDensidade" step="1" placeholder="Ex.: 8"></div>
          </div>
          <label class="caixa-marcar"><input type="checkbox" id="cfg-tsplGirar" data-cfg="tsplGirar"> Girar 180°</label>
          <label class="caixa-marcar"><input type="checkbox" id="cfg-tsplInverter" data-cfg="tsplInverter"> Inverter cores</label>
        </div>
        <div data-metodo="navegador">
          <p class="dica">Usa a janela de impressão do sistema. Configure no driver um papel do tamanho indicado em “Ajustes avançados”, com escala 100% e sem margens.</p>
        </div>
        <div class="instrucoes">
          <ol>
            <li data-metodo="direto">Conecte a impressora e calibre o sensor uma vez por bobina.</li>
            <li>Imprima uma etiqueta de teste e confira o contorno de 33 × 21 mm.</li>
            <li>Se estiver deslocada, ajuste o deslocamento em “Ajustes avançados”.</li>
          </ol>
        </div>
        <div class="linha-campos">
          <div class="campo">
            <label for="cfg-colunaTeste">Coluna da etiqueta de teste</label>
            <select id="cfg-colunaTeste" data-cfg="colunaTeste">
              <option value="1">Coluna 1 (esquerda)</option>
              <option value="2">Coluna 2 (centro)</option>
              <option value="3">Coluna 3 (direita)</option>
            </select>
          </div>
        </div>
        <div class="botoes">
          <button type="button" id="botao-teste-unico">Imprimir 1 etiqueta de teste</button>
          <button type="button" id="botao-teste-fileira">Imprimir fileira de teste (3)</button>
        </div>
      </details>

      <details class="cartao">
        <summary>Ajustes avançados de impressão</summary>
        <div class="botoes" style="margin-bottom:10px">
          <button type="button" class="pequeno" id="botao-restaurar">Restaurar padrões</button>
        </div>
        <h3>Papel e posição</h3>
        <div class="grade-config">
          <div class="campo inteira" data-metodo="navegador">
            <label for="cfg-modoPagina">Modo de página</label>
            <select id="cfg-modoPagina" data-cfg="modoPagina">
              <option value="fileira">Uma página por fileira (recomendado)</option>
              <option value="continuo">Página contínua</option>
            </select>
          </div>
          <div class="campo inteira"><label for="cfg-larguraPagina">Largura da página (mm)</label><input type="number" id="cfg-larguraPagina" data-cfg="larguraPagina" step="0.5" placeholder="Ex.: 104"><span class="dica">4B-2054N: 104 mm.</span></div>
          <div class="campo"><label for="cfg-espacoHorizontal">Espaço entre colunas (mm)</label><input type="number" id="cfg-espacoHorizontal" data-cfg="espacoHorizontal" step="0.1" placeholder="Ex.: 3"></div>
          <div class="campo"><label for="cfg-espacoVertical">Espaço entre fileiras (mm)</label><input type="number" id="cfg-espacoVertical" data-cfg="espacoVertical" step="0.1" placeholder="Ex.: 3"></div>
          <div class="campo"><label for="cfg-deslocX">Deslocamento horizontal (mm)</label><input type="number" id="cfg-deslocX" data-cfg="deslocX" step="0.1" placeholder="Ex.: 0"></div>
          <div class="campo"><label for="cfg-deslocY">Deslocamento vertical (mm)</label><input type="number" id="cfg-deslocY" data-cfg="deslocY" step="0.1" placeholder="Ex.: 0"></div>
        </div>
        <div class="grade-config" data-metodo="navegador">
          <div class="campo"><label for="cfg-margemSuperior">Margem superior (mm)</label><input type="number" id="cfg-margemSuperior" data-cfg="margemSuperior" step="0.1" placeholder="Ex.: 0"></div>
          <div class="campo"><label for="cfg-margemInferior">Margem inferior (mm)</label><input type="number" id="cfg-margemInferior" data-cfg="margemInferior" step="0.1" placeholder="Ex.: 0"></div>
        </div>
        <label class="caixa-marcar"><input type="checkbox" id="cfg-novaFileiraPorProduto" data-cfg="novaFileiraPorProduto"> Começar cada produto em uma nova fileira</label>
        <div class="papel-calculado" aria-live="polite" data-metodo="navegador">
          Papel a configurar no driver: <strong id="papel-tamanho">—</strong>
          <span id="papel-detalhe" class="dica"></span>
          <span id="papel-alerta" class="alerta-largura"></span>
        </div>
        <h3>Conteúdo da etiqueta</h3>
        <div class="grade-config">
          <div class="campo"><label for="cfg-fonteNome">Tamanho máx. do nome (pt)</label><input type="number" id="cfg-fonteNome" data-cfg="fonteNome" step="0.5" placeholder="Ex.: 7"></div>
          <div class="campo"><label for="cfg-fontePreco">Tamanho do preço (pt)</label><input type="number" id="cfg-fontePreco" data-cfg="fontePreco" step="0.5" placeholder="Ex.: 8"></div>
          <div class="campo"><label for="cfg-alturaCodigo">Altura do código de barras (mm)</label><input type="number" id="cfg-alturaCodigo" data-cfg="alturaCodigo" step="0.5" placeholder="Ex.: 8"></div>
          <div class="campo"><label for="cfg-larguraCodigo">Largura máx. do código (%)</label><input type="number" id="cfg-larguraCodigo" data-cfg="larguraCodigo" step="5" placeholder="Ex.: 90"></div>
          <div class="campo"><label for="cfg-margemInterna">Margem interna (mm)</label><input type="number" id="cfg-margemInterna" data-cfg="margemInterna" step="0.1" placeholder="Ex.: 1"></div>
          <div class="campo">
            <label for="cfg-alinhamentoTexto">Alinhamento do texto</label>
            <select id="cfg-alinhamentoTexto" data-cfg="alinhamentoTexto">
              <option value="center">Centralizado</option>
              <option value="left">À esquerda</option>
            </select>
          </div>
        </div>
        <label class="caixa-marcar"><input type="checkbox" id="cfg-mostrarDigitos" data-cfg="mostrarDigitos"> Mostrar os números abaixo do código de barras</label>
      </details>

      <details class="cartao">
        <summary>Leitor nesta tela (opcional)</summary>
        <p class="dica">O PDV já registra a venda ao bipar. Use esta área só se quiser conferir a etiqueta nesta página.</p>
        <div class="campo">
          <label for="campo-leitor">Bipe a etiqueta</label>
          <input type="text" id="campo-leitor" autocomplete="off" spellcheck="false" placeholder="Aguardando a máquina de bipar…" inputmode="none">
        </div>
        <div id="resultado-leitura" class="resultado-leitura" aria-live="assertive">Bipe uma etiqueta para conferir o produto.</div>
        <div class="barra-lista">
          <div class="totais" id="totais-leituras"></div>
          <button type="button" class="pequeno perigo" id="botao-limpar-leituras">Limpar registros de hoje</button>
        </div>
        <div class="tabela-envoltorio">
          <table>
            <thead>
              <tr>
                <th scope="col">Horário</th>
                <th scope="col">Produto</th>
                <th scope="col" class="num">Preço</th>
                <th scope="col">Código</th>
              </tr>
            </thead>
            <tbody id="lista-leituras"></tbody>
          </table>
          <div id="leituras-vazias" class="vazio-lista">Nenhuma leitura registrada hoje.</div>
        </div>
      </details>
    </div>

    <div class="coluna labels-previa-coluna">
      <section class="cartao" aria-labelledby="titulo-previa">
        <h2 id="titulo-previa">Prévia da impressão</h2>
        <div class="barra-previa">
          <div class="resumo-previa" id="resumo-previa"></div>
          <label class="zoom">Zoom
            <select id="cfg-zoom" data-cfg="zoom">
              <option value="1">100%</option>
              <option value="1.5">150%</option>
              <option value="2">200%</option>
              <option value="3">300%</option>
              <option value="4">400%</option>
            </select>
          </label>
        </div>
        <div class="palco-previa">
          <div class="escala-previa" id="previa"></div>
        </div>
        <p class="nota-previa" id="nota-previa"></p>
        <div class="alerta" data-metodo="direto">
          <p>A prévia mostra o bitmap enviado à impressora. O contorno azul não é impresso.</p>
        </div>
        <div class="alerta" data-metodo="navegador">
          <p>Na janela de impressão: escala 100%, margens nenhuma, sem cabeçalhos nem rodapés.</p>
        </div>
      </section>
    </div>
  </div>
</div>

<dialog id="dialogo-etiqueta" aria-labelledby="titulo-form">
  <form id="form-produto" novalidate autocomplete="off">
    <div class="dialogo-corpo">
      <h2 id="titulo-form">Editar etiqueta</h2>
      <p class="dica">Nome, preço e código vêm do cadastro do produto. Aqui você ajusta a quantidade desta impressão.</p>
      <div class="campo">
        <label for="campo-nome">Nome do produto</label>
        <input type="text" id="campo-nome" maxlength="120" required readonly placeholder="Ex.: Película de vidro 9H">
        <span class="erro" data-erro="nome"></span>
      </div>
      <div class="linha-campos">
        <div class="campo">
          <label for="campo-preco">Preço</label>
          <div class="prefixo"><span>R$</span><input type="text" id="campo-preco" inputmode="decimal" data-mask="money" placeholder="Ex.: 49,90" required readonly></div>
          <span class="erro" data-erro="preco"></span>
        </div>
        <div class="campo">
          <label for="campo-quantidade">Quantidade</label>
          <input type="number" id="campo-quantidade" min="1" max="9999" step="1" inputmode="numeric" required placeholder="Ex.: 9">
          <span class="erro" data-erro="quantidade"></span>
        </div>
      </div>
      <div class="campo">
        <label for="campo-codigo">Código de barras</label>
        <input type="text" id="campo-codigo" maxlength="24" inputmode="text" spellcheck="false" readonly placeholder="Ex.: 7891234567895">
        <span class="dica" id="dica-codigo">EAN-13 e UPC-A são reconhecidos automaticamente; outros códigos usam Code 128.</span>
        <span class="erro" data-erro="codigo"></span>
      </div>
    </div>
    <div class="dialogo-rodape">
      <button type="button" id="botao-limpar">Cancelar</button>
      <button type="submit" class="primario" id="botao-adicionar">Salvar alterações</button>
    </div>
  </form>
</dialog>

<dialog id="dialogo-impressao" aria-labelledby="dialogo-titulo">
  <div class="dialogo-corpo">
    <h2 id="dialogo-titulo">Confirmar impressão</h2>
    <p class="resumo" id="dialogo-resumo"></p>
    <div class="alerta" data-metodo="direto">
      <p><strong>Impressão direta via USB.</strong> O trabalho vai para a impressora com SIZE <span id="dialogo-size"></span>.</p>
    </div>
    <div class="alerta" data-metodo="navegador">
      <p>Confira o papel do driver: <strong id="dialogo-papel"></strong>. Escala 100%, margens nenhuma.</p>
    </div>
  </div>
  <div class="dialogo-rodape">
    <button type="button" id="dialogo-cancelar">Cancelar</button>
    <button type="button" class="destaque" id="dialogo-confirmar">Imprimir</button>
  </div>
</dialog>
