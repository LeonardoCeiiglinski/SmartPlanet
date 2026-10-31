/*
 * Cupom da POS-5890U-L.
 * Papel 58 mm, área impressa 48 mm, 203 dpi, fonte A de 12 pontos:
 * 32 colunas × 12 pontos = 384 pontos = 48 mm. ESC/POS, página Windows-1252.
 */
(function (raiz) {
  'use strict';

  const COL = 32;
  const DOMINIO = 'lojadeeletronicossaojose.com.br';
  const VIAS = [
    { id: 'cliente', titulo: 'VIA DO CLIENTE', guarda: 'Fica com o cliente' },
    { id: 'empresa', titulo: 'VIA DA LOJA', guarda: 'Fica com a loja' },
  ];

  function chars(s) {
    return Array.from(String(s ?? ''));
  }

  function dinheiro(v) {
    const n = Math.round(Number(v) * 100);
    const neg = n < 0;
    const abs = Math.abs(Number.isFinite(n) ? n : 0);
    const inteiro = Math.floor(abs / 100);
    const cent = String(abs % 100).padStart(2, '0');
    const corpo = String(inteiro).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return (neg ? '-' : '') + 'R$ ' + corpo + ',' + cent;
  }

  function qtd(v) {
    const n = Number(v);
    if (!Number.isFinite(n)) return String(v ?? '');
    if (Math.abs(n - Math.round(n)) < 1e-6) return String(Math.round(n));
    return n.toFixed(3).replace('.', ',').replace(/0+$/, '').replace(/,$/, '');
  }

  function dataHora(valor) {
    const m = String(valor ?? '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    if (!m) return String(valor ?? '');
    return m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5];
  }

  function quebrar(texto, limite) {
    const lim = Math.max(1, limite);
    const palavras = String(texto ?? '').replace(/\s+/g, ' ').trim().split(' ').filter(Boolean);
    if (!palavras.length) return [];
    const linhas = [];
    let atual = '';
    const empurra = (s) => { if (s) linhas.push(s); };
    for (let palavra of palavras) {
      while (chars(palavra).length > lim) {
        if (atual) { empurra(atual); atual = ''; }
        empurra(chars(palavra).slice(0, lim).join(''));
        palavra = chars(palavra).slice(lim).join('');
      }
      const tentativa = atual ? atual + ' ' + palavra : palavra;
      if (chars(tentativa).length <= lim) atual = tentativa;
      else { empurra(atual); atual = palavra; }
    }
    empurra(atual);
    return linhas;
  }

  function preencher(esq, dir) {
    const e = chars(esq);
    const d = chars(dir || '');
    if (d.length >= COL) return d.slice(0, COL).join('');
    const maxE = COL - d.length;
    const corte = e.slice(0, maxE);
    return corte.join('') + ' '.repeat(COL - corte.length - d.length) + d.join('');
  }

  function centro(texto) {
    return quebrar(texto, COL).map((p) => {
      const t = chars(p);
      const sobra = COL - t.length;
      const esq = Math.floor(sobra / 2);
      return ' '.repeat(esq) + t.join('') + ' '.repeat(sobra - esq);
    });
  }

  function linha(texto, bold, via) {
    const t = chars(texto);
    const base = t.length === COL ? t.join('') : preencher(t.join(''), '');
    return { text: base, bold: !!bold, via: !!via };
  }

  function coluna(esq, dir, bold) {
    const direita = String(dir);
    const larguraDir = chars(direita).length;
    const maxE = Math.max(1, COL - larguraDir - 1);
    const blocos = quebrar(esq, maxE);
    const lista = blocos.length ? blocos : [''];
    return lista.map((b, i) => linha(i === lista.length - 1 ? preencher(b, direita) : b, bold));
  }

  function linhas(recibo, viaTitulo, guarda) {
    const out = [];
    const add = (itens) => { for (const item of itens) out.push(typeof item === 'string' ? linha(item, false) : item); };
    if (viaTitulo) add(centro(viaTitulo).map((t) => linha(t, true, true)));
    if (guarda) add(centro(guarda).map((t) => linha(t, true, false)));
    const marca = String(recibo.tradeName || 'SmartPlanet').trim() || 'SmartPlanet';
    const baixa = marca.toLowerCase();
    add(centro(marca).map((t) => linha(t, true)));
    if (!baixa.includes('smartplanet')) add(centro('SmartPlanet'));
    if (!baixa.includes('boutique')) add(centro('a Boutique do Celular'));
    if (recibo.phone) add(centro(recibo.phone));
    for (const endereco of recibo.address || []) add(centro(endereco));
    add([linha('-'.repeat(COL), false)]);
    add(centro(recibo.status === 'cancelled' ? 'RECIBO CANCELADO' : 'RECIBO NÃO FISCAL').map((t) => linha(t, true)));
    add(quebrar(recibo.saleNumber || '', COL).map((t) => linha(t, false)));
    if (recibo.completedAt) add([linha(dataHora(recibo.completedAt), false)]);
    if (recibo.operator) add(quebrar('Operador: ' + recibo.operator, COL).map((t) => linha(t, false)));
    add(quebrar('Cliente: ' + (String(recibo.customer || '').trim() || 'Consumidor final'), COL).map((t) => linha(t, false)));
    add([linha('-'.repeat(COL), false)]);
    for (const item of recibo.items || []) {
      add(quebrar(item.description || 'Item', COL).map((t) => linha(t, false)));
      const q = qtd(item.quantity);
      const unit = dinheiro(item.unitPrice);
      add(coluna(q + ' x ' + unit, dinheiro(item.subtotal), false));
    }
    add([linha('-'.repeat(COL), false)]);
    if (Number(recibo.discount) > 0) add(coluna('Desconto', dinheiro(recibo.discount), false));
    add(coluna('TOTAL', dinheiro(recibo.total), true));
    for (const pag of recibo.payments || []) {
      const nome = (pag.name || 'Pagamento') + (Number(pag.installments) > 1 ? ' ' + pag.installments + 'x' : '');
      add(coluna(nome, dinheiro(pag.amount), false));
    }
    add([linha('-'.repeat(COL), false)]);
    if (viaTitulo) add(centro(viaTitulo).map((t) => linha(t, true, false)));
    add(centro('Não é documento fiscal').map((t) => linha(t, false)));
    add(centro(DOMINIO).map((t) => linha(t, false)));
    return out;
  }

  function vias(recibo) {
    return VIAS.map((via) => ({
      id: via.id,
      titulo: via.titulo,
      linhas: linhas(recibo, via.titulo, via.guarda),
    }));
  }

  function rasterEscPos(pixels, width, height) {
    if (width % 8 !== 0) throw new Error('A largura do cupom precisa ser múltipla de 8 pontos.');
    const bytesPorLinha = width / 8;
    const partes = [];
    const MAX = 128;
    for (let y0 = 0; y0 < height; y0 += MAX) {
      const rows = Math.min(MAX, height - y0);
      const dados = new Uint8Array(8 + rows * bytesPorLinha);
      dados[0] = 0x1d;
      dados[1] = 0x76;
      dados[2] = 0x30;
      dados[3] = 0x00;
      dados[4] = bytesPorLinha & 255;
      dados[5] = (bytesPorLinha >> 8) & 255;
      dados[6] = rows & 255;
      dados[7] = (rows >> 8) & 255;
      let o = 8;
      for (let y = 0; y < rows; y++) {
        for (let xb = 0; xb < bytesPorLinha; xb++) {
          let b = 0;
          const base = (y0 + y) * width + xb * 8;
          for (let bit = 0; bit < 8; bit++) {
            if (pixels[base + bit]) b |= 0x80 >> bit;
          }
          dados[o++] = b;
        }
      }
      partes.push(dados);
    }
    return partes;
  }

  function cp1252(texto) {
    const bytes = [];
    for (const ch of chars(texto)) {
      const c = ch.codePointAt(0);
      bytes.push(c <= 0xff ? c : 0x3f);
    }
    return bytes;
  }

  function paraEscPos(lista) {
    const bytes = [
      0x1b, 0x40,
      0x1b, 0x74, 0x10,
      0x1b, 0x52, 0x00,
      0x1b, 0x4d, 0x00,
      0x1b, 0x21, 0x00,
      0x1d, 0x21, 0x00,
      0x1b, 0x61, 0x00,
    ];
    for (const item of lista) {
      bytes.push(0x1d, 0x21, item.via ? 0x01 : 0x00);
      bytes.push(0x1d, 0x42, item.via ? 0x01 : 0x00);
      bytes.push(0x1b, 0x45, item.bold || item.via ? 0x01 : 0x00);
      bytes.push(...cp1252(item.text), 0x0a);
    }
    bytes.push(0x1d, 0x21, 0x00, 0x1d, 0x42, 0x00, 0x1b, 0x45, 0x00, 0x1b, 0x64, 0x03, 0x1d, 0x56, 0x42, 0x00);
    return Uint8Array.from(bytes);
  }

  const api = {
    COLUNAS: COL,
    PAPEL_MM: 58,
    IMPRESSAO_MM: 48,
    MODELO: 'POS-5890U-L',
    DOMINIO,
    VIAS,
    linhas,
    vias,
    rasterEscPos,
    paraEscPos,
    dinheiro,
    qtd,
  };
  raiz.SmartPlanetReceipt = Object.assign(raiz.SmartPlanetReceipt || {}, api);
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
