(function () {
  'use strict';

  const L = window.EtqLayout;
  const B = window.EtqBarcode;
  const R = window.EtqRender;
  const T = window.EtqTspl;

  const CHAVES = {
    produtos: 'etiquetas33x21.produtos.v1',
    config: 'etiquetas33x21.config.v2',
    escopo: 'etiquetas33x21.escopo.v1',
    leituras: 'etiquetas33x21.leituras.v1',
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
    leituras: sanitizarLeituras(lerArmazenado(CHAVES.leituras, [])),
  };

  function sanitizarLeituras(lista) {
    if (!Array.isArray(lista)) return [];
    return lista.filter((l) => l && typeof l.codigo === 'string' && typeof l.nome === 'string' && Number.isInteger(Number(l.precoCentavos)) && typeof l.quando === 'string').map((l) => ({
      id: typeof l.id === 'string' && l.id ? l.id : novoId(),
      quando: l.quando,
      codigo: l.codigo,
      produtoId: typeof l.produtoId === 'string' ? l.produtoId : '',
      nome: l.nome,
      precoCentavos: Number(l.precoCentavos),
    })).slice(0, 2000);
  }

  const salvarProdutos = () => gravarArmazenado(CHAVES.produtos, estado.produtos);
  const salvarLeituras = () => gravarArmazenado(CHAVES.leituras, estado.leituras);
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

    let r;
    let pag;
    if (cfg.metodo === 'direto') {
      pag = L.tamanhoPagina({ ...cfg, modoPagina: 'fileira' }, fileiras.length);
      previa.textContent = '';
      const visiveis = fileiras.slice(0, MAX_FILEIRAS_PREVIA);
      for (const fileira of visiveis) {
        const canvas = T.canvasPrevia(fileira, cfg);
        canvas.className = 'folha-bitmap';
        canvas.style.width = `${pag.largura}mm`;
        canvas.style.height = `${pag.altura}mm`;
        previa.appendChild(canvas);
      }
      r = { fileirasOcultas: fileiras.length - visiveis.length };
    } else {
      r = R.renderizarFolhas(previa, fileiras, cfg, { maxFileiras: MAX_FILEIRAS_PREVIA });
      pag = L.tamanhoPagina(cfg, fileiras.length);
    }
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
    if (L.larguraBloco(estado.config) - pag.largura > 2 * estado.config.margemInterna) {
      alertas.push('As colunas são mais largas que a página: o conteúdo das bordas será cortado.');
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
    if (estado.config.metodo === 'direto') {
      enviarDireto(() => T.gerarTrabalho(fileiras, estado.config), `${plural(fileiras.length, 'fileira enviada', 'fileiras enviadas')} à impressora.`);
      return;
    }
    prepararImpressao(fileiras);
    // A janela do navegador exige a confirmação do usuário antes de enviar à impressora.
    setTimeout(() => window.print(), 60);
  }

  function mensagemErroUsb(e) {
    if (e && e.name === 'NotFoundError') return 'Nenhuma impressora foi escolhida na lista.';
    if (e && e.name === 'SecurityError') return 'O navegador bloqueou o acesso USB. Abra o aplicativo pelo endereço https do GitHub Pages ou como arquivo local no Chrome/Edge.';
    return (e && e.message) || 'Falha ao comunicar com a impressora.';
  }

  async function enviarDireto(gerar, sucesso) {
    try {
      const trabalho = gerar();
      const bytes = trabalho.bytes || trabalho;
      status('Enviando para a impressora…');
      await T.enviar(bytes);
      status(sucesso);
    } catch (e) {
      status(mensagemErroUsb(e), true);
    }
    atualizarConexao();
  }

  function atualizarConexao() {
    const nome = T.nomeConectado();
    const alvo = $('#status-conexao');
    alvo.textContent = nome ? `conectada (${nome})` : T.usbDisponivel() ? 'não conectada' : 'navegador sem acesso USB (use Chrome ou Edge)';
    alvo.classList.toggle('ok', !!nome);
  }

  function aplicarMetodo() {
    for (const bloco of $$('[data-metodo]')) bloco.hidden = bloco.dataset.metodo !== estado.config.metodo;
    $('#dialogo-confirmar').textContent = estado.config.metodo === 'direto' ? 'Imprimir agora' : 'Abrir janela de impressão';
  }

  $('#botao-conectar').addEventListener('click', async () => {
    try {
      await T.conectar(true);
      status('Impressora conectada.');
    } catch (e) {
      status(mensagemErroUsb(e), true);
    }
    atualizarConexao();
  });

  $('#botao-calibrar-sensor').addEventListener('click', () => {
    enviarDireto(T.comandoCalibrar, 'Calibração do sensor enviada: a impressora avança algumas etiquetas para medir o gap.');
  });

  $('#botao-avancar').addEventListener('click', () => {
    enviarDireto(() => T.comandoAvancar(estado.config), 'Avançou uma fileira.');
  });

  $('#botao-baixar-prn').addEventListener('click', () => {
    const { fileiras, totalEtiquetas } = L.montarFileiras(produtosDoEscopo(), estado.config);
    if (!totalEtiquetas) {
      status('Cadastre ou selecione produtos antes de gerar o arquivo.', true);
      return;
    }
    const { bytes } = T.gerarTrabalho(fileiras, estado.config);
    const url = URL.createObjectURL(new Blob([bytes], { type: 'application/octet-stream' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = 'etiquetas.prn';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 5000);
    status(`Arquivo etiquetas.prn gerado com ${plural(totalEtiquetas, 'etiqueta', 'etiquetas')}. Veja no LEIA-ME como enviá-lo à impressora.`);
  });

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
    const pagDireta = L.tamanhoPagina({ ...cfg, modoPagina: 'fileira' }, 1);
    $('#dialogo-size').textContent = `${fmtMm(pagDireta.largura)} × ${fmtMm(pagDireta.altura)} mm`;
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

  /* ---------- Leitor de código de barras ---------- */

  const campoLeitor = $('#campo-leitor');
  const diaLocal = (data) => {
    const d = new Date(data);
    return `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;
  };
  const ehHoje = (quando) => diaLocal(quando) === diaLocal(new Date());
  const horaLocal = (quando) => new Date(quando).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

  let audioCtx = null;
  function bip(ok) {
    try {
      audioCtx = audioCtx || new AudioContext();
      const osc = audioCtx.createOscillator();
      const ganho = audioCtx.createGain();
      osc.frequency.value = ok ? 880 : 220;
      ganho.gain.setValueAtTime(0.0001, audioCtx.currentTime);
      ganho.gain.exponentialRampToValueAtTime(0.2, audioCtx.currentTime + 0.01);
      ganho.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + (ok ? 0.12 : 0.28));
      osc.connect(ganho);
      ganho.connect(audioCtx.destination);
      osc.start();
      osc.stop(audioCtx.currentTime + 0.3);
      audioCtx.resume();
    } catch (e) { /* sem áudio */ }
  }

  function mostrarResultado(tipo, conteudo, extra) {
    const caixa = $('#resultado-leitura');
    caixa.className = 'resultado-leitura' + (tipo ? ' ' + tipo : '');
    caixa.textContent = '';
    if (tipo === 'ok') {
      caixa.append(
        el('span', 'lido-nome', conteudo.nome),
        el('span', 'lido-preco', L.formatarPreco(conteudo.precoCentavos)),
        el('span', 'lido-extra', extra)
      );
    } else {
      caixa.textContent = conteudo;
    }
  }

  function renderizarLeituras() {
    const deHoje = estado.leituras.filter((l) => ehHoje(l.quando));
    const corpo = $('#lista-leituras');
    corpo.textContent = '';
    $('#leituras-vazias').hidden = deHoje.length > 0;
    for (const l of deHoje.slice(0, 50)) {
      const tr = el('tr');
      tr.append(
        el('td', null, horaLocal(l.quando)),
        el('td', 'nome-produto', l.nome),
        el('td', 'num', L.formatarPreco(l.precoCentavos)),
        el('td', 'codigo-produto', l.codigo)
      );
      corpo.appendChild(tr);
    }
    const unidades = deHoje.length;
    const produtos = new Set(deHoje.map((l) => l.produtoId || l.codigo)).size;
    const t = $('#totais-leituras');
    t.textContent = '';
    t.append('Hoje: ', el('strong', null, plural(unidades, 'leitura', 'leituras')), ` · ${plural(produtos, 'produto diferente', 'produtos diferentes')}`);
    $('#botao-limpar-leituras').disabled = unidades === 0;
  }

  function registrarLeitura(codigoBruto) {
    const codigo = String(codigoBruto || '').trim();
    campoLeitor.value = '';
    if (codigo.length < 3) return;
    const produto = estado.produtos.find((p) => p.codigo && p.codigo === codigo);
    if (!produto) {
      campoCodigo.value = codigo;
      atualizarDicaCodigo();
      mostrarResultado('nao', `O código ${codigo} não está cadastrado. Preencha o nome e o preço para registrar esse produto; a próxima leitura já será reconhecida.`);
      bip(false);
      campoNome.focus();
      campoNome.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
    estado.leituras.unshift({
      id: novoId(),
      quando: new Date().toISOString(),
      codigo,
      produtoId: produto.id,
      nome: produto.nome,
      precoCentavos: produto.precoCentavos,
    });
    if (estado.leituras.length > 2000) estado.leituras.length = 2000;
    salvarLeituras();
    const vezes = estado.leituras.filter((l) => ehHoje(l.quando) && l.produtoId === produto.id).length;
    mostrarResultado('ok', produto, `Registrado às ${horaLocal(estado.leituras[0].quando)} · ${plural(vezes, 'vez hoje', 'vezes hoje')}`);
    bip(true);
    renderizarLeituras();
    campoLeitor.focus();
  }

  // O leitor envia o código de uma vez, bem mais rápido do que uma pessoa digita.
  let bufferLeitor = '';
  let ultimoToque = 0;
  let rajadaRapida = false;
  document.addEventListener('keydown', (evento) => {
    if (evento.ctrlKey || evento.altKey || evento.metaKey || evento.isComposing) return;
    const agora = performance.now();
    if (evento.key === 'Enter' || evento.key === 'Tab') {
      const codigo = bufferLeitor;
      const foiRajada = rajadaRapida && codigo.length >= 3 && agora - ultimoToque < 250;
      bufferLeitor = '';
      rajadaRapida = false;
      if (foiRajada) {
        evento.preventDefault();
        evento.stopPropagation();
        const alvo = evento.target;
        if (alvo instanceof HTMLInputElement && alvo !== campoLeitor && alvo.value.endsWith(codigo)) {
          alvo.value = alvo.value.slice(0, -codigo.length);
        }
        registrarLeitura(codigo);
      }
      return;
    }
    if (evento.key.length !== 1) return;
    const intervalo = agora - ultimoToque;
    if (intervalo > 90) {
      bufferLeitor = '';
      rajadaRapida = true;
    } else if (intervalo > 40) {
      rajadaRapida = false;
    }
    bufferLeitor += evento.key;
    ultimoToque = agora;
    if (bufferLeitor.length > B.LIMITE) bufferLeitor = bufferLeitor.slice(-B.LIMITE);
  }, true);

  campoLeitor.addEventListener('keydown', (evento) => {
    if (evento.key === 'Enter') {
      evento.preventDefault();
      if (campoLeitor.value.trim().length >= 3) registrarLeitura(campoLeitor.value);
    }
  });

  $('#botao-limpar-leituras').addEventListener('click', () => {
    const deHoje = estado.leituras.filter((l) => ehHoje(l.quando)).length;
    if (!deHoje) return;
    if (!confirm(`Apagar os ${deHoje} registros de leitura de hoje?`)) return;
    estado.leituras = estado.leituras.filter((l) => !ehHoje(l.quando));
    salvarLeituras();
    mostrarResultado('', 'Registros de hoje apagados. Bipe uma etiqueta para registrar o produto.');
    renderizarLeituras();
  });

  /* ---------- Início ---------- */

  function renderizarTudo() {
    aplicarMetodo();
    renderizarLeituras();
    renderizarLista();
    renderizarPrevia();
    atualizarPapel();
  }

  preencherConfig();
  atualizarConexao();
  if (T.usbDisponivel()) {
    T.conectar(false).then(atualizarConexao, atualizarConexao);
    navigator.usb.addEventListener('disconnect', () => setTimeout(atualizarConexao, 0));
  }
  atualizarDicaCodigo();
  renderizarTudo();
  campoLeitor.focus();
})();
