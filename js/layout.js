/*
 * Cálculos puros de layout: dimensões físicas, distribuição em fileiras,
 * configuração e formatação de preços. Funciona no navegador e no Node (testes).
 */
(function (raiz, fabrica) {
  const api = fabrica();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else raiz.EtqLayout = api;
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  const ETIQUETA = Object.freeze({ largura: 33, altura: 21, colunas: 3 });
  // Largura máxima de impressão informada para a 4B-2054N / MDK-2054N (4,09").
  const LARGURA_MAX_IMPRESSAO = 104;
  const ALTURA_MAX_CONTINUO = 2000;
  const LIMITE_NOME = 120;
  const LIMITE_QTD = 9999;

  const CAMPOS_CONFIG = {
    metodo: { tipo: 'opcao', padrao: 'direto', opcoes: ['direto', 'navegador'] },
    larguraPagina: { tipo: 'numero', padrao: 104, min: 99, max: 120 },
    margemSuperior: { tipo: 'numero', padrao: 0, min: 0, max: 20 },
    margemInferior: { tipo: 'numero', padrao: 0, min: 0, max: 20 },
    espacoHorizontal: { tipo: 'numero', padrao: 3, min: 0, max: 10 },
    espacoVertical: { tipo: 'numero', padrao: 3, min: 0, max: 20 },
    deslocX: { tipo: 'numero', padrao: 0, min: -10, max: 10 },
    deslocY: { tipo: 'numero', padrao: 0, min: -10, max: 10 },
    margemInterna: { tipo: 'numero', padrao: 1.2, min: 0, max: 4 },
    fonteNome: { tipo: 'numero', padrao: 8, min: 5, max: 16 },
    fontePreco: { tipo: 'numero', padrao: 14, min: 6, max: 28 },
    alturaCodigo: { tipo: 'numero', padrao: 5.5, min: 3, max: 12 },
    larguraCodigo: { tipo: 'numero', padrao: 100, min: 40, max: 100 },
    mostrarDigitos: { tipo: 'booleano', padrao: true },
    alinhamentoTexto: { tipo: 'opcao', padrao: 'center', opcoes: ['center', 'left'] },
    modoPagina: { tipo: 'opcao', padrao: 'fileira', opcoes: ['fileira', 'continuo'] },
    novaFileiraPorProduto: { tipo: 'booleano', padrao: false },
    colunaTeste: { tipo: 'numero', padrao: 1, min: 1, max: 3, inteiro: true },
    zoom: { tipo: 'numero', padrao: 2, min: 1, max: 4 },
    tsplVelocidade: { tipo: 'numero', padrao: 3, min: 1, max: 6, inteiro: true },
    tsplDensidade: { tipo: 'numero', padrao: 8, min: 0, max: 15, inteiro: true },
    tsplGirar: { tipo: 'booleano', padrao: false },
    tsplInverter: { tipo: 'booleano', padrao: false },
  };

  const arred = (n) => Math.round(n * 100) / 100;

  function configPadrao() {
    const c = {};
    for (const [chave, def] of Object.entries(CAMPOS_CONFIG)) c[chave] = def.padrao;
    return c;
  }

  function sanitizarConfig(bruta) {
    const c = configPadrao();
    if (!bruta || typeof bruta !== 'object') return c;
    for (const [chave, def] of Object.entries(CAMPOS_CONFIG)) {
      if (!(chave in bruta)) continue;
      const v = bruta[chave];
      if (def.tipo === 'numero') {
        let n = typeof v === 'string' ? parseFloat(v.replace(',', '.')) : Number(v);
        if (!Number.isFinite(n)) continue;
        n = Math.min(def.max, Math.max(def.min, n));
        c[chave] = def.inteiro ? Math.round(n) : arred(n);
      } else if (def.tipo === 'booleano') {
        c[chave] = v === true;
      } else if (def.opcoes.includes(v)) {
        c[chave] = v;
      }
    }
    return c;
  }

  function larguraBloco(cfg) {
    return ETIQUETA.colunas * ETIQUETA.largura + (ETIQUETA.colunas - 1) * cfg.espacoHorizontal;
  }

  /**
   * Posição horizontal (mm) da borda esquerda de cada coluna dentro da página.
   * A bobina fica centralizada pelas guias da impressora, então o bloco das
   * 3 colunas é centralizado na largura da página e depois deslocado.
   */
  function posicoesColunas(cfg) {
    const inicio = (cfg.larguraPagina - larguraBloco(cfg)) / 2 + cfg.deslocX;
    return Array.from({ length: ETIQUETA.colunas }, (_, i) => arred(inicio + i * (ETIQUETA.largura + cfg.espacoHorizontal)));
  }

  /** Tamanho de cada página (mm) para o total de fileiras informado. */
  function tamanhoPagina(cfg, totalFileiras) {
    const n = Math.max(1, totalFileiras | 0);
    const { altura } = ETIQUETA;
    const larguraPagina = cfg.larguraPagina;
    if (cfg.modoPagina === 'continuo') {
      return {
        largura: arred(larguraPagina),
        altura: arred(cfg.margemSuperior + n * altura + (n - 1) * cfg.espacoVertical + cfg.margemInferior),
        paginas: 1,
      };
    }
    return {
      largura: arred(larguraPagina),
      altura: arred(cfg.margemSuperior + altura + cfg.margemInferior),
      paginas: n,
    };
  }

  function emFileiras(celulas) {
    const cols = ETIQUETA.colunas;
    while (celulas.length % cols) celulas.push(null);
    const fileiras = [];
    for (let i = 0; i < celulas.length; i += cols) fileiras.push(celulas.slice(i, i + cols));
    return fileiras;
  }

  /**
   * Expande os produtos na quantidade exata de etiquetas e distribui em
   * fileiras de 3 colunas. Posições sem etiqueta são `null`.
   */
  function montarFileiras(produtos, cfg) {
    const cols = ETIQUETA.colunas;
    const celulas = [];
    let indice = 0;
    for (const produto of produtos) {
      const qtd = Math.max(0, Math.floor(Number(produto.quantidade) || 0));
      if (qtd === 0) continue;
      if (cfg && cfg.novaFileiraPorProduto) while (celulas.length % cols) celulas.push(null);
      for (let k = 0; k < qtd; k++) celulas.push({ produto, indice: indice++ });
    }
    return { fileiras: emFileiras(celulas), totalEtiquetas: indice };
  }

  /** Uma fileira com uma única etiqueta do produto na coluna escolhida (1 a 3). */
  function fileiraUnica(produto, coluna) {
    const fileira = [null, null, null];
    fileira[coluna - 1] = { produto, indice: 0 };
    return [fileira];
  }

  /** Fileira de calibração; `colunas` lista as colunas (1 a 3) que recebem a etiqueta de teste. */
  function fileiraTeste(colunas) {
    const fileira = [null, null, null];
    for (const c of colunas) fileira[c - 1] = { teste: true, coluna: c };
    return [fileira];
  }

  function parsePreco(texto) {
    if (texto == null) return null;
    let s = String(texto).replace(/R\$/gi, '').replace(/[\s\u00a0]/g, '');
    if (!s) return null;
    if (s.includes(',')) {
      if (!/^(\d{1,3}(\.\d{3})+|\d+),\d{1,2}$/.test(s)) return null;
      s = s.replace(/\./g, '').replace(',', '.');
    } else if (/^\d{1,3}(\.\d{3})+$/.test(s)) {
      s = s.replace(/\./g, '');
    }
    if (!/^\d+(\.\d{1,2})?$/.test(s)) return null;
    const [inteiro, decimal = ''] = s.split('.');
    const centavos = parseInt(inteiro, 10) * 100 + parseInt((decimal + '00').slice(0, 2), 10);
    if (centavos > 99999999) return null;
    return centavos;
  }

  const fmtValor = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  function formatarValor(centavos) {
    return fmtValor.format(centavos / 100);
  }

  function formatarPreco(centavos) {
    return 'R$\u00a0' + formatarValor(centavos);
  }

  return {
    ETIQUETA,
    LARGURA_MAX_IMPRESSAO,
    ALTURA_MAX_CONTINUO,
    LIMITE_NOME,
    LIMITE_QTD,
    CAMPOS_CONFIG,
    configPadrao,
    sanitizarConfig,
    tamanhoPagina,
    larguraBloco,
    posicoesColunas,
    montarFileiras,
    fileiraUnica,
    fileiraTeste,
    parsePreco,
    formatarValor,
    formatarPreco,
  };
});
