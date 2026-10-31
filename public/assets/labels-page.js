(function () {
  'use strict';

  if (window.__labelsPageAbort) window.__labelsPageAbort.abort();
  const pageAbort = new AbortController();
  window.__labelsPageAbort = pageAbort;
  const on = { signal: pageAbort.signal };

  const catalog = Array.isArray(window.LABELS_CATALOG) ? window.LABELS_CATALOG : [];
  const prefill = window.LABELS_PREFILL;
  const chave = 'etiquetas33x21.produtos.v1';
  let escolhido = null;

  function noCatalogo(item) {
    return catalog.some((produto) => (
      (item.codigo && produto.codigo && item.codigo === produto.codigo)
      || (!item.codigo && !produto.codigo && item.nome === produto.nome)
    ));
  }

  function mesmoProduto(item, produto) {
    if (produto.codigo && item.codigo === produto.codigo) return true;
    return !produto.codigo && item.nome === produto.nome;
  }

  try {
    const bruta = JSON.parse(localStorage.getItem(chave) || '[]');
    let lista = Array.isArray(bruta) ? bruta.filter(noCatalogo) : [];
    if (prefill && prefill.nome && catalog.some((produto) => mesmoProduto(produto, prefill))) {
      const quantidade = Math.min(9999, Math.max(1, Number(prefill.quantidade) || 9));
      const existente = lista.find((item) => mesmoProduto(item, prefill));
      if (existente) {
        existente.quantidade = quantidade;
        existente.selecionado = true;
        existente.nome = String(prefill.nome).slice(0, 120);
        existente.precoCentavos = Number(prefill.precoCentavos) || 0;
        existente.codigo = String(prefill.codigo || '');
      } else {
        lista.push({
          id: 'erp-' + String(prefill.id || Date.now()),
          nome: String(prefill.nome).slice(0, 120),
          precoCentavos: Number(prefill.precoCentavos) || 0,
          codigo: String(prefill.codigo || ''),
          quantidade,
          selecionado: true,
        });
      }
    }
    localStorage.setItem(chave, JSON.stringify(lista));
  } catch (erro) {
    /* o aviso de armazenamento do app original cobre esta falha */
  }

  function aplicarCatalogo(produto) {
    const nome = document.getElementById('campo-nome');
    const preco = document.getElementById('campo-preco');
    const codigo = document.getElementById('campo-codigo');
    const quantidade = document.getElementById('campo-quantidade');
    const nova = document.getElementById('quantidade-nova');
    if (!nome || !preco || !codigo || !quantidade) return;
    escolhido = produto;
    nome.value = produto.nome;
    preco.value = produto.preco;
    codigo.value = produto.codigo;
    quantidade.value = (nova && nova.value) || '9';
    const resumo = document.getElementById('resumo-catalogo');
    if (resumo) {
      resumo.hidden = false;
      resumo.textContent = produto.nome + ' · R$ ' + produto.preco + (produto.codigo ? ' · ' + produto.codigo : '');
    }
    const botao = document.getElementById('botao-adicionar-catalogo');
    const qtd = quantidade.value || '9';
    if (botao) botao.textContent = 'Adicionar ' + qtd + ' à impressão';
  }

  function localizar(valor) {
    const texto = String(valor || '').trim().toLowerCase();
    if (!texto) return null;
    return catalog.find((item) => (
      item.label.toLowerCase() === texto
      || item.codigo.toLowerCase() === texto
      || String(item.id) === texto
      || item.nome.toLowerCase() === texto
    )) || null;
  }

  function ligarPagina() {
    const busca = document.getElementById('busca-catalogo');
    const nova = document.getElementById('quantidade-nova');
    const adicionar = document.getElementById('botao-adicionar-catalogo');
    const form = document.getElementById('form-produto');
    const dialogo = document.getElementById('dialogo-etiqueta');

    if (busca) {
      busca.addEventListener('change', () => {
        const produto = catalog.find((item) => String(item.id) === String(busca.value));
        if (produto) aplicarCatalogo(produto);
      }, on);
    }

    if (nova) {
      nova.addEventListener('input', () => {
        const qtd = nova.value || '9';
        const campo = document.getElementById('campo-quantidade');
        if (campo && !dialogo?.open) campo.value = qtd;
        if (adicionar) adicionar.textContent = 'Adicionar ' + qtd + ' à impressão';
      }, on);
    }

    if (adicionar) {
      adicionar.addEventListener('click', () => {
        const produto = escolhido || (busca ? localizar(busca.value) : null);
        const status = document.getElementById('status');
        if (!produto) {
          if (status) {
            status.textContent = 'Escolha um produto já cadastrado. Cadastre-o em Produtos e serviços primeiro.';
            status.classList.add('erro');
          }
          const visivel = document.getElementById('busca-catalogo-busca');
          if (visivel) visivel.focus();
          else if (busca) busca.focus();
          return;
        }
        aplicarCatalogo(produto);
        if (form) form.requestSubmit();
      }, on);
    }

    if (form) {
      form.addEventListener('submit', (evento) => {
        const nome = (document.getElementById('campo-nome') || {}).value || '';
        const codigo = (document.getElementById('campo-codigo') || {}).value || '';
        const permitido = catalog.some((item) => (
          (codigo && item.codigo === codigo) || (!codigo && item.nome === nome)
        ));
        if (!permitido) {
          evento.preventDefault();
          evento.stopImmediatePropagation();
          const status = document.getElementById('status');
          if (status) {
            status.textContent = 'Etiqueta disponível somente para produto cadastrado.';
            status.classList.add('erro');
          }
        }
      }, { capture: true, signal: pageAbort.signal });
    }

    if (dialogo) {
      dialogo.addEventListener('click', (evento) => {
        if (evento.target === dialogo) dialogo.close();
      }, on);
      dialogo.addEventListener('close', () => {
        if (document.querySelector('#lista-produtos tr.editando')) {
          const limpar = document.getElementById('botao-limpar');
          if (limpar) limpar.click();
        }
      }, on);
    }

    if (prefill && prefill.nome) {
      const produto = catalog.find((item) => mesmoProduto(item, prefill));
      if (produto) {
        aplicarCatalogo(produto);
        if (busca) busca.value = String(produto.id);
      }
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ligarPagina);
  else ligarPagina();
})();
