// Teste de impressão real em PDF usando Edge/Chrome em modo headless.
// Executar com: node tests/print-tests.js
// Verifica contagem, dimensões em mm, sobreposição e número de páginas do PDF gerado.
'use strict';
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');
const { pathToFileURL } = require('url');

const CANDIDATOS = [
  process.env.CHROME_PATH,
  'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
  'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
  'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
  'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
  '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge',
].filter(Boolean);
const navegador = CANDIDATOS.find((c) => fs.existsSync(c));
if (!navegador) {
  console.error('Nenhum Chrome/Edge encontrado. Defina CHROME_PATH.');
  process.exit(2);
}

const perfil = fs.mkdtempSync(path.join(os.tmpdir(), 'etq-perfil-'));
const saida = fs.mkdtempSync(path.join(os.tmpdir(), 'etq-pdf-'));
const pagina = path.join(__dirname, 'print-test.html');
const base = ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', `--user-data-dir=${perfil}`];

function url(params) {
  return pathToFileURL(pagina).href + '?' + new URLSearchParams(params).toString();
}

function rodarDom(params) {
  const html = execFileSync(navegador, [...base, '--dump-dom', url(params)], { encoding: 'utf8', timeout: 60000 });
  const m = html.match(/RESULTADO:(.*?):FIM/s);
  if (!m) throw new Error('resultado não encontrado no DOM');
  return JSON.parse(m[1].replace(/&quot;/g, '"').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>'));
}

function rodarPdf(params, nome) {
  const arquivo = path.join(saida, nome + '.pdf');
  execFileSync(navegador, [...base, '--no-pdf-header-footer', `--print-to-pdf=${arquivo}`, url(params)], { timeout: 60000, stdio: 'ignore' });
  const bin = fs.readFileSync(arquivo).toString('latin1');
  const paginas = (bin.match(/\/Type\s*\/Page(?![s\w])/g) || []).length;
  const caixa = bin.match(/\/MediaBox\s*\[\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s*\]/);
  const ptParaMm = (pt) => Math.round((pt * 25.4 / 72) * 100) / 100;
  return {
    arquivo,
    paginas,
    larguraMm: caixa ? ptParaMm(caixa[3] - caixa[1]) : null,
    alturaMm: caixa ? ptParaMm(caixa[4] - caixa[2]) : null,
  };
}

let falhas = 0;
function verificar(cond, msg) {
  if (!cond) {
    falhas++;
    console.log(`       FALHA: ${msg}`);
  }
}

const casos = [];
for (const modo of ['fileira', 'continuo']) for (const q of [1, 3, 6, 9, 12]) casos.push({ q, modo });
casos.push({ q: 4, modo: 'fileira', codigo: '', nome: 'Sem código de barras' });
casos.push({
  q: 3, modo: 'fileira', codigo: 'ABC-12345-XYZ',
  nome: 'Nome de produto extremamente comprido para verificar o ajuste automático de fonte em etiqueta pequena',
  preco: '123456',
});

console.log(`Navegador: ${navegador}\n`);
for (const caso of casos) {
  const params = { q: caso.q, modo: caso.modo };
  if ('codigo' in caso) params.codigo = caso.codigo;
  if (caso.nome) params.nome = caso.nome;
  if (caso.preco) params.preco = caso.preco;
  const nome = `q${caso.q}-${caso.modo}${'codigo' in caso ? '-' + (caso.codigo || 'semcodigo') : ''}`;
  const d = rodarDom(params);
  const pdf = rodarPdf(params, nome);
  const fileirasEsperadas = Math.ceil(caso.q / 3);
  const paginasEsperadas = caso.modo === 'fileira' ? fileirasEsperadas : 1;
  const alturaEsperada = caso.modo === 'fileira' ? 21 : fileirasEsperadas * 21 + (fileirasEsperadas - 1) * 3;

  console.log(
    `  q=${String(caso.q).padStart(2)} ${caso.modo.padEnd(8)} etiquetas=${d.etiquetasNoDom} fileiras=${d.fileirasNoDom} ` +
    `vazias=${d.posicoesVazias} | PDF: ${pdf.paginas} pág. de ${pdf.larguraMm} × ${pdf.alturaMm} mm` +
    (caso.nome ? ` | ${caso.nome.slice(0, 30)}…` : '')
  );
  verificar(d.totalEtiquetas === caso.q && d.etiquetasNoDom === caso.q, `quantidade ${d.etiquetasNoDom} ≠ ${caso.q}`);
  verificar(d.indicesUnicos === caso.q && d.indicesSequenciais, 'etiquetas duplicadas ou fora de ordem');
  verificar(d.fileiras === fileirasEsperadas && d.fileirasNoDom === fileirasEsperadas, `fileiras ${d.fileirasNoDom} ≠ ${fileirasEsperadas}`);
  verificar(d.posicoesVazias === fileirasEsperadas * 3 - caso.q, 'posições vazias incorretas');
  verificar(d.folhas === paginasEsperadas, `folhas no DOM ${d.folhas} ≠ ${paginasEsperadas}`);
  verificar(d.folhaMm.every(([w, h]) => Math.abs(w - 103) < 0.05 && Math.abs(h - alturaEsperada) < 0.05), `folha mede ${JSON.stringify(d.folhaMm)}`);
  verificar(d.temCodigo === (caso.codigo !== ''), 'presença do código de barras incorreta');
  verificar(d.problemas.length === 0, d.problemas.join('; '));
  verificar(pdf.paginas === paginasEsperadas, `PDF com ${pdf.paginas} páginas, esperado ${paginasEsperadas}`);
  verificar(Math.abs(pdf.larguraMm - 103) < 0.3 && Math.abs(pdf.alturaMm - alturaEsperada) < 0.3,
    `página do PDF ${pdf.larguraMm} × ${pdf.alturaMm} mm, esperado 103 × ${alturaEsperada} mm`);
}

console.log(`\nPDFs gerados em: ${saida}`);
console.log(falhas ? `${falhas} verificação(ões) falharam.` : 'Todas as verificações de impressão passaram.');
try { fs.rmSync(perfil, { recursive: true, force: true }); } catch (e) { /* perfil ainda em uso */ }
process.exit(falhas ? 1 : 0);
