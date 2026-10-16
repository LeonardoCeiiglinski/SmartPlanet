/*
 * Renderização das etiquetas em milímetros reais. A mesma função gera a prévia
 * e a área de impressão, garantindo que o que se vê é o que será impresso.
 */
(function (raiz) {
  'use strict';

  const L = raiz.EtqLayout;
  const B = raiz.EtqBarcode;
  const PONTO_MM = 25.4 / 203; // 1 ponto da cabeça térmica de 203 dpi
  const ZONA_SILENCIO = 5; // módulos reservados de cada lado do código
  const NOME_MIN = 5;
  const PRECO_MIN = 6;
  const SVG_NS = 'http://www.w3.org/2000/svg';
  const cache = new Map();
  let host = null;

  const mm = (v) => `${Math.round(v * 1000) / 1000}mm`;

  function div(classe, texto) {
    const el = document.createElement('div');
    el.className = classe;
    if (texto != null) el.textContent = texto;
    return el;
  }

  function aplicarCaixa(el, cfg, comMargemInterna = true) {
    el.style.width = mm(L.ETIQUETA.largura);
    el.style.height = mm(L.ETIQUETA.altura);
    el.style.padding = comMargemInterna ? mm(cfg.margemInterna) : '0';
  }

  /** Largura do módulo arredondada para múltiplos de ponto da impressora, quando possível. */
  function geometriaCodigo(codigo, cfg) {
    const enc = codigo ? B.codificar(codigo) : null;
    if (!enc) return null;
    const util = (L.ETIQUETA.largura - 2 * cfg.margemInterna) * (cfg.larguraCodigo / 100);
    const bruto = util / (enc.bits.length + 2 * ZONA_SILENCIO);
    let modulo = Math.min(3, Math.floor(bruto / PONTO_MM + 1e-9)) * PONTO_MM;
    let denso = false;
    if (modulo < PONTO_MM) {
      modulo = bruto;
      denso = true;
    }
    return { enc, modulo, largura: enc.bits.length * modulo, denso };
  }

  function criarSvg(bits, modulo, altura) {
    const svg = document.createElementNS(SVG_NS, 'svg');
    svg.setAttribute('viewBox', `0 0 ${bits.length} 10`);
    svg.setAttribute('preserveAspectRatio', 'none');
    svg.setAttribute('shape-rendering', 'crispEdges');
    svg.setAttribute('aria-hidden', 'true');
    svg.style.width = mm(bits.length * modulo);
    svg.style.height = mm(altura);
    let d = '';
    for (let i = 0; i < bits.length; ) {
      if (bits[i] === '1') {
        let j = i;
        while (j < bits.length && bits[j] === '1') j++;
        d += `M${i} 0h${j - i}v10h${i - j}z`;
        i = j;
      } else {
        i++;
      }
    }
    const path = document.createElementNS(SVG_NS, 'path');
    path.setAttribute('d', d);
    path.setAttribute('fill', '#000');
    svg.appendChild(path);
    return svg;
  }

  function criarEtiqueta(produto, cfg, ajuste, indice) {
    const el = div('etiqueta');
    aplicarCaixa(el, cfg);
    el.style.textAlign = cfg.alinhamentoTexto;
    if (indice !== undefined) el.dataset.indice = String(indice);

    const caixa = div('etq-nome');
    const texto = div('etq-nome-texto', produto.nome);
    texto.lang = 'pt-BR';
    texto.style.fontSize = ajuste.nomePt + 'pt';
    if (ajuste.linhas) {
      texto.classList.add('cortado');
      texto.style.setProperty('-webkit-line-clamp', String(ajuste.linhas));
    }
    caixa.appendChild(texto);
    el.appendChild(caixa);

    const preco = div('etq-preco');
    preco.style.fontSize = ajuste.precoPt + 'pt';
    preco.append(div('etq-moeda', 'R$'), L.formatarValor(produto.precoCentavos));
    el.appendChild(preco);

    if (ajuste.geo) {
      const cod = div('etq-codigo');
      cod.appendChild(criarSvg(ajuste.geo.enc.bits, ajuste.geo.modulo, cfg.alturaCodigo));
      if (cfg.mostrarDigitos) cod.appendChild(div('etq-digitos', ajuste.geo.enc.texto));
      el.appendChild(cod);
    }
    return el;
  }

  function obterHost() {
    if (!host || !host.isConnected) {
      host = div('host-medicao');
      host.setAttribute('aria-hidden', 'true');
      document.body.appendChild(host);
    }
    return host;
  }

  /**
   * Mede a etiqueta em tamanho real e reduz as fontes até nome e preço caberem
   * sem sobrepor o código de barras. Resultado reaproveitado para etiquetas iguais.
   */
  function medirAjuste(produto, cfg) {
    const chave = JSON.stringify([
      produto.nome, produto.precoCentavos, produto.codigo || '', cfg.margemInterna, cfg.fonteNome,
      cfg.fontePreco, cfg.alturaCodigo, cfg.larguraCodigo, cfg.mostrarDigitos, cfg.alinhamentoTexto,
    ]);
    if (cache.has(chave)) return cache.get(chave);

    const ajuste = {
      nomePt: cfg.fonteNome,
      precoPt: cfg.fontePreco,
      linhas: 0,
      reduzido: false,
      cortado: false,
      semEspaco: false,
      geo: geometriaCodigo(produto.codigo, cfg),
    };
    const el = criarEtiqueta(produto, cfg, ajuste);
    obterHost().appendChild(el);

    const preco = el.querySelector('.etq-preco');
    while (ajuste.precoPt > PRECO_MIN && preco.scrollWidth > preco.clientWidth) {
      ajuste.precoPt = Math.max(PRECO_MIN, ajuste.precoPt - 0.5);
      preco.style.fontSize = ajuste.precoPt + 'pt';
    }

    const caixa = el.querySelector('.etq-nome');
    const texto = caixa.firstChild;
    const cabe = () =>
      texto.getBoundingClientRect().height <= caixa.getBoundingClientRect().height + 0.25 &&
      texto.scrollWidth <= texto.clientWidth + 0.5;
    while (ajuste.nomePt > NOME_MIN && !cabe()) {
      ajuste.nomePt = Math.max(NOME_MIN, ajuste.nomePt - 0.25);
      texto.style.fontSize = ajuste.nomePt + 'pt';
    }
    ajuste.reduzido = ajuste.nomePt < cfg.fonteNome;
    if (!cabe()) {
      const alturaLinha = parseFloat(getComputedStyle(texto).lineHeight);
      const linhas = Math.floor((caixa.getBoundingClientRect().height + 0.25) / alturaLinha);
      ajuste.cortado = true;
      ajuste.semEspaco = linhas < 1;
      ajuste.linhas = Math.max(1, linhas);
    }

    el.remove();
    cache.set(chave, ajuste);
    if (cache.size > 500) cache.delete(cache.keys().next().value);
    return ajuste;
  }

  function criarEtiquetaTeste(cfg, coluna) {
    const el = div('etiqueta etiqueta-teste');
    aplicarCaixa(el, cfg, false);
    el.dataset.teste = String(coluna);
    el.append(
      div('teste-borda'),
      div('teste-cruz-h'),
      div('teste-cruz-v'),
      div('teste-titulo', 'TESTE 33 × 21 mm'),
      div('teste-coluna', `COLUNA ${coluna}`)
    );
    return el;
  }

  function criarCelula(celula, cfg) {
    if (!celula) {
      const vazia = div('etiqueta vazia');
      aplicarCaixa(vazia, cfg, false);
      return vazia;
    }
    if (celula.teste) return criarEtiquetaTeste(cfg, celula.coluna);
    return criarEtiqueta(celula.produto, cfg, medirAjuste(celula.produto, cfg), celula.indice);
  }

  /**
   * Gera as folhas. Modo "fileira": uma página por fileira. Modo "continuo":
   * uma única página com todas as fileiras.
   */
  function renderizarFolhas(container, fileiras, cfg, opcoes = {}) {
    container.textContent = '';
    const limite =
      opcoes.maxFileiras && fileiras.length > opcoes.maxFileiras ? opcoes.maxFileiras : fileiras.length;
    const visiveis = fileiras.slice(0, limite);
    const grupos = cfg.modoPagina === 'continuo' ? (visiveis.length ? [visiveis] : []) : visiveis.map((f) => [f]);

    const frag = document.createDocumentFragment();
    for (const grupo of grupos) {
      const pag = L.tamanhoPagina(cfg, grupo.length);
      const folha = div('folha');
      folha.style.width = mm(pag.largura);
      folha.style.height = mm(pag.altura);
      const conteudo = div('folha-conteudo');
      conteudo.style.left = mm((cfg.larguraPagina - L.larguraBloco(cfg)) / 2);
      conteudo.style.top = mm(cfg.margemSuperior);
      conteudo.style.rowGap = mm(cfg.espacoVertical);
      if (cfg.deslocX || cfg.deslocY) conteudo.style.transform = `translate(${mm(cfg.deslocX)}, ${mm(cfg.deslocY)})`;
      for (const fileira of grupo) {
        const linha = div('fileira');
        linha.style.columnGap = mm(cfg.espacoHorizontal);
        for (const celula of fileira) linha.appendChild(criarCelula(celula, cfg));
        conteudo.appendChild(linha);
      }
      folha.appendChild(conteudo);
      frag.appendChild(folha);
    }
    container.appendChild(frag);
    return { fileirasRenderizadas: limite, fileirasOcultas: fileiras.length - limite };
  }

  function aplicarEstiloPagina(cfg, totalFileiras) {
    const pag = L.tamanhoPagina(cfg, totalFileiras);
    let el = document.getElementById('estilo-pagina');
    if (!el) {
      el = document.createElement('style');
      el.id = 'estilo-pagina';
      document.head.appendChild(el);
    }
    el.textContent = `@page { size: ${pag.largura}mm ${pag.altura}mm; margin: 0; }`;
    return pag;
  }

  raiz.EtqRender = { medirAjuste, geometriaCodigo, renderizarFolhas, aplicarEstiloPagina };
})(window);
