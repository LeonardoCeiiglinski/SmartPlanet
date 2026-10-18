const assert = require('assert');
const wedge = require('../public/assets/scanner-wedge.js');

function digitar(teclas) {
  const leitor = wedge.create();
  let agora = 1000;
  const saidas = [];
  for (const item of teclas) {
    agora += item.espera;
    saidas.push(leitor.push(item.tecla, agora, !!item.repete));
  }
  return saidas;
}

function codigo(texto, intervalo, fecha) {
  const teclas = texto.split('').map((tecla) => ({ tecla, espera: intervalo }));
  teclas.push({ tecla: fecha || 'Enter', espera: intervalo });
  return teclas;
}

const rajada = digitar(codigo('7891234567895', 12));
assert.strictEqual(rajada.at(-1).action, 'scan');
assert.strictEqual(rajada.at(-1).code, '7891234567895');
assert.ok(rajada.some((item) => item.action === 'swallow'), 'a rajada longa não engoliu teclas');

const lento = digitar(codigo('7891234567895', 70));
assert.strictEqual(lento.at(-1).action, 'scan', 'leitor de entrada com intervalo de 70 ms');
assert.strictEqual(lento.at(-1).code, '7891234567895');

const pessoa = digitar(codigo('cafe', 180));
assert.strictEqual(pessoa.at(-1).action, 'ignore');

const curto = digitar(codigo('123', 10));
assert.strictEqual(curto.at(-1).action, 'ignore');

const repetido = digitar([
  { tecla: 'a', espera: 10 },
  { tecla: 'a', espera: 30, repete: true },
  { tecla: 'a', espera: 30, repete: true },
  { tecla: 'a', espera: 30, repete: true },
  { tecla: 'Enter', espera: 30 }
]);
assert.strictEqual(repetido.at(-1).action, 'ignore');

const tab = digitar(codigo('789100', 20, 'Tab'));
assert.strictEqual(tab.at(-1).action, 'scan');
assert.strictEqual(tab.at(-1).code, '789100');

console.log('scanner-wedge: ok');
