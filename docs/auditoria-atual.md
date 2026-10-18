# Auditoria do aplicativo de etiquetas

Data da auditoria: 17/10/2026  
Commit de referência: `9b049b8`  
Ponto de restauração: tag `backup-static-20261017`

## Estado atual

O projeto é uma aplicação estática, sem backend e sem banco de dados. Usa HTML,
CSS e JavaScript puro e funciona tanto por arquivo local quanto no GitHub Pages.
Não há `package.json`, framework ou dependência de execução.

Os dados são gravados em `localStorage`:

- `etiquetas33x21.produtos.v1`: produtos;
- `etiquetas33x21.config.v2`: configuração de impressão;
- `etiquetas33x21.escopo.v1`: escopo da seleção;
- `etiquetas33x21.leituras.v1`: histórico local de leituras.

Consequentemente, dados não são compartilhados entre computadores ou
navegadores e são perdidos quando o armazenamento do site é apagado.

## Componentes existentes

- `index.html`: interface, formulários, diálogo de impressão e área imprimível.
- `css/styles.css`: interface, dimensões físicas e regras `@media print`.
- `js/app.js`: estado, validações, armazenamento, leitor, UI e impressão.
- `js/layout.js`: dimensões, fileiras de três colunas, preços e configurações.
- `js/barcode.js`: EAN-13, UPC-A e Code 128 sem dependências.
- `js/render.js`: etiquetas DOM/SVG, ajuste de fontes e CSS `@page`.
- `js/tspl.js`: canvas a 203 dpi, bitmap de um bit, TSPL, WebUSB e PRN.
- `tests/unit-tests.js`: códigos, preços, fileiras e dimensões.
- `tests/print-tests.js`: DOM, PDF e comandos TSPL em Edge/Chrome.

## Contrato de impressão preservado

Existem dois caminhos independentes:

1. impressão direta: `layout.js` → `tspl.js` → WebUSB ou arquivo PRN;
2. navegador: `layout.js` → `render.js` → `window.print()`.

Parâmetros atuais:

- etiqueta: 33 × 21 mm;
- três colunas;
- página: 104 × 21 mm por fileira;
- espaço horizontal: 3 mm;
- gap vertical: 3 mm;
- resolução TSPL: 8 pontos/mm (203 dpi);
- bitmap: 832 × 168 pontos.

O bloco das três etiquetas mede 105 mm e é centralizado em uma cabeça de
104 mm, ficando 0,5 mm além de cada borda. Isso já faz parte do comportamento
testado, mas precisa de validação física antes de ser considerado calibrado.

## Leitor de código de barras

O leitor atual é tratado como teclado. Uma rajada rápida de caracteres,
terminada por Enter ou Tab, é comparada exatamente com o código do produto.
O acerto cria um registro local; o erro prepara o cadastro.

O mecanismo é reaproveitável no PDV, mas precisa passar a consultar o banco,
preservar zeros à esquerda, impedir eventos duplicados e manter o foco sem
interferir em formulários e atalhos.

## Riscos encontrados

1. `index.html` contém um `@page` inicial de 103 mm, enquanto a configuração
   runtime e os testes usam 104 mm.
2. Os renderizadores DOM e canvas têm algoritmos de tipografia independentes.
3. O contorno da prévia TSPL não acompanha `deslocY`.
4. O cadastro local aceita códigos duplicados; a leitura encontra o primeiro.
5. WebUSB pode reutilizar o primeiro dispositivo autorizado, mesmo que não seja
   a impressora desejada.
6. O driver do Windows pode bloquear a interface USB; PRN e impressão pelo
   navegador continuam necessários.
7. Não há testes automatizados da UI completa, do leitor físico ou do WebUSB.
8. Não existe autenticação, autorização, servidor, estoque, venda ou caixa.

## Decisão de migração

O pipeline de impressão continuará no cliente. O PHP fornecerá autenticação,
cadastros, regras de negócio e API interna; o MySQL/MariaDB será a fonte única
dos dados comerciais. `layout.js`, `barcode.js`, `render.js` e `tspl.js` não
serão reimplementados no servidor na primeira migração.

## Baseline executado

Em 17/10/2026:

- `node tests/unit-tests.js`: todos os testes passaram;
- `node tests/print-tests.js`: todas as verificações passaram;
- PDF validado para 1, 3, 6, 9 e 12 etiquetas, nos modos por fileira e contínuo;
- TSPL validado em 104 × 21 mm, gap 3 mm e bitmap 832 × 168.

Não houve teste físico com leitor ou impressora durante esta auditoria.

## Correções posteriores

- O `@page` inicial de `index.html` foi alinhado para 104 mm.
- O cadastro SQL passou a exigir código de barras único.
- O PDV consulta o catálogo autenticado; o leitor HID continua no navegador.

## Restauração

Para consultar o estado anterior:

```bash
git switch --detach backup-static-20261017
```

Para criar uma branch restaurada sem destruir o trabalho atual:

```bash
git switch -c restauracao-etiquetas backup-static-20261017
```

Nunca usar `git reset --hard` para restaurar enquanto houver trabalho não
versionado.
