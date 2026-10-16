// Testes de lógica pura. Executar com: node tests/unit-tests.js
'use strict';
const assert = require('assert');
const L = require('../js/layout.js');
const B = require('../js/barcode.js');

let falhas = 0;
function teste(nome, fn) {
  try {
    fn();
    console.log(`  ok   ${nome}`);
  } catch (e) {
    falhas++;
    console.log(`  FALHA ${nome}\n       ${e.message}`);
  }
}

console.log('Code 128');
teste('tabela tem 107 padrões únicos com somas corretas', () => {
  assert.strictEqual(B.PADROES_128.length, 107);
  assert.strictEqual(new Set(B.PADROES_128).size, 107);
  B.PADROES_128.forEach((p, i) => {
    const soma = [...p].reduce((s, c) => s + Number(c), 0);
    assert.strictEqual(soma, i === 106 ? 13 : 11, `padrão ${i}`);
  });
});

function decodificar128(bits) {
  // Converte módulos em larguras e mapeia de volta para valores.
  const larg = [];
  for (let i = 0; i < bits.length; ) {
    let j = i;
    while (j < bits.length && bits[j] === bits[i]) j++;
    larg.push(j - i);
    i = j;
  }
  const valores = [];
  for (let i = 0; i + 6 <= larg.length; i += 6) {
    const p = larg.slice(i, i + 6).join('');
    if (i + 7 === larg.length) {
      assert.strictEqual(larg.slice(i).join(''), '2331112', 'STOP');
      break;
    }
    const v = B.PADROES_128.indexOf(p);
    assert.ok(v >= 0, `padrão desconhecido ${p}`);
    valores.push(v);
  }
  const check = valores.pop();
  let soma = valores[0];
  for (let k = 1; k < valores.length; k++) soma += valores[k] * k;
  assert.strictEqual(soma % 103, check, 'dígito de verificação');
  let modo = valores[0] === 105 ? 'C' : 'B';
  let texto = '';
  for (const v of valores.slice(1)) {
    if (v === 99) { modo = 'C'; continue; }
    if (v === 100) { modo = 'B'; continue; }
    texto += modo === 'C' ? String(v).padStart(2, '0') : String.fromCharCode(v + 32);
  }
  return texto;
}

for (const t of ['ABC-123', '12345678', '1234567', '7891234567890', 'x', '5', 'Produto 42/A']) {
  teste(`ida e volta Code 128: "${t}"`, () => {
    const c = B.codificar(t);
    assert.strictEqual(c.tipo, 'Code 128');
    assert.strictEqual(c.bits.length, (c.valores.length + 1) * 11 + 2);
    assert.strictEqual(decodificar128(c.bits), t);
  });
}

console.log('EAN-13 / UPC-A');
teste('dígito verificador', () => {
  assert.strictEqual(B.digitoEan('400638133393'), '1');
  assert.strictEqual(B.digitoEan('789123456789'), '5');
  assert.ok(B.eanValido('4006381333931'));
  assert.ok(!B.eanValido('4006381333932'));
});
teste('EAN-13 válido gera 95 módulos com guardas', () => {
  const c = B.codificar('4006381333931');
  assert.strictEqual(c.tipo, 'EAN-13');
  assert.strictEqual(c.bits.length, 95);
  assert.strictEqual(c.bits.slice(0, 3), '101');
  assert.strictEqual(c.bits.slice(45, 50), '01010');
  assert.strictEqual(c.bits.slice(-3), '101');
  // Primeiro dígito 4 => paridade LGLLGG; segundo dígito 0 em L = 0001101
  assert.strictEqual(c.bits.slice(3, 10), '0001101');
});
teste('EAN-13 com verificador errado vira Code 128 com aviso', () => {
  const v = B.validar('4006381333932');
  assert.strictEqual(v.tipo, 'Code 128');
  assert.ok(v.aviso);
});
teste('UPC-A válido usa EAN-13 com zero à esquerda', () => {
  const c = B.codificar('036000291452');
  assert.strictEqual(c.tipo, 'UPC-A');
  assert.strictEqual(c.bits.length, 95);
});
teste('código vazio não gera barras; acentos e excesso são rejeitados', () => {
  assert.strictEqual(B.codificar(''), null);
  assert.ok(B.validar('').vazio);
  assert.ok(!B.validar('AÇÃO').ok);
  assert.ok(!B.validar('1'.repeat(25)).ok);
});

console.log('Preço');
teste('interpretação de preços', () => {
  const casos = {
    '12,90': 1290, '12,9': 1290, '12': 1200, '12.90': 1290, 'R$ 1.234,56': 123456,
    '1.500': 150000, '0,99': 99, '1.50': 150, '1234.5': 123450,
  };
  for (const [t, c] of Object.entries(casos)) assert.strictEqual(L.parsePreco(t), c, t);
  for (const t of ['', 'abc', '12,345', '1,2,3', '-5', '12.3.4', '12,']) assert.strictEqual(L.parsePreco(t), null, t);
});
teste('formatação em reais', () => {
  assert.strictEqual(L.formatarPreco(1290), 'R$\u00a012,90');
  assert.strictEqual(L.formatarValor(123456), '1.234,56');
  assert.strictEqual(L.formatarValor(5), '0,05');
});

console.log('Fileiras e dimensões');
const cfg = L.configPadrao();
const produto = { id: 'p', nome: 'X', precoCentavos: 100, codigo: '', quantidade: 0 };
const esperado = { 1: 1, 3: 1, 6: 2, 9: 3, 12: 4 };
for (const [qtd, fileirasEsperadas] of Object.entries(esperado)) {
  teste(`quantidade ${qtd} => ${fileirasEsperadas} fileira(s), sem duplicatas`, () => {
    const { fileiras, totalEtiquetas } = L.montarFileiras([{ ...produto, quantidade: Number(qtd) }], cfg);
    assert.strictEqual(totalEtiquetas, Number(qtd));
    assert.strictEqual(fileiras.length, fileirasEsperadas);
    fileiras.forEach((f) => assert.strictEqual(f.length, 3));
    const indices = fileiras.flat().filter(Boolean).map((c) => c.indice);
    assert.deepStrictEqual(indices, [...Array(Number(qtd)).keys()]);
    const vazias = fileiras.flat().filter((c) => c === null).length;
    assert.strictEqual(vazias, fileirasEsperadas * 3 - Number(qtd));
    // Posições vazias só podem estar no fim da última fileira
    const plano = fileiras.flat();
    assert.ok(plano.slice(0, Number(qtd)).every(Boolean));
  });
}
teste('vários produtos preenchem fileiras em sequência', () => {
  const r = L.montarFileiras([{ ...produto, quantidade: 2 }, { ...produto, id: 'q', quantidade: 2 }], cfg);
  assert.strictEqual(r.fileiras.length, 2);
  assert.strictEqual(r.fileiras[0][2].produto.id, 'q');
});
teste('opção "nova fileira por produto"', () => {
  const r = L.montarFileiras(
    [{ ...produto, quantidade: 2 }, { ...produto, id: 'q', quantidade: 2 }],
    { ...cfg, novaFileiraPorProduto: true }
  );
  assert.strictEqual(r.fileiras.length, 2);
  assert.strictEqual(r.fileiras[0][2], null);
  assert.strictEqual(r.fileiras[1][0].produto.id, 'q');
  assert.strictEqual(r.totalEtiquetas, 4);
});
teste('página por fileira: 104 mm (largura da cabeça) × 21 mm', () => {
  const p = L.tamanhoPagina(cfg, 4);
  assert.deepStrictEqual(p, { largura: 104, altura: 21, paginas: 4 });
});
teste('página contínua: 4 fileiras = 4×21 + 3×3 = 93 mm', () => {
  const p = L.tamanhoPagina({ ...cfg, modoPagina: 'continuo' }, 4);
  assert.deepStrictEqual(p, { largura: 104, altura: 93, paginas: 1 });
});
teste('margens superior e inferior entram na altura da página', () => {
  const p = L.tamanhoPagina({ ...cfg, margemSuperior: 1, margemInferior: 0.5 }, 1);
  assert.deepStrictEqual(p, { largura: 104, altura: 22.5, paginas: 1 });
});
teste('colunas centralizadas: bloco 3×33 + 2×3 = 105 mm em 104 mm começa em -0,5 mm', () => {
  assert.strictEqual(L.larguraBloco(cfg), 105);
  assert.deepStrictEqual(L.posicoesColunas(cfg), [-0.5, 35.5, 71.5]);
  assert.deepStrictEqual(L.posicoesColunas({ ...cfg, deslocX: 1 }), [0.5, 36.5, 72.5]);
  assert.deepStrictEqual(L.posicoesColunas({ ...cfg, espacoHorizontal: 2 }), [0.5, 35.5, 70.5]);
});
teste('configuração inválida é corrigida', () => {
  const c = L.sanitizarConfig({ larguraPagina: '50', fontePreco: '999', modoPagina: 'a4', deslocX: '1,5', zoom: 'x', metodo: 'fax' });
  assert.strictEqual(c.larguraPagina, 99);
  assert.strictEqual(c.metodo, 'direto');
  assert.strictEqual(c.fontePreco, 28);
  assert.strictEqual(c.modoPagina, 'fileira');
  assert.strictEqual(c.deslocX, 1.5);
  assert.strictEqual(c.zoom, 2);
});
teste('etiqueta de teste e etiqueta única ocupam só a coluna pedida', () => {
  assert.deepStrictEqual(L.fileiraTeste([2])[0].map(Boolean), [false, true, false]);
  assert.deepStrictEqual(L.fileiraUnica(produto, 3)[0].map(Boolean), [false, false, true]);
});

console.log(falhas ? `\n${falhas} teste(s) falharam.` : '\nTodos os testes passaram.');
process.exit(falhas ? 1 : 0);
