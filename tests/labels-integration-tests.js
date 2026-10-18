'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const routes = read('public/index.php');
const page = read('index.html');
const view = read('app/Views/labels/index.php');
const layout = read('app/Views/layout.php');
const controller = read('app/Http/LabelController.php');
const client = read('js/app.js');

assert.match(routes, /get\('\/labels'/, 'rota autenticada principal ausente');
assert.match(layout, /class="sidebar"/, 'menu lateral do ERP ausente');
assert.match(layout, /\/assets\/shell\.js/, 'navegação sem recarregar o menu ausente');
assert.match(layout, /\$headExtra/, 'layout não aceita CSS extra da página de etiquetas');
assert.match(layout, /\$bodyEnd/, 'layout não aceita scripts da página de etiquetas');
assert.match(controller, /View::render\('labels\/index'/, 'menu Etiquetas não usa o layout do sistema');
assert.match(controller, /bodyClass/, 'página de etiquetas não marca o layout do ERP');
assert.match(view, /\/legacy\/css\/styles\.css/, 'CSS original não é servido pelo menu');
assert.match(view, /\/legacy\/js\/layout\.js/, 'scripts originais não são servidos pelo menu');
assert.match(view, /\/legacy\/js\/app\.js/, 'aplicativo original não é servido pelo menu');
assert.match(view, /id="form-produto"/, 'cadastro original ausente');
assert.match(view, /id="campo-leitor"/, 'leitor original ausente');
assert.match(view, /id="botao-conectar"/, 'impressão USB original ausente');
assert.match(view, /id="botao-teste-fileira"/, 'calibração original ausente');
assert.match(view, /name="escopo" value="todos"/, 'impressão de todos ausente');
assert.match(view, /name="escopo" value="selecionados"/, 'impressão de selecionados ausente');
assert.match(view, /id="area-impressao"/, 'área de impressão ausente');
assert.match(view, /busca-catalogo/, 'busca no cadastro da loja ausente');
assert.match(view, /dialogo-etiqueta/, 'edição em container ausente');
assert.match(read('app/Views/stock/index.php'), /labels\?product=/, 'atalho de etiqueta no estoque ausente');
assert.match(read('app/Views/stock/index.php'), /qty=9/, 'estoque não envia 9 etiquetas');
assert.match(page, /src="js\/layout.js"/, 'layout original ausente');
assert.match(page, /src="js\/barcode.js"/, 'código de barras original ausente');
assert.match(page, /src="js\/render.js"/, 'renderizador original ausente');
assert.match(page, /src="js\/tspl.js"/, 'TSPL original ausente');
assert.match(page, /src="js\/app.js"/, 'aplicativo original ausente');
assert.match(client, /EtqLayout/, 'layout legado não foi preservado');
assert.match(client, /EtqRender/, 'renderizador legado não foi preservado');
assert.match(client, /EtqTspl/, 'TSPL/WebUSB legado não foi preservado');

console.log('Todos os testes da integração de etiquetas passaram.');
