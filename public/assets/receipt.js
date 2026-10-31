/*
 * Envia o cupom de 48 mm para a POS-5890U-L pela USB.
 * O Bluetooth do modelo é o clássico, que o navegador não alcança.
 */
(function (raiz) {
  'use strict';

  const CHAVE = 'sp.receipt.usb.v1';
  let conexao = null;

  function usbDisponivel() {
    return !!(raiz.navigator && raiz.navigator.usb);
  }

  function lembrar(dispositivo) {
    try {
      localStorage.setItem(CHAVE, JSON.stringify({
        vendorId: dispositivo.vendorId,
        productId: dispositivo.productId,
      }));
    } catch (e) { /* armazenamento indisponível */ }
  }

  function preferido(lista) {
    let salvo = null;
    try { salvo = JSON.parse(localStorage.getItem(CHAVE) || 'null'); } catch (e) { salvo = null; }
    if (salvo && salvo.vendorId && salvo.productId) {
      const igual = lista.find((d) => d.vendorId === salvo.vendorId && d.productId === salvo.productId);
      if (igual) return igual;
    }
    return lista.find((d) => /5890|thermal|receipt|58mm|pos-58/i.test((d.productName || '') + ' ' + (d.manufacturerName || ''))) || null;
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
    if (escolha.alt.alternateSetting !== escolha.iface.alternate.alternateSetting) {
      try { await dispositivo.selectAlternateInterface(escolha.iface.interfaceNumber, escolha.alt.alternateSetting); } catch (e) { /* a alternativa padrão já serve */ }
    }
    try {
      await dispositivo.claimInterface(escolha.iface.interfaceNumber);
    } catch (e) {
      const err = new Error('O Windows está com o driver desta impressora aberto. Use “Pelo Windows” e escolha a POS-5890 com papel de 58 mm.');
      err.bloqueio = true;
      throw err;
    }
    conexao = { dispositivo, endpoint: escolha.ep.endpointNumber };
    lembrar(dispositivo);
    return conexao;
  }

  async function conectar() {
    if (!usbDisponivel()) {
      const err = new Error('Este navegador não acessa a USB. Abra no Chrome ou no Edge, ou use “Pelo Windows”.');
      err.bloqueio = true;
      throw err;
    }
    if (conexao && conexao.dispositivo.opened) return conexao;
    const conhecidos = await raiz.navigator.usb.getDevices();
    const escolhido = preferido(conhecidos);
    if (escolhido) return abrir(escolhido);
    return abrir(await raiz.navigator.usb.requestDevice({ filters: [] }));
  }

  async function enviar(bytes) {
    const c = await conectar();
    const BLOCO = 2048;
    for (let i = 0; i < bytes.length; i += BLOCO) {
      const r = await c.dispositivo.transferOut(c.endpoint, bytes.subarray(i, i + BLOCO));
      if (r.status !== 'ok') throw new Error('A impressora recusou os dados.');
    }
  }

  function status(root, texto, erro) {
    const el = root.querySelector('.receipt-status');
    if (!el) return;
    el.textContent = texto;
    el.classList.toggle('erro', !!erro);
  }

  function juntar(partes) {
    const total = partes.reduce((soma, parte) => soma + parte.length, 0);
    const out = new Uint8Array(total);
    let o = 0;
    for (const parte of partes) {
      out.set(parte, o);
      o += parte.length;
    }
    return out;
  }

  function desenhar(lista) {
    const R = raiz.SmartPlanetReceipt;
    const W = 384;
    const LH = 26;
    const PAD = 10;
    const H = PAD * 2 + lista.length * LH;
    const canvas = document.createElement('canvas');
    canvas.width = W;
    canvas.height = H;
    const ctx = canvas.getContext('2d', { willReadFrequently: true }) || canvas.getContext('2d');
    if (!ctx) throw new Error('Este navegador não desenhou o cupom.');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, W, H);
    ctx.fillStyle = '#000';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.save();
    ctx.translate(W / 2, H / 2);
    ctx.rotate(-Math.PI / 5);
    ctx.font = '15px "Courier New", Courier, monospace';
    const alcance = Math.ceil(Math.hypot(W, H));
    let faixa = 0;
    for (let y = -alcance; y <= alcance; y += 40) {
      ctx.fillText(faixa % 2 === 0 ? 'SmartPlanet' : R.DOMINIO, 0, y);
      faixa += 1;
    }
    ctx.restore();
    const marca = ctx.getImageData(0, 0, W, H);
    const md = marca.data;
    for (let i = 0; i < md.length; i += 4) {
      if (md[i] > 170) continue;
      const p = i / 4;
      const x = p % W;
      const y = (p / W) | 0;
      if ((x + y) % 5 !== 0) md[i] = md[i + 1] = md[i + 2] = 255;
    }
    ctx.putImageData(marca, 0, 0);
    ctx.fillStyle = '#000';
    ctx.textAlign = 'left';
    ctx.textBaseline = 'middle';
    ctx.font = '20px "Courier New", Courier, monospace';
    const larguraZero = ctx.measureText('0').width || 12;
    const tamanho = 20 * (12 / larguraZero);
    lista.forEach((item, i) => {
      ctx.font = (item.bold ? 'bold ' : '') + tamanho + 'px "Courier New", Courier, monospace';
      ctx.fillText(item.text, 0, PAD + i * LH + LH / 2);
    });
    return canvas;
  }

  function pixels(canvas) {
    const ctx = canvas.getContext('2d', { willReadFrequently: true }) || canvas.getContext('2d');
    const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const out = new Uint8Array(canvas.width * canvas.height);
    for (let i = 0, p = 0; i < img.data.length; i += 4, p++) out[p] = img.data[i] < 150 ? 1 : 0;
    return out;
  }

  function linhasDoSlip(slip) {
    return [...slip.querySelectorAll('.receipt-line')].map((el) => ({
      text: el.textContent || '',
      bold: el.classList.contains('is-bold'),
      via: el.classList.contains('is-via'),
    }));
  }

  function escapar(valor) {
    return String(valor).replace(/[&<>]/g, (c) => (c === '&' ? '&amp;' : c === '<' ? '&lt;' : '&gt;'));
  }

  function slipsMarcados(root) {
    const boxes = [...root.querySelectorAll('[data-receipt-pick]')];
    if (!boxes.length) return [...root.querySelectorAll('.receipt-slip')];
    const ids = new Set(boxes.filter((box) => box.checked).map((box) => box.value));
    return [...root.querySelectorAll('.receipt-slip')].filter((slip) => ids.has(slip.dataset.via));
  }

  function nomeVia(slip) {
    return slip.dataset.via === 'empresa' ? 'via da loja' : 'via do cliente';
  }

  function telas(root) {
    return slipsMarcados(root).map((slip) => desenhar(linhasDoSlip(slip)));
  }

  function trabalho(vias) {
    const R = raiz.SmartPlanetReceipt;
    const corte = Uint8Array.from([0x1b, 0x64, 0x03, 0x1d, 0x56, 0x42, 0x00]);
    const partes = [Uint8Array.from([0x1b, 0x40])];
    for (const via of vias) {
      partes.push(...R.rasterEscPos(pixels(via.canvas), via.canvas.width, via.canvas.height));
      partes.push(corte);
    }
    return juntar(partes);
  }

  function janela(root) {
    const R = raiz.SmartPlanetReceipt;
    const slips = slipsMarcados(root);
    if (!slips.length) {
      status(root, 'Marque a via do cliente, a da loja ou as duas.', true);
      return;
    }
    const doc = window.open('', '_blank', 'width=420,height=720');
    if (!doc) {
      status(root, 'O navegador bloqueou a janela de impressão.', true);
      return;
    }
    const blocos = slips.map((slip) => {
      const linhas = linhasDoSlip(slip);
      const corpo = linhas.map((linha) => {
        const classe = linha.via ? 'via' : (linha.bold ? 'bold' : '');
        return '<div class="' + classe + '">' + escapar(linha.text) + '</div>';
      }).join('');
      return '<section class="slip"><div class="wm"><span>SmartPlanet</span><span>' + escapar(R.DOMINIO) + '</span></div><div class="txt">' + corpo + '</div></section>';
    });
    const altura = slips.reduce((soma, slip) => soma + linhasDoSlip(slip).length * 3.6 + 10, 0);
    doc.document.open();
    doc.document.write(
      '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Cupom 58 mm</title><style>' +
      '@page{size:58mm ' + Math.ceil(altura) + 'mm;margin:0}' +
      'html,body{margin:0;width:58mm;background:#fff;color:#000}' +
      '.slip{position:relative;width:58mm;box-sizing:border-box;padding:2mm 5mm 4mm;page-break-after:always;overflow:hidden}' +
      '.txt{position:relative;z-index:1;width:48mm;margin:0;font:2.4mm/3.4mm "Courier New",Courier,monospace;white-space:pre}' +
      '.bold{font-weight:700}' +
      '.via{font-weight:700;color:#fff;background:#000}' +
      '.wm{position:absolute;left:50%;top:50%;z-index:0;width:160%;transform:translate(-50%,-50%) rotate(-28deg);color:rgba(0,0,0,.28);font:2.6mm/1.8 "Courier New",Courier,monospace;text-align:center}' +
      '.wm span{display:block}' +
      '</style></head><body>' + blocos.join('') + '</body></html>'
    );
    doc.document.close();
    doc.focus();
    setTimeout(() => { doc.print(); }, 250);
  }

  async function imprimir(root) {
    const R = raiz.SmartPlanetReceipt;
    const cru = root.querySelector('#receipt-payload');
    if (!R || !cru) return;
    const botao = root.querySelector('[data-receipt-usb]');
    botao.disabled = true;
    status(root, 'Enviando para a POS-5890U-L…', false);
    try {
      const slips = slipsMarcados(root);
      if (!slips.length) {
        status(root, 'Marque a via do cliente, a da loja ou as duas.', true);
        return;
      }
      await enviar(juntar(slips.map((slip) => R.paraEscPos(linhasDoSlip(slip)))));
      const nomes = slips.map(nomeVia);
      const texto = nomes.length === 1
        ? 'Enviada a ' + nomes[0] + '. Papel 58 mm.'
        : 'Enviadas a ' + nomes[0] + ' e a ' + nomes[1] + '. Papel 58 mm.';
      status(root, texto, false);
    } catch (e) {
      if (e && e.name === 'NotFoundError') {
        status(root, 'Nenhuma impressora escolhida.', true);
      } else {
        status(root, (e && e.message) || 'Não foi possível imprimir.', true);
      }
    } finally {
      botao.disabled = false;
    }
  }

  function htmlDasVias(caixa, vias) {
    const R = raiz.SmartPlanetReceipt;
    caixa.replaceChildren(...vias.map((via) => {
      const col = document.createElement('div');
      col.className = 'receipt-col';
      const escolha = document.createElement('label');
      escolha.className = 'receipt-pick';
      const box = document.createElement('input');
      box.type = 'checkbox';
      box.checked = true;
      box.setAttribute('data-receipt-pick', '');
      box.value = via.id;
      escolha.append(box, document.createTextNode(' ' + via.titulo));
      const slip = document.createElement('article');
      slip.className = 'receipt-slip';
      slip.dataset.via = via.id;
      const marca = document.createElement('div');
      marca.className = 'receipt-watermark';
      marca.setAttribute('aria-hidden', 'true');
      for (let i = 0; i < 3; i++) {
        const nome = document.createElement('span');
        nome.textContent = 'SmartPlanet';
        const dominio = document.createElement('span');
        dominio.textContent = R.DOMINIO;
        marca.append(nome, dominio);
      }
      const texto = document.createElement('div');
      texto.className = 'receipt-text';
      for (const item of via.linhas) {
        const linha = document.createElement('div');
        linha.className = 'receipt-line' + (item.bold ? ' is-bold' : '') + (item.via ? ' is-via' : '');
        linha.textContent = item.text;
        texto.append(linha);
      }
      slip.append(marca, texto);
      col.append(escolha, slip);
      return col;
    }));
  }

  function mount(root) {
    if (!root || root.dataset.mounted === '1') return;
    const R = raiz.SmartPlanetReceipt;
    const cru = root.querySelector('#receipt-payload');
    const caixa = root.querySelector('.receipt-vias');
    if (!caixa) return;
    root.dataset.mounted = '1';
    if (!caixa.querySelector('.receipt-line') && R && cru) {
      try { htmlDasVias(caixa, R.vias(JSON.parse(cru.textContent))); } catch (e) { /* o HTML do servidor permanece */ }
    }
    const botao = root.querySelector('[data-receipt-usb]');
    if (botao) botao.addEventListener('click', () => { imprimir(root); });
    const janelaBotao = root.querySelector('[data-receipt-window]');
    if (janelaBotao) janelaBotao.addEventListener('click', () => { janela(root); });
  }

  function montarTodos() {
    document.querySelectorAll('.receipt').forEach(mount);
  }

  raiz.SmartPlanetReceipt = Object.assign(raiz.SmartPlanetReceipt || {}, { mount, montarTodos });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', montarTodos);
  else montarTodos();

  if (usbDisponivel() && !raiz.__spReceiptUsb) {
    raiz.__spReceiptUsb = true;
    raiz.navigator.usb.addEventListener('disconnect', (e) => {
      if (conexao && e.device === conexao.dispositivo) conexao = null;
    });
  }
})(window);
