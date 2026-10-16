(function () {
  'use strict';

  const L = window.EtqLayout;
  const B = window.EtqBarcode;
  const R = window.EtqRender;

  const CHAVES = {
    produtos: 'etiquetas33x21.produtos.v1',
    config: 'etiquetas33x21.config.v1',
    escopo: 'etiquetas33x21.escopo.v1',
  };
  const MAX_FILEIRAS_PREVIA = 60;

  const $ = (sel) => document.querySelector(sel);
  const $$ = (sel) => Array.from(document.querySelectorAll(sel));

  /* ---------- Armazenamento local ---------- */

  function avisarArmazenamento() {
    $('#aviso-armazenamento').hidden = false;
  }

  function lerArmazenado(chave, padrao) {
    try {
      const v = localStorage.getItem(chave);
      return v == null ? padrao : JSON.parse(v);
    } catch (e) {
      avisarArmazenamento();
      return padrao;
    }
  }

  function gravarArmazenado(chave, valor) {
    try {
      localStorage.setItem(chave, JSON.stringify(valor));
    } catch (e) {
      avisarArmazenamento();
    }
  }

  function novoId() {
    return Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
  }

  function sanitizarProduto(p) {
    if (!p || typeof p !== 'object') return null;
    const nome = String(p.nome || '').trim().slice(0, L.LIMITE_NOME);
    const preco = Number(p.precoCentavos);
    const qtd = Math.floor(Number(p.quantidade));
    if (!nome || !Number.isInteger(preco) || preco < 0 || !(qtd >= 1)) return null;
    const codigo = String(p.codigo || '').trim();
    return {
      id: typeof p.id === 'string' && p.id ? p.id : novoId(),
      nome,
      precoCentavos: preco,
      codigo: B.validar(codigo).ok ? codigo : '',
      quantidade: Math.min(qtd, L.LIMITE_QTD),
      selecionado: p.selecionado !== false,
    };
  }

  const produtosSalvos = lerArmazenado(CHAVES.produtos, []);
  const estado = {
    produtos: Array.isArray(produtosSalvos) ? produtosSalvos.map(sanitizarProduto).filter(Boolean) : [],
    config: L.sanitizarConfig(lerArmazenado(CHAVES.config, {})),
    escopo: lerArmazenado(CHAVES.escopo, 'todos') === 'selecionados' ? 'selecionados' : 'todos',
    editandoId: null,
    impressaoPreparada: false,
    fileirasPendentes: null,
  };

  const salvarProdutos = () => gravarArmazenado(CHAVES.produtos, estado.produtos);
  const salvarConfig = () => gravarArmazenado(CHAVES.config, estado.config);

  /* ---------- Utilidades ---------- */

  let temporizadorStatus = null;
  function status(msg, erro = false) {
    const el = $('#status');
    el.textContent = msg;
    el.classList.toggle('erro', erro);
    clearTimeout(temporizadorStatus);
    temporizadorStatus = setTimeout(() => (el.textContent = ''), 5000);
  }

  const plural = (n, um, varios) => `${n.toLocaleString('pt-BR')} ${n === 1 ? um : varios}`;
  const fmtMm = (v) => v.toLocaleString('pt-BR', { maximumFractionDigits: 2 });

  function el(tag, classe, texto) {
    const e = document.createElement(tag);
    if (classe) e.className = classe;
    if (texto != null) e.textContent = texto;
    return e;
  }

  function produtosDoEscopo() {
    return estado.escopo === 'selecionados' ? estado.produtos.filter((p) => p.selecionado) : estado.produtos;
  }

  /* ---------- Formulário ---------- */

  const form = $('#form-produto');
  const campoNome = $('#campo-nome');
  const campoPreco = $('#campo-preco');
  const campoCodigo = $('#campo-codigo');
  const campoQtd = $('#campo-quantidade');
  const camposPorNome = { nome: campoNome, preco: campoPreco, codigo: campoCodigo, quantidade: campoQtd };

  function mostrarErro(campo, msg) {
    $(`[data-erro="${campo}"]`).textContent = msg || '';
    camposPorNome[campo].setAttribute('aria-invalid', msg ? 'true' : 'false');
  }

  function atualizarDicaCodigo() {
    const v = B.validar(campoCodigo.value);
    const dica = $('#dica-codigo');
    if (!v.ok) {
      dica.textContent = '';
      mostrarErro('codigo', v.erro);
      return;
    }
    mostrarErro('codigo', '');
    if (v.vazio) dica.textContent = 'Sem código: a etiqueta terá apenas nome e preço.';
    else dica.textContent = `Será impresso como ${v.tipo}.` + (v.aviso ? ' ' + v.aviso : '');
  }

  function validarFormulario() {
    let ok = true;
    const nome = campoNome.value.trim();
    if (!nome) {
      mostrarErro('nome', 'Informe o nome do produto.');
      ok = false;
    } else mostrarErro('nome', '');

    const preco = L.parsePreco(campoPreco.value);
    if (preco == null) {
      mostrarErro('preco', 'Informe um preço válido, por exemplo 12,90.');
      ok = false;
    } else mostrarErro('preco', '');

    const textoQtd = String(campoQtd.value).trim();
    const qtd = /^\d+$/.test(textoQtd) ? parseInt(textoQtd, 10) : NaN;
    if (!(qtd >= 1 && qtd <= L.LIMITE_QTD)) {
      mostrarErro('quantidade', `Informe um número inteiro de 1 a ${L.LIMITE_QTD.toLocaleString('pt-BR')}.`);
      ok = false;
    } else mostrarErro('quantidade', '');

    const codigo = campoCodigo.value.trim();
    const vc = B.validar(codigo);
    if (!vc.ok) {
      mostrarErro('codigo', vc.erro);
      ok = false;
    } else mostrarErro('codigo', '');

    return ok ? { nome, precoCentavos: preco, codigo, quantidade: qtd } : null;
  }

  function limparFormulario() {
    form.reset();
    estado.editandoId = null;
    Object.keys(camposPorNome).forEach((c) => mostrarErro(c, ''));
    $('#botao-adicionar').textContent = 'Adicionar produto';
    $('#titulo-form').textContent = 'Cadastrar produto';
    atualizarDicaCodigo();
    renderizarLista();
  }

  function editarProduto(id) {
    const p = estado.produtos.find((x) => x.id === id);
    if (!p) return;
    estado.editandoId = id;
    campoNome.value = p.nome;
    campoPreco.value = L.formatarValor(p.precoCentavos);
    campoCodigo.value = p.codigo;
    campoQtd.value = String(p.quantidade);
    Object.keys(camposPorNome).forEach((c) => mostrarErro(c, ''));
    atualizarDicaCodigo();
    $('#botao-adicionar').textContent = 'Salvar alterações';
    $('#titulo-form').textContent = 'Editar produto';
    renderizarLista();
    campoNome.focus();
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const dados = validarFormulario();
    if (!dados) {
      status('Corrija os campos destacados.', true);
      return;
    }
    if (estado.editandoId) {
      const p = estado.produtos.find((x) => x.id === estado.editandoId);
      if (p) Object.assign(p, dados);
      status(`Produto “${dados.nome}” atualizado.`);
    } else {
      estado.produtos.push({ id: novoId(), selecionado: true, ...dados });
      status(`Produto “${dados.nome}” adicionado com ${plural(dados.quantidade, 'etiqueta', 'etiquetas')}.`);
    }
    salvarProdutos();
    limparFormulario();
    renderizarTudo();
    campoNome.focus();
  });

  $('#botao-limpar').addEventListener('click', () => {
    const estavaEditando = !!estado.editandoId;
    limparFormulario();
    status(estavaEditando ? 'Edição cancelada.' : 'Formulário limpo.');
    campoNome.focus();
  });

  campoPreco.addEventListener('blur', () => {
    const c = L.parsePreco(campoPreco.value);
    if (c != null) campoPreco.value = L.formatarValor(c);
  });
  campoCodigo.addEventListener('input', atualizarDicaCodigo);

  /* ---------- Lista de produtos ---------- */

  function selosDoProduto(p) {
    const selos = [];
    const ajuste = R.medirAjuste(p, estado.config);
    if (ajuste.semEspaco) selos.push(['Sem espaço para o nome: reduza o preço ou o código', true]);
    else if (ajuste.cortado) selos.push(['Nome longo: será cortado', true]);
    else if (ajuste.reduzido) selos.push([`Nome reduzido para ${ajuste.nomePt} pt`, false]);
    if (ajuste.precoPt < estado.config.fontePreco) selos.push([`Preço reduzido para ${ajuste.precoPt} pt`, false]);
    if (ajuste.geo) {
      selos.push([ajuste.geo.enc.tipo, false]);
      if (ajuste.geo.denso) selos.push(['Código longo: barras finas, pode não ser lido', true]);
    }
    return selos;
  }

  function renderizarLista() {
    const corpo = $('#lista-produtos');
    corpo.textContent = '';
    $('#lista-vazia').hidden = estado.produtos.length > 0;

    for (const p of estado.produtos) {
      const tr = el('tr');
      if (p.id === estado.editandoId) tr.className = 'editando';

      const tdSel = el('td');
      const cb = el('input');
      cb.type = 'checkbox';
      cb.checked = p.selecionado;
      cb.setAttribute('aria-label', `Selecionar ${p.nome}`);
      cb.addEventListener('change', () => {
        p.selecionado = cb.checked;
        salvarProdutos();
        atualizarSelecionarTodos();
        renderizarTotais();
        renderizarPrevia();
      });
      tdSel.appendChild(cb);

      const tdNome = el('td');
      tdNome.appendChild(el('div', 'nome-produto', p.nome));
      for (const [texto, atencao] of selosDoProduto(p)) tdNome.appendChild(el('span', atencao ? 'selo atencao' : 'selo', texto));

      const tdPreco = el('td', 'num', L.formatarPreco(p.precoCentavos));
      const tdCodigo = el('td', 'codigo-produto', p.codigo || '—');
      const tdQtd = el('td', 'num', p.quantidade.toLocaleString('pt-BR'));

      const tdAcoes = el('td', 'acoes');
      const bEditar = el('button', 'pequeno', 'Editar');
      bEditar.type = 'button';
      bEditar.addEventListener('click', () => editarProduto(p.id));
      const bTestar = el('button', 'pequeno', 'Testar 1');
      bTestar.type = 'button';
      bTestar.title = 'Imprimir uma única etiqueta deste produto na coluna escolhida em “Teste e calibração”';
      bTestar.addEventListener('click', () => {
        imprimirFileiras(L.fileiraUnica(p, estado.config.colunaTeste));
      });
      const bExcluir = el('button', 'pequeno perigo', 'Excluir');
      bExcluir.type = 'button';
      bExcluir.addEventListener('click', () => {
        if (!confirm(`Excluir “${p.nome}”?`)) return;
        estado.produtos = estado.produtos.filter((x) => x.id !== p.id);
        if (estado.editandoId === p.id) limparFormulario();
        salvarProdutos();
        renderizarTudo();
        status(`Produto “${p.nome}” excluído.`);
      });
      tdAcoes.append(bEditar, bTestar, bExcluir);

      tr.append(tdSel, tdNome, tdPreco, tdCodigo, tdQtd, tdAcoes);
      corpo.appendChild(tr);
    }
    atualizarSelecionarTodos();
    renderizarTotais();
  }

  function atualizarSelecionarTodos() {
    const cb = $('#selecionar-todos');
    const sel = estado.produtos.filter((p) => p.selecionado).length;
    cb.checked = estado.produtos.length > 0 && sel === estado.produtos.length;
    cb.indeterminate = sel > 0 && sel < estado.produtos.length;
    cb.disabled = estado.produtos.length === 0;
    $('#botao-remover-todos').disabled = estado.produtos.length === 0;
  }

  function renderizarTotais() {
    const total = estado.produtos.reduce((s, p) => s + p.quantidade, 0);
    const sel = estado.produtos.filter((p) => p.selecionado);
    const totalSel = sel.reduce((s, p) => s + p.quantidade, 0);
    const t = $('#totais');
    t.textContent = '';
    if (!estado.produtos.length) return;
    t.append(
      'Cadastrados: ',
      el('strong', null, plural(estado.produtos.length, 'produto', 'produtos')),
      ' · ',
      el('strong', null, plural(total, 'etiqueta', 'etiquetas')),
      ` · Selecionados: ${plural(sel.length, 'produto', 'produtos')} (${plural(totalSel, 'etiqueta', 'etiquetas')})`
    );
  }

  $('#selecionar-todos').addEventListener('change', (e) => {
    estado.produtos.forEach((p) => (p.selecionado = e.target.checked));
    salvarProdutos();
    renderizarTudo();
  });

  $('#botao-remover-todos').addEventListener('click', () => {
    if (!estado.produtos.length) return;
    if (!confirm(`Remover todos os ${estado.produtos.length} produtos cadastrados? Esta ação não pode ser desfeita.`)) return;
    estado.produtos = [];
    salvarProdutos();
    limparFormulario();
    renderizarTudo();
    status('Todos os produtos foram removidos.');
  });

  $$('input[name="escopo"]').forEach((r) => {
    r.checked = r.value === estado.escopo;
    r.addEventListener('change', () => {
      if (!r.checked) return;
      estado.escopo = r.value;
      gravarArmazenado(CHAVES.escopo, estado.escopo);
      renderizarPrevia();
    });
  });

  /* ---------- Prévia ---------- */

  function renderizarPrevia() {
    const cfg = estado.config;
    const { fileiras, totalEtiquetas } = L.montarFileiras(produtosDoEscopo(), cfg);
    const previa = $('#previa');
    previa.style.zoom = String(cfg.zoom);
    const resumo = $('#resumo-previa');
    const nota = $('#nota-previa');
    resumo.textContent = '';
    nota.textContent = '';

    if (!totalEtiquetas) {
      previa.style.zoom = '1';
      previa.textContent = '';
      previa.appendChild(
        el('div', 'previa-vazia', estado.escopo === 'selecionados' && estado.produtos.length
          ? 'Nenhum produto selecionado para impressão.'
          : 'Adicione produtos para ver a prévia das etiquetas.')
      );
      return;
    }

    const r = R.renderizarFolhas(previa, fileiras, cfg, { maxFileiras: MAX_FILEIRAS_PREVIA });
    const pag = L.tamanhoPagina(cfg, fileiras.length);
    resumo.append(
      el('strong', null, plural(totalEtiquetas, 'etiqueta', 'etiquetas')),
      ` · ${plural(fileiras.length, 'fileira', 'fileiras')} · ${plural(pag.paginas, 'página', 'páginas')} de ${fmtMm(pag.largura)} × ${fmtMm(pag.altura)} mm`
    );
    const vazias = fileiras.length * 3 - totalEtiquetas;
    const partes = [];
    if (vazias > 0) partes.push(`${plural(vazias, 'posição ficará', 'posições ficarão')} em branco (hachuradas na prévia).`);
    if (r.fileirasOcultas > 0) partes.push(`A prévia mostra as primeiras ${MAX_FILEIRAS_PREVIA} fileiras; as outras ${r.fileirasOcultas.toLocaleString('pt-BR')} serão impressas normalmente.`);
    nota.textContent = partes.join(' ');
  }

  /* ---------- Configurações ---------- */

  function preencherConfig() {
    for (const campo of $$('[data-cfg]')) {
      const v = estado.config[campo.dataset.cfg];
      if (campo.type === 'checkbox') campo.checked = v;
      else campo.value = String(v);
    }
    for (const [chave, def] of Object.entries(L.CAMPOS_CONFIG)) {
      const campo = $(`#cfg-${chave}`);
      if (campo && campo.type === 'number') {
        campo.min = def.min;
        campo.max = def.max;
      }
    }
  }

  function atualizarPapel() {
    const { fileiras } = L.montarFileiras(produtosDoEscopo(), estado.config);
    const pag = L.tamanhoPagina(estado.config, fileiras.length);
    $('#papel-tamanho').textContent = `${fmtMm(pag.largura)} × ${fmtMm(pag.altura)} mm`;
    $('#papel-detalhe').textContent =
      estado.config.modoPagina === 'continuo'
        ? ' (largura × altura total; a altura muda conforme a quantidade de fileiras).'
        : ' (largura × altura de uma fileira; cada fileira é uma página).';
    const alertas = [];
    if (pag.largura > L.LARGURA_MAX_IMPRESSAO) {
      alertas.push(`A largura passa de ${L.LARGURA_MAX_IMPRESSAO} mm, a largura máxima de impressão da 4B-2054N; a parte da direita pode não sair.`);
    }
    if (estado.config.modoPagina === 'continuo' && pag.altura > L.ALTURA_MAX_CONTINUO) {
      alertas.push('Página contínua muito longa; use o modo “Uma página por fileira”.');
    }
    $('#papel-alerta').textContent = alertas.join(' ');
  }

  for (const campo of $$('[data-cfg]')) {
    campo.addEventListener('change', () => {
      const chave = campo.dataset.cfg;
      const valor = campo.type === 'checkbox' ? campo.checked : campo.value;
      estado.config = L.sanitizarConfig({ ...estado.config, [chave]: valor });
      if (campo.type !== 'checkbox') campo.value = String(estado.config[chave]);
      salvarConfig();
      renderizarTudo();
    });
  }

  $('#botao-restaurar').addEventListener('click', () => {
    if (!confirm('Restaurar todos os ajustes de impressão para os valores padrão?')) return;
    estado.config = L.configPadrao();
    salvarConfig();
    preencherConfig();
    renderizarTudo();
    status('Ajustes restaurados para os valores padrão.');
  });

  /* ---------- Impressão ---------- */

  function prepararImpressao(fileiras) {
    R.renderizarFolhas($('#area-impressao'), fileiras, estado.config);
    R.aplicarEstiloPagina(estado.config, fileiras.length);
    estado.impressaoPreparada = true;
  }

  function imprimirFileiras(fileiras) {
    prepararImpressao(fileiras);
    // A janela do navegador exige a confirmação do usuário antes de enviar à impressora.
    setTimeout(() => window.print(), 60);
  }

  $('#botao-imprimir').addEventListener('click', () => {
    const cfg = estado.config;
    const { fileiras, totalEtiquetas } = L.montarFileiras(produtosDoEscopo(), cfg);
    if (!totalEtiquetas) {
      status(estado.escopo === 'selecionados' ? 'Nenhum produto selecionado para imprimir.' : 'Cadastre ao menos um produto antes de imprimir.', true);
      return;
    }
    const pag = L.tamanhoPagina(cfg, fileiras.length);
    const vazias = fileiras.length * 3 - totalEtiquetas;
    const escopo = estado.escopo === 'selecionados' ? 'somente produtos selecionados' : 'todos os produtos cadastrados';
    $('#dialogo-resumo').textContent =
      `Serão impressas ${plural(totalEtiquetas, 'etiqueta', 'etiquetas')} em ${plural(fileiras.length, 'fileira', 'fileiras')} de 3 colunas ` +
      `(${escopo}), em ${plural(pag.paginas, 'página', 'páginas')}.` +
      (vazias ? ` ${plural(vazias, 'posição ficará', 'posições ficarão')} em branco.` : '');
    $('#dialogo-papel').textContent = `${fmtMm(pag.largura)} × ${fmtMm(pag.altura)} mm`;
    estado.fileirasPendentes = fileiras;
    $('#dialogo-impressao').showModal();
  });

  $('#dialogo-cancelar').addEventListener('click', () => {
    estado.fileirasPendentes = null;
    $('#dialogo-impressao').close();
  });

  $('#dialogo-confirmar').addEventListener('click', () => {
    const fileiras = estado.fileirasPendentes;
    estado.fileirasPendentes = null;
    $('#dialogo-impressao').close();
    if (fileiras) imprimirFileiras(fileiras);
  });

  $('#botao-teste-unico').addEventListener('click', () => {
    imprimirFileiras(L.fileiraTeste([estado.config.colunaTeste]));
  });

  $('#botao-teste-fileira').addEventListener('click', () => {
    imprimirFileiras(L.fileiraTeste([1, 2, 3]));
  });

  // Ctrl+P / Cmd+P sem usar os botões: imprime o mesmo conteúdo da prévia.
  window.addEventListener('beforeprint', () => {
    if (estado.impressaoPreparada) return;
    const { fileiras } = L.montarFileiras(produtosDoEscopo(), estado.config);
    prepararImpressao(fileiras);
  });
  window.addEventListener('afterprint', () => {
    estado.impressaoPreparada = false;
  });

  /* ---------- Início ---------- */

  function renderizarTudo() {
    renderizarLista();
    renderizarPrevia();
    atualizarPapel();
  }

  preencherConfig();
  atualizarDicaCodigo();
  renderizarTudo();
})();
