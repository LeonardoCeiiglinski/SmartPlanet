/*
 * Gerador de códigos de barras sem dependências.
 * EAN-13 (13 dígitos com verificador válido), UPC-A (12 dígitos válidos) e Code 128 (demais).
 * Resultado: sequência de módulos ('1' = barra, '0' = espaço), sem zonas de silêncio.
 */
(function (raiz, fabrica) {
  const api = fabrica();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else raiz.EtqBarcode = api;
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  // Larguras barra/espaço dos símbolos 0–105 e do STOP (106).
  const PADROES_128 = [
    '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
    '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
    '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
    '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
    '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
    '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
    '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
    '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
    '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
    '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
    '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
  ];
  const START_B = 104;
  const START_C = 105;
  const CODE_C = 99;
  const STOP = 106;

  const EAN_L = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'];
  const EAN_R = EAN_L.map((p) => p.replace(/./g, (b) => (b === '0' ? '1' : '0')));
  const EAN_G = EAN_R.map((p) => p.split('').reverse().join(''));
  const PARIDADE = ['LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG', 'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL'];

  const LIMITE = 24;

  function digitoEan(doze) {
    let soma = 0;
    for (let i = 0; i < 12; i++) soma += Number(doze[i]) * (i % 2 === 0 ? 1 : 3);
    return String((10 - (soma % 10)) % 10);
  }

  function eanValido(codigo) {
    return /^\d{13}$/.test(codigo) && digitoEan(codigo.slice(0, 12)) === codigo[12];
  }

  function validar(texto) {
    const t = String(texto == null ? '' : texto).trim();
    if (!t) return { ok: true, vazio: true, tipo: null };
    if (t.length > LIMITE) return { ok: false, erro: `Use no máximo ${LIMITE} caracteres no código de barras.` };
    if (!/^[\x20-\x7e]+$/.test(t)) {
      return { ok: false, erro: 'O código aceita apenas números, letras sem acento e símbolos básicos.' };
    }
    if (/^\d{13}$/.test(t)) {
      if (eanValido(t)) return { ok: true, tipo: 'EAN-13' };
      return {
        ok: true,
        tipo: 'Code 128',
        aviso: 'Dígito verificador de EAN-13 não confere; será impresso como Code 128 com os mesmos números.',
      };
    }
    if (/^\d{12}$/.test(t) && eanValido('0' + t)) return { ok: true, tipo: 'UPC-A' };
    return { ok: true, tipo: 'Code 128' };
  }

  function valoresCode128(t) {
    const valores = [];
    if (/^\d+$/.test(t) && t.length >= 2) {
      let i = 0;
      if (t.length % 2 === 1) {
        valores.push(START_B, t.charCodeAt(0) - 32, CODE_C);
        i = 1;
      } else {
        valores.push(START_C);
      }
      for (; i < t.length; i += 2) valores.push(parseInt(t.substr(i, 2), 10));
    } else {
      valores.push(START_B);
      for (const ch of t) valores.push(ch.charCodeAt(0) - 32);
    }
    let soma = valores[0];
    for (let k = 1; k < valores.length; k++) soma += valores[k] * k;
    valores.push(soma % 103);
    return valores;
  }

  function bitsDeLarguras(larguras) {
    let bits = '';
    let barra = true;
    for (const ch of larguras) {
      bits += (barra ? '1' : '0').repeat(Number(ch));
      barra = !barra;
    }
    return bits;
  }

  function bitsEan13(c) {
    const paridade = PARIDADE[Number(c[0])];
    let bits = '101';
    for (let i = 1; i <= 6; i++) bits += (paridade[i - 1] === 'L' ? EAN_L : EAN_G)[Number(c[i])];
    bits += '01010';
    for (let i = 7; i <= 12; i++) bits += EAN_R[Number(c[i])];
    return bits + '101';
  }

  function codificar(texto) {
    const v = validar(texto);
    if (!v.ok || v.vazio) return null;
    const t = String(texto).trim();
    if (v.tipo === 'EAN-13') return { tipo: 'EAN-13', bits: bitsEan13(t), texto: t };
    if (v.tipo === 'UPC-A') return { tipo: 'UPC-A', bits: bitsEan13('0' + t), texto: t };
    const valores = valoresCode128(t);
    const larguras = valores.map((x) => PADROES_128[x]).join('') + PADROES_128[STOP];
    return { tipo: 'Code 128', bits: bitsDeLarguras(larguras), texto: t, valores };
  }

  return { LIMITE, PADROES_128, validar, codificar, eanValido, digitoEan, valoresCode128 };
});
