/*
 * Impressão direta na 4B-2054N / MDK-2054N em TSPL.
 * Cada fileira é desenhada em um bitmap de 8 pontos/mm (203 dpi) e enviada com
 * SIZE/GAP próprios, sem passar pela escala ou pelo tamanho de papel do driver.
 */
(function (raiz) {
  'use strict';

  const L = raiz.EtqLayout;
  const B = raiz.EtqBarcode;
  const D = 8; // pontos por mm
  const PT = 25.4 / 72; // mm por ponto tipográfico
  const FONTE = 'Arial, Helvetica, "Liberation Sans", sans-serif';
  const pt = (v) => v * PT * D;

  /* ---------- Desenho ---------- */

  function quebrarLinhas(ctx, texto, largura) {
    const cabe = (s) => ctx.measureText(s).width <= largura;
    const linhas = [];
    let atual = '';
    for (let palavra of texto.split(/\s+/).filter(Boolean)) {
      const tentativa = atual ? atual + ' ' + palavra : palavra;
      if (cabe(tentativa)) {
        atual = tentativa;
        continue;
      }
      if (atual) linhas.push(atual);
      atual = '';
      while (!cabe(palavra) && palavra.length > 1) {
        let i = 1;
        while (i < palavra.length - 1 && cabe(palavra.slice(0, i + 1))) i++;
        linhas.push(palavra.slice(0, i));
        palavra = palavra.slice(i);
      }
      atual = palavra;
    }
    if (atual) linhas.push(atual);
    return linhas;
  }

  function desenharEtiqueta(ctx, produto, cfg, x, y) {
    const P = cfg.margemInterna * D;
    const W = L.ETIQUETA.largura * D;
    const H = L.ETIQUETA.altura * D;
    const util = W - 2 * P;
    const centro = cfg.alinhamentoTexto === 'center';
    const info = { nomeCortado: false, codigoDenso: false };

    ctx.save();
    ctx.beginPath();
    ctx.rect(x, y, W, H);
    ctx.clip();
    ctx.fillStyle = '#000';
    ctx.textBaseline = 'alphabetic';
    let base = y + H - P;

    const enc = produto.codigo ? B.codificar(produto.codigo) : null;
    if (enc) {
      if (cfg.mostrarDigitos) {
        const fs = pt(5.5);
        ctx.font = `bold ${fs}px ${FONTE}`;
        ctx.textAlign = 'center';
        ctx.fillText(enc.texto, x + W / 2, base);
        base -= fs * 0.75 + 0.3 * D;
      }
      const disponivel = (util * cfg.larguraCodigo) / 100;
      let m = Math.min(3, Math.floor(disponivel / (enc.bits.length + 10)));
      if (m < 1) {
        m = 1;
        info.codigoDenso = true;
      }
      const bw = enc.bits.length * m;
      const bh = Math.round(cfg.alturaCodigo * D);
      const bx = Math.round(x + (W - bw) / 2);
      const by = Math.round(base - bh);
      for (let i = 0; i < enc.bits.length; ) {
        if (enc.bits[i] === '1') {
          let j = i;
          while (j < enc.bits.length && enc.bits[j] === '1') j++;
          ctx.fillRect(bx + i * m, by, (j - i) * m, bh);
          i = j;
        } else i++;
      }
      base = by - 0.6 * D;
    }

    const valor = L.formatarValor(produto.precoCentavos);
    const medirPreco = (s) => {
      ctx.font = `bold ${s * 0.6}px ${FONTE}`;
      const a = ctx.measureText('R$').width;
      ctx.font = `bold ${s}px ${FONTE}`;
      const b = ctx.measureText(valor).width;
      const esp = s * 0.15;
      return { a, b, esp, total: a + esp + b };
    };
    let ps = pt(cfg.fontePreco);
    let mp = medirPreco(ps);
    while (ps > pt(6) && mp.total > util) {
      ps -= pt(0.5);
      mp = medirPreco(ps);
    }
    const px = centro ? x + W / 2 - mp.total / 2 : x + P;
    ctx.textAlign = 'left';
    ctx.font = `bold ${ps * 0.6}px ${FONTE}`;
    ctx.fillText('R$', px, base);
    ctx.font = `bold ${ps}px ${FONTE}`;
    ctx.fillText(valor, px + mp.a + mp.esp, base);
    base -= ps * 0.72 + 0.6 * D;

    const topo = y + P;
    const area = base - topo;
    let ns = pt(cfg.fonteNome);
    let linhas;
    for (;;) {
      ctx.font = `bold ${ns}px ${FONTE}`;
      linhas = quebrarLinhas(ctx, produto.nome, util);
      if (linhas.length * ns * 1.08 <= area || ns <= pt(5)) break;
      ns = Math.max(pt(5), ns - pt(0.25));
    }
    const lh = ns * 1.08;
    const maxLinhas = Math.floor(area / lh);
    if (linhas.length > maxLinhas) {
      info.nomeCortado = true;
      linhas = linhas.slice(0, Math.max(0, maxLinhas));
      if (linhas.length) {
        let ultima = linhas[linhas.length - 1];
        while (ultima.length > 1 && ctx.measureText(ultima + '…').width > util) ultima = ultima.slice(0, -1);
        linhas[linhas.length - 1] = ultima.trimEnd() + '…';
      }
    }
    const inicio = topo + (area - linhas.length * lh) / 2;
    ctx.textAlign = centro ? 'center' : 'left';
    linhas.forEach((linha, i) => ctx.fillText(linha, centro ? x + W / 2 : x + P, inicio + i * lh + ns * 0.89));

    ctx.restore();
    return info;
  }

  function desenharTeste(ctx, coluna, x, y) {
    const W = L.ETIQUETA.largura * D;
    const H = L.ETIQUETA.altura * D;
    const t = 2;
    ctx.save();
    ctx.fillStyle = '#000';
    ctx.fillRect(x, y, W, t);
    ctx.fillRect(x, y + H - t, W, t);
    ctx.fillRect(x, y, t, H);
    ctx.fillRect(x + W - t, y, t, H);
    ctx.fillRect(x + W / 2 - 16, y + H / 2 - 1, 32, t);
    ctx.fillRect(x + W / 2 - 1, y + H / 2 - 16, t, 32);
    ctx.textAlign = 'center';
    ctx.textBaseline = 'alphabetic';
    ctx.font = `bold ${pt(6.5)}px ${FONTE}`;
    ctx.fillText('TESTE 33 × 21 mm', x + W / 2, y + 1.6 * D + pt(6.5) * 0.72);
    ctx.font = `bold ${pt(7.5)}px ${FONTE}`;
    ctx.fillText(`COLUNA ${coluna}`, x + W / 2, y + H - 1.6 * D);
    ctx.restore();
  }

  /** Desenha uma fileira em um canvas do tamanho exato da página TSPL. */
  function desenharFileira(fileira, cfg) {
    const pag = L.tamanhoPagina({ ...cfg, modoPagina: 'fileira' }, 1);
    const wd = Math.ceil(Math.round(pag.largura * D) / 8) * 8;
    const hd = Math.round(pag.altura * D);
    const canvas = document.createElement('canvas');
    canvas.width = wd;
    canvas.height = hd;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, wd, hd);
    const cols = L.posicoesColunas(cfg);
    const y = Math.round((cfg.margemSuperior + cfg.deslocY) * D);
    const avisos = [];
    fileira.forEach((celula, i) => {
      if (!celula) return;
      const x = Math.round(cols[i] * D);
      if (celula.teste) desenharTeste(ctx, celula.coluna, x, y);
      else avisos.push(desenharEtiqueta(ctx, celula.produto, cfg, x, y));
    });
    return { canvas, wd, hd, avisos };
  }

  /** Converte para 1 bit por ponto. TSPL: bit 0 = ponto preto (ou o inverso, se `inverter`). */
  function paraBitmap(canvas, inverter) {
    const w = canvas.width;
    const h = canvas.height;
    const wb = w / 8;
    const dados = canvas.getContext('2d').getImageData(0, 0, w, h).data;
    const out = new Uint8Array(wb * h).fill(inverter ? 0x00 : 0xff);
    for (let yy = 0; yy < h; yy++) {
      for (let xx = 0; xx < w; xx++) {
        const i = (yy * w + xx) * 4;
        const lum = (dados[i] * 299 + dados[i + 1] * 587 + dados[i + 2] * 114) / 1000;
        if (lum < 150) {
          const byte = yy * wb + (xx >> 3);
          const bit = 0x80 >> (xx & 7);
          if (inverter) out[byte] |= bit;
          else out[byte] &= ~bit;
        }
      }
    }
    return out;
  }

  /** Canvas preto e branco puro (o que a cabeça térmica vai imprimir), com contornos das etiquetas para referência. */
  function canvasPrevia(fileira, cfg) {
    const { canvas, wd, hd } = desenharFileira(fileira, cfg);
    const ctx = canvas.getContext('2d');
    const cols = L.posicoesColunas(cfg);
    const y0 = Math.round(cfg.margemSuperior * D);
    const dentroEtiqueta = (x, y) =>
      y >= y0 && y < y0 + L.ETIQUETA.altura * D && cols.some((c) => x >= Math.round(c * D) && x < Math.round(c * D) + L.ETIQUETA.largura * D);
    const img = ctx.getImageData(0, 0, wd, hd);
    const d = img.data;
    for (let i = 0; i < d.length; i += 4) {
      const lum = (d[i] * 299 + d[i + 1] * 587 + d[i + 2] * 114) / 1000;
      const p = i / 4;
      if (lum < 150) d[i] = d[i + 1] = d[i + 2] = 0;
      else if (dentroEtiqueta(p % wd, Math.floor(p / wd))) d[i] = d[i + 1] = d[i + 2] = 255;
      else [d[i], d[i + 1], d[i + 2]] = [236, 230, 214];
      d[i + 3] = 255;
    }
    ctx.putImageData(img, 0, 0);
    ctx.strokeStyle = 'rgba(31, 94, 255, 0.55)';
    ctx.lineWidth = 1;
    ctx.setLineDash([4, 3]);
    cols.forEach((c, i) => {
      if (!fileira[i]) {
        ctx.fillStyle = 'rgba(255,255,255,0.5)';
        ctx.fillRect(Math.round(c * D), 0, L.ETIQUETA.largura * D, hd);
      }
      ctx.strokeRect(Math.round(c * D) + 0.5, y0 + 0.5, L.ETIQUETA.largura * D - 1, L.ETIQUETA.altura * D - 1);
    });
    return canvas;
  }

  /* ---------- Comandos ---------- */

  const texto = (s) => new TextEncoder().encode(s);

  function cabecalho(cfg) {
    const pag = L.tamanhoPagina({ ...cfg, modoPagina: 'fileira' }, 1);
    return (
      `SIZE ${pag.largura} mm,${pag.altura} mm\r\n` +
      `GAP ${cfg.espacoVertical} mm,0 mm\r\n` +
      `DIRECTION ${cfg.tsplGirar ? 0 : 1},0\r\n` +
      'REFERENCE 0,0\r\n' +
      'OFFSET 0 mm\r\n' +
      'SET TEAR ON\r\n' +
      `SPEED ${cfg.tsplVelocidade}\r\n` +
      `DENSITY ${cfg.tsplDensidade}\r\n`
    );
  }

  function chaveFileira(fileira) {
    return fileira.map((c) => (!c ? '-' : c.teste ? 't' + c.coluna : 'p' + c.produto.id)).join('|');
  }

  /** Gera o trabalho TSPL completo. Fileiras idênticas consecutivas viram um único PRINT com cópias. */
  function gerarTrabalho(fileiras, cfg) {
    const partes = [texto(cabecalho(cfg))];
    let i = 0;
    let paginas = 0;
    while (i < fileiras.length) {
      const chave = chaveFileira(fileiras[i]);
      let n = 1;
      while (i + n < fileiras.length && chaveFileira(fileiras[i + n]) === chave) n++;
      const { canvas, wd, hd } = desenharFileira(fileiras[i], cfg);
      partes.push(texto(`CLS\r\nBITMAP 0,0,${wd / 8},${hd},0,`));
      partes.push(paraBitmap(canvas, cfg.tsplInverter));
      partes.push(texto(`\r\nPRINT 1,${n}\r\n`));
      paginas += n;
      i += n;
    }
    const total = partes.reduce((s, p) => s + p.length, 0);
    const bytes = new Uint8Array(total);
    let pos = 0;
    for (const p of partes) {
      bytes.set(p, pos);
      pos += p.length;
    }
    return { bytes, paginas };
  }

  const comandoCalibrar = () => texto('GAPDETECT\r\n');
  const comandoAvancar = (cfg) => texto(cabecalho(cfg) + 'FORMFEED\r\n');

  /* ---------- USB ---------- */

  let conexao = null;

  function usbDisponivel() {
    return !!(raiz.navigator && raiz.navigator.usb);
  }

  async function abrir(dispositivo) {
    if (!dispositivo.opened) await dispositivo.open();
    if (dispositivo.configuration === null) await dispositivo.selectConfiguration(1);
    let escolha = null;
    for (const iface of dispositivo.configuration.interfaces) {
      for (const alt of iface.alternates) {
        const ep = alt.endpoints.find((e) => e.direction === 'out' && e.type === 'bulk');
        if (!ep) continue;
        if (!escolha || alt.interfaceClass === 7) escolha = { iface, alt, ep };
      }
    }
    if (!escolha) throw new Error('A impressora não expôs uma saída USB de dados.');
    try {
      await dispositivo.claimInterface(escolha.iface.interfaceNumber);
    } catch (e) {
      const err = new Error(
        'O sistema não liberou a impressora para o navegador. No Windows, o driver instalado bloqueia o acesso USB direto: ' +
        'use “Baixar arquivo .prn” ou a impressão pela janela do navegador.'
      );
      err.bloqueio = true;
      throw err;
    }
    conexao = { dispositivo, endpoint: escolha.ep.endpointNumber };
    return conexao;
  }

  async function conectar(pedirAoUsuario) {
    if (!usbDisponivel()) {
      throw new Error('Este navegador não permite acesso USB direto. Use Google Chrome ou Microsoft Edge (não funciona no Safari nem no Firefox).');
    }
    if (conexao && conexao.dispositivo.opened) return conexao;
    const conhecidos = await raiz.navigator.usb.getDevices();
    if (conhecidos.length) return abrir(conhecidos[0]);
    if (!pedirAoUsuario) return null;
    const dispositivo = await raiz.navigator.usb.requestDevice({ filters: [] });
    return abrir(dispositivo);
  }

  async function enviar(bytes) {
    const c = await conectar(true);
    const BLOCO = 16384;
    for (let i = 0; i < bytes.length; i += BLOCO) {
      const r = await c.dispositivo.transferOut(c.endpoint, bytes.subarray(i, i + BLOCO));
      if (r.status !== 'ok') throw new Error('A impressora recusou os dados (status ' + r.status + ').');
    }
  }

  function nomeConectado() {
    if (!conexao || !conexao.dispositivo.opened) return null;
    const d = conexao.dispositivo;
    return [d.manufacturerName, d.productName].filter(Boolean).join(' ') || 'Impressora USB';
  }

  if (usbDisponivel()) {
    raiz.navigator.usb.addEventListener('disconnect', (e) => {
      if (conexao && e.device === conexao.dispositivo) conexao = null;
    });
  }

  raiz.EtqTspl = {
    desenharFileira,
    canvasPrevia,
    gerarTrabalho,
    comandoCalibrar,
    comandoAvancar,
    usbDisponivel,
    conectar,
    enviar,
    nomeConectado,
  };
})(window);
