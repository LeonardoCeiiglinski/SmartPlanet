'use strict';
const assert = require('assert');
const R = require('../public/assets/receipt-58.js');

const recibo = {
  tradeName: 'SmartPlanet',
  phone: '(48) 99662-8811',
  address: [],
  saleNumber: 'VD-20261019-131859-B0EB',
  completedAt: '2026-10-19 13:18:59',
  operator: 'Leonardo Ceiglinski',
  customer: '',
  status: 'completed',
  discount: 0,
  total: 19.9,
  items: [{ description: 'Mouse Optico USB 2.0', quantity: 1, unitPrice: 19.9, subtotal: 19.9 }],
  payments: [{ name: 'Dinheiro', amount: 19.9, installments: 1 }],
};

assert.strictEqual(R.DOMINIO, 'lojadeeletronicossaojose.com.br');
assert.strictEqual(Array.from(R.DOMINIO).length, 31);
const vias = R.vias(recibo);
assert.deepStrictEqual(vias.map((v) => v.titulo), ['VIA DO CLIENTE', 'VIA DA LOJA']);
assert.notStrictEqual(vias[0].linhas[0].text, vias[1].linhas[0].text);
assert.ok(vias[0].linhas[0].via && vias[0].linhas[0].text.includes('VIA DO CLIENTE'));
assert.ok(vias[1].linhas[0].via && vias[1].linhas[0].text.includes('VIA DA LOJA'));
assert.ok(vias[0].linhas.some((l) => l.text.includes('Fica com o cliente')));
assert.ok(vias[1].linhas.some((l) => l.text.includes('Fica com a loja')));
assert.ok(vias[0].linhas.some((l) => l.text.includes('Cliente: Consumidor final')));
for (const via of vias) {
  assert.ok(via.linhas.some((l) => l.bold && l.text.includes(via.titulo)), via.titulo);
  assert.ok(via.linhas.some((l) => l.text.includes(R.DOMINIO)), 'domínio na ' + via.titulo);
}
const linhas = vias[0].linhas;
assert.ok(linhas.length > 8, 'cupom curto demais');
for (const linha of linhas) {
  assert.strictEqual(Array.from(linha.text).length, R.COLUNAS, 'linha fora de 32 colunas: ' + JSON.stringify(linha.text));
}
assert.ok(linhas.some((l) => l.text.includes('RECIBO NÃO FISCAL') && l.bold));
assert.ok(linhas.some((l) => l.text.trim() === 'Mouse Optico USB 2.0'));
assert.ok(linhas.some((l) => l.text.startsWith('TOTAL') && l.text.endsWith('R$ 19,90') && l.bold));
assert.ok(linhas.some((l) => l.text.startsWith('Dinheiro') && l.text.endsWith('R$ 19,90')));
assert.ok(linhas.some((l) => l.text.includes('Não é documento fiscal')));
assert.ok(linhas.some((l) => l.text.includes('19/10/2026 13:18')));

const longo = R.vias({
  tradeName: 'SmartPlanet',
  saleNumber: 'VD-1',
  completedAt: '2026-10-19 13:18:59',
  operator: 'Ana',
  status: 'completed',
  discount: 2.5,
  total: 1234.56,
  items: [{
    description: 'Cabo trançado reforçado com conector USB tipo C de dois metros',
    quantity: 1.5,
    unitPrice: 10,
    subtotal: 15,
  }],
  payments: [{ name: 'Cartão de crédito', amount: 1234.56, installments: 3 }],
})[1].linhas;
for (const linha of longo) {
  assert.strictEqual(Array.from(linha.text).length, 32);
}
assert.ok(longo.some((l) => l.text.includes('1,5 x R$ 10,00')));
assert.ok(longo.some((l) => l.text.startsWith('Desconto') && l.text.endsWith('R$ 2,50')));
assert.ok(longo.some((l) => l.text.includes('3x') && l.text.endsWith('R$ 1.234,56')));

const cliente = Buffer.from(R.paraEscPos(vias[0].linhas)).toString('latin1');
const empresa = Buffer.from(R.paraEscPos(vias[1].linhas)).toString('latin1');
assert.ok(cliente.includes('VIA DO CLIENTE'));
assert.ok(empresa.includes('VIA DA LOJA'));
assert.ok(cliente.includes('Fica com o cliente'));
assert.ok(empresa.includes('Fica com a loja'));
assert.notStrictEqual(cliente, empresa);
const nomeLongo = R.linhas({
  tradeName: 'SMARTPLANET A BOUTIQUE DO CELULAR',
  saleNumber: 'VD-1',
  status: 'completed',
  total: 1,
  items: [],
  payments: [],
}, 'VIA DO CLIENTE');
assert.ok(!nomeLongo.some((l) => l.text.includes('a Boutique do Celular')));
assert.ok(!nomeLongo.some((l) => l.text.trim() === 'SmartPlanet'));

const bytes = R.paraEscPos(linhas);
assert.strictEqual(bytes[0], 0x1b);
assert.strictEqual(bytes[1], 0x40);
assert.ok(bytes.includes(0x10), 'página 1252');
const texto = Buffer.from(bytes).toString('latin1');
assert.ok(texto.includes('N\u00c3O'), 'NÃO em Windows-1252');
assert.strictEqual(bytes[bytes.length - 4], 0x1d);
assert.strictEqual(bytes[bytes.length - 3], 0x56);
assert.strictEqual(bytes[bytes.length - 2], 0x42);
assert.strictEqual(bytes[bytes.length - 1], 0x00);
assert.strictEqual(R.PAPEL_MM, 58);
assert.strictEqual(R.IMPRESSAO_MM, 48);
assert.strictEqual(R.MODELO, 'POS-5890U-L');
const pixels = new Uint8Array(384 * 8);
pixels[0] = 1;
const faixa = R.rasterEscPos(pixels, 384, 8)[0];
assert.strictEqual(faixa[0], 0x1d);
assert.strictEqual(faixa[4], 48);
assert.strictEqual(faixa[8] & 0x80, 0x80);

console.log('receipt-58mm ok', linhas.length, 'linhas', bytes.length, 'bytes');
