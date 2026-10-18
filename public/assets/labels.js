(function () {
  'use strict';
  const L = window.EtqLayout;
  const R = window.EtqRender;
  const T = window.EtqTspl;
  const boot = window.LABELS_BOOTSTRAP || { products: [], csrf: '' };
  const $ = (selector) => document.querySelector(selector);
  const $$ = (selector) => Array.from(document.querySelectorAll(selector));
  const CONFIG_KEY = 'etiquetas33x21.config.v2';
  const LEGACY_KEY = 'etiquetas33x21.produtos.v1';
  const state = {
    products: boot.products.map((p) => ({ ...p, quantidade: 1, selecionado: false })),
    scope: 'selecionados',
    config: loadConfig(),
    pending: null,
    legacy: [],
    migrationPreview: null,
  };

  function loadConfig() {
    try {
      return L.sanitizarConfig(JSON.parse(localStorage.getItem(CONFIG_KEY) || '{}'));
    } catch (_) {
      return L.configPadrao();
    }
  }
  function saveConfig() {
    try { localStorage.setItem(CONFIG_KEY, JSON.stringify(state.config)); } catch (_) { /* ajustes seguem na sessão */ }
  }
  function status(message, error) {
    $('#status').textContent = message;
    $('#status').classList.toggle('erro', !!error);
  }
  function scopedProducts() {
    return state.scope === 'selecionados' ? state.products.filter((p) => p.selecionado) : state.products;
  }
  function rows() {
    return L.montarFileiras(scopedProducts(), state.config);
  }
  function post(url, payload) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': boot.csrf, Accept: 'application/json' },
      body: JSON.stringify(payload),
    }).then(async (response) => {
      if (!response.ok) throw new Error((await response.json().catch(() => null))?.message || `Erro HTTP ${response.status}`);
      return response.json();
    });
  }
  function historyPayload(method) {
    return {
      method,
      settings: state.config,
      items: scopedProducts().filter((p) => p.quantidade > 0).map((p) => ({
        productId: Number(p.id), quantity: p.quantidade,
      })),
    };
  }
  async function recordHistory(method) {
    await post('/labels/history', historyPayload(method));
  }

  function renderList() {
    const query = $('#busca-produto').value.trim().toLocaleLowerCase('pt-BR');
    const visible = state.products.filter((p) =>
      !query || [p.nome, p.codigo, p.codigoInterno].some((v) => String(v || '').toLocaleLowerCase('pt-BR').includes(query))
    );
    const body = $('#lista-produtos');
    body.textContent = '';
    visible.forEach((product) => {
      const tr = document.createElement('tr');
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.checked = product.selecionado;
      checkbox.addEventListener('change', () => {
        product.selecionado = checkbox.checked;
        renderAll();
      });
      const quantity = document.createElement('input');
      quantity.type = 'number';
      quantity.min = '1';
      quantity.max = String(L.LIMITE_QTD);
      quantity.value = String(product.quantidade);
      quantity.className = 'label-quantity';
      quantity.addEventListener('change', () => {
        product.quantidade = Math.max(1, Math.min(L.LIMITE_QTD, Math.floor(Number(quantity.value) || 1)));
        quantity.value = String(product.quantidade);
        renderPreview();
      });
      const test = document.createElement('button');
      test.type = 'button';
      test.className = 'pequeno';
      test.textContent = 'Testar 1';
      test.addEventListener('click', () => printRows(L.fileiraUnica(product, state.config.colunaTeste), [product], 'test'));
      const values = [checkbox, product.nome, L.formatarPreco(product.precoCentavos), product.codigo || '—', quantity, test];
      values.forEach((value) => {
        const td = document.createElement('td');
        if (value instanceof Node) td.appendChild(value); else td.textContent = value;
        tr.appendChild(td);
      });
      body.appendChild(tr);
    });
    $('#lista-vazia').hidden = visible.length > 0;
    const selected = state.products.filter((p) => p.selecionado);
    $('#totais').textContent = `${state.products.length} produtos no catálogo · ${selected.length} selecionados`;
    const all = $('#selecionar-todos');
    all.checked = state.products.length > 0 && selected.length === state.products.length;
    all.indeterminate = selected.length > 0 && selected.length < state.products.length;
  }

  function renderPreview() {
    const result = rows();
    const preview = $('#previa');
    preview.textContent = '';
    if (!result.totalEtiquetas) {
      preview.textContent = 'Selecione produtos para visualizar.';
      $('#resumo-previa').textContent = 'Nenhuma etiqueta selecionada.';
      return;
    }
    if (state.config.metodo === 'direto') {
      result.fileiras.slice(0, 60).forEach((row) => {
        const canvas = T.canvasPrevia(row, state.config);
        canvas.className = 'folha-bitmap';
        canvas.style.width = `${state.config.larguraPagina}mm`;
        canvas.style.height = `${L.ETIQUETA.altura}mm`;
        preview.appendChild(canvas);
      });
    } else {
      R.renderizarFolhas(preview, result.fileiras, state.config, { maxFileiras: 60 });
    }
    $('#resumo-previa').textContent = `${result.totalEtiquetas} etiquetas · ${result.fileiras.length} fileiras de 3 colunas`;
  }
  function applyMethod() {
    $$('[data-metodo]').forEach((element) => { element.hidden = element.dataset.metodo !== state.config.metodo; });
  }
  function renderAll() {
    applyMethod();
    renderList();
    renderPreview();
  }

  function prepareBrowser(rowsToPrint) {
    R.renderizarFolhas($('#area-impressao'), rowsToPrint, state.config);
    R.aplicarEstiloPagina(state.config, rowsToPrint.length);
  }
  async function printRows(rowsToPrint, products, kind) {
    try {
      if (state.config.metodo === 'direto') {
        await T.enviar(T.gerarTrabalho(rowsToPrint, state.config).bytes);
        if (kind !== 'test') await recordHistory('direto');
        status('Trabalho enviado e registrado no histórico.');
      } else {
        prepareBrowser(rowsToPrint);
        if (kind !== 'test') await recordHistory('navegador');
        window.print();
        status('Trabalho registrado; confirme a impressão na janela do navegador.');
      }
    } catch (error) {
      status(error.message || 'Falha ao imprimir.', true);
    }
    updateConnection();
  }
  function updateConnection() {
    const name = T.nomeConectado();
    $('#status-conexao').textContent = name ? `conectada (${name})` : T.usbDisponivel() ? 'não conectada' : 'WebUSB indisponível';
  }

  $$('[data-cfg]').forEach((input) => {
    const key = input.dataset.cfg;
    if (input.type === 'checkbox') input.checked = state.config[key];
    else input.value = String(state.config[key]);
    input.addEventListener('change', () => {
      state.config = L.sanitizarConfig({ ...state.config, [key]: input.type === 'checkbox' ? input.checked : input.value });
      saveConfig();
      renderAll();
    });
  });
  $$('input[name=escopo]').forEach((radio) => radio.addEventListener('change', () => {
    if (radio.checked) {
      state.scope = radio.value;
      renderPreview();
    }
  }));
  $('#busca-produto').addEventListener('input', renderList);
  $('#selecionar-todos').addEventListener('change', (event) => {
    state.products.forEach((p) => { p.selecionado = event.target.checked; });
    renderAll();
  });
  $('#botao-restaurar').addEventListener('click', () => {
    state.config = L.configPadrao();
    saveConfig();
    location.reload();
  });
  $('#botao-conectar').addEventListener('click', async () => {
    try { await T.conectar(true); status('Impressora conectada.'); } catch (error) { status(error.message, true); }
    updateConnection();
  });
  $('#botao-calibrar-sensor').addEventListener('click', async () => {
    try { await T.enviar(T.comandoCalibrar()); status('Calibração enviada.'); } catch (error) { status(error.message, true); }
  });
  $('#botao-avancar').addEventListener('click', async () => {
    try { await T.enviar(T.comandoAvancar(state.config)); status('Avanço enviado.'); } catch (error) { status(error.message, true); }
  });
  $('#botao-teste-unico').addEventListener('click', () => printRows(L.fileiraTeste([state.config.colunaTeste]), [], 'test'));
  $('#botao-teste-fileira').addEventListener('click', () => printRows(L.fileiraTeste([1, 2, 3]), [], 'test'));
  $('#botao-imprimir').addEventListener('click', () => {
    const result = rows();
    if (!result.totalEtiquetas) return status('Selecione ao menos um produto.', true);
    state.pending = result.fileiras;
    $('#dialogo-resumo').textContent = `${result.totalEtiquetas} etiquetas em ${result.fileiras.length} fileiras.`;
    $('#dialogo-impressao').showModal();
  });
  $('#dialogo-cancelar').addEventListener('click', () => $('#dialogo-impressao').close());
  $('#dialogo-confirmar').addEventListener('click', () => {
    $('#dialogo-impressao').close();
    if (state.pending) printRows(state.pending, scopedProducts(), 'normal');
    state.pending = null;
  });
  $('#botao-baixar-prn').addEventListener('click', async () => {
    const result = rows();
    if (!result.totalEtiquetas) return status('Selecione ao menos um produto.', true);
    try {
      const bytes = T.gerarTrabalho(result.fileiras, state.config).bytes;
      await recordHistory('prn');
      const url = URL.createObjectURL(new Blob([bytes], { type: 'application/octet-stream' }));
      const link = document.createElement('a');
      link.href = url; link.download = 'etiquetas.prn'; link.click();
      setTimeout(() => URL.revokeObjectURL(url), 5000);
      status('Arquivo PRN gerado e registrado no histórico.');
    } catch (error) { status(error.message, true); }
  });
  window.addEventListener('beforeprint', () => {
    if (!$('#area-impressao').children.length) prepareBrowser(rows().fileiras);
  });

  try {
    const legacy = JSON.parse(localStorage.getItem(LEGACY_KEY) || '[]');
    if (Array.isArray(legacy) && legacy.length) {
      state.legacy = legacy;
      $('#botao-migrar').hidden = false;
    }
  } catch (_) { /* legado inválido é ignorado até o usuário corrigir o armazenamento */ }
  $('#botao-migrar').addEventListener('click', async () => {
    try {
      state.migrationPreview = await post('/labels/migration/preview', { products: state.legacy });
      const p = state.migrationPreview;
      const conflicts = p.conflitos.map((c) => `<li>${escapeHtml(c.legado.nome)}: ${escapeHtml(c.motivo)}</li>`).join('');
      $('#migracao-resumo').innerHTML =
        `<p><strong>${p.novos.length}</strong> novos, <strong>${p.conflitos.length}</strong> conflitos e <strong>${p.invalidos.length}</strong> inválidos.</p>` +
        (conflicts ? `<p>Nenhum conflito será sobrescrito:</p><ul>${conflicts}</ul>` : '');
      $('#migracao-confirmar').disabled = !p.novos.length;
      $('#dialogo-migracao').showModal();
    } catch (error) { status(error.message, true); }
  });
  $('#migracao-cancelar').addEventListener('click', () => $('#dialogo-migracao').close());
  $('#migracao-confirmar').addEventListener('click', async () => {
    try {
      const indices = state.migrationPreview.novos.map((item) => item.indice);
      const result = await post('/labels/migration', { products: state.legacy, indices });
      $('#dialogo-migracao').close();
      status(`${result.importados} produtos importados. Recarregando catálogo central…`);
      setTimeout(() => location.reload(), 700);
    } catch (error) { status(error.message, true); }
  });
  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value);
    return div.innerHTML;
  }

  state.scope = 'selecionados';
  $$('input[name=escopo]').forEach((radio) => { radio.checked = radio.value === state.scope; });
  updateConnection();
  renderAll();
})();
