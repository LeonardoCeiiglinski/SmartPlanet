'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const routes = read('public/index.php');
const client = read('public/assets/labels.js');
const service = read('app/Domain/Labels/LabelService.php');
const view = read('app/Views/labels/index.php');

assert.match(routes, /get\('\/labels'/, 'rota autenticada principal ausente');
assert.match(routes, /post\('\/labels\/history'/, 'rota de histórico ausente');
assert.match(routes, /post\('\/labels\/migration\/preview'/, 'rota de prévia de migração ausente');
assert.match(service, /WHERE p\.company_id=:company AND p\.active=1/, 'catálogo não está isolado por empresa');
assert.match(service, /INSERT INTO print_history/, 'histórico não é persistido no backend');
assert.match(service, /Produto não pertence ao catálogo central/, 'histórico não valida catálogo central');
assert.match(client, /EtqLayout/, 'layout legado não foi preservado');
assert.match(client, /EtqRender/, 'renderizador legado não foi preservado');
assert.match(client, /EtqTspl/, 'TSPL\/WebUSB legado não foi preservado');
assert.match(client, /Nenhum conflito será sobrescrito/, 'migração não explicita tratamento de conflitos');
assert.doesNotMatch(client, /localStorage\.setItem\(LEGACY_KEY/, 'cliente não pode manter catálogo local divergente');
assert.match(view, /name="escopo" value="todos"/, 'impressão de todos ausente');
assert.match(view, /name="escopo" value="selecionados"/, 'impressão de selecionados ausente');

console.log('Todos os testes da integração de etiquetas passaram.');
