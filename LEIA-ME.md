# Etiquetas 33 × 21 mm — 4BARCODE 4B-2054N / Tomate MDK-2054N

Aplicativo local para cadastrar produtos e imprimir etiquetas de **33 × 21 mm** em bobina de **3 colunas**.
Não precisa de internet, instalação, servidor nem cadastro.

## Como abrir

1. Abra a pasta do aplicativo.
2. Dê dois cliques em **`index.html`**.
   - Windows: abre no Edge ou no Chrome.
   - macOS: abre no Safari; Chrome também funciona (botão direito › Abrir com › Google Chrome).
3. Use sempre o **mesmo navegador**: os produtos ficam salvos nele (armazenamento local). Limpar os dados de navegação do navegador apaga os produtos.

Recomendado: **Google Chrome** ou **Microsoft Edge**, que respeitam melhor o tamanho de página definido pelo aplicativo.

## Uso diário

1. Preencha **Nome do produto**, **Preço**, **Quantidade de etiquetas** e, se houver, o **Código de barras**.
   - 13 dígitos com verificador válido → EAN-13; 12 dígitos válidos → UPC-A; outros → Code 128.
   - Sem código, a etiqueta sai só com nome e preço.
2. Clique em **Adicionar produto**. Use **Editar**, **Excluir** e as caixas de seleção na lista.
3. Escolha **Imprimir todas as etiquetas** ou **Somente produtos selecionados**.
4. Confira a **Prévia da impressão** (cada fileira tem 3 etiquetas; posições vazias aparecem hachuradas e não são impressas).
5. Clique em **Imprimir etiquetas**, confira o resumo e confirme (**Imprimir agora** na impressão direta, ou **Abrir janela de impressão** no método pelo navegador). Nada é enviado sem essa confirmação.

Quantidade 3 = 1 fileira, 6 = 2 fileiras, 9 = 3 fileiras, 12 = 4 fileiras. Com quantidade 1, a fileira usa só a primeira coluna.

## Impressão direta via USB (recomendada)

É o método padrão. O app desenha cada fileira ponto a ponto (8 pontos/mm, a resolução da impressora) e envia em **TSPL**, a linguagem da 4B-2054N, junto com o tamanho da etiqueta (`SIZE 104 mm,21 mm`, `GAP 3 mm`). A escala e o papel do driver **não interferem**. Esses problemas de escala e papel são a causa das etiquetas saírem espalhadas por várias fileiras e de muitas fileiras avançarem em branco.

1. Abra o app no **Google Chrome** ou no **Microsoft Edge** (Safari e Firefox não têm acesso USB).
2. Ligue a impressora pelo USB e clique em **Conectar impressora USB**. Escolha a impressora na lista.
3. Clique em **Calibrar sensor de etiquetas** (uma vez por bobina): a impressora avança algumas etiquetas para medir o gap.
4. Clique em **Imprimir fileira de teste (3)** e ajuste o alinhamento (veja “Calibração”).
5. Imprima normalmente com **Imprimir etiquetas**.

Se sair de cabeça para baixo, marque **Girar 180°**. Se sair tudo preto com o texto em branco, marque **Inverter cores**. Para escurecer ou clarear, mude o **Escurecimento** (0–15).

**macOS:** funciona direto no Chrome/Edge.

**Windows:** o driver da impressora normalmente bloqueia o acesso USB do navegador (aparece a mensagem “O sistema não liberou a impressora”). Nesse caso:
- use **Baixar arquivo .prn** e envie o arquivo para a impressora sem conversão: compartilhe a impressora (Propriedades › Compartilhamento, nome `Tomate`) e, no Prompt de Comando, rode `copy /b "%USERPROFILE%\Downloads\etiquetas.prn" \\localhost\Tomate`;
- ou use o método **Pela janela de impressão do navegador** com o driver configurado como explicado abaixo.

No macOS, o arquivo .prn também pode ser enviado pelo Terminal: `lp -d NOME_DA_IMPRESSORA -o raw ~/Downloads/etiquetas.prn` (veja o nome com `lpstat -p`).

> Não use “AnyDesk Printer” nem impressão remota: elas convertem o trabalho em PDF e redimensionam a página.

## Configuração do driver (só para impressão pelo navegador)

O aplicativo mostra em **Ajustes de impressão › Papel a configurar no driver** o tamanho exato da página (padrão: **104 × 21 mm**, uma página por fileira; as 3 colunas de 33 mm com 3 mm de espaço ficam centralizadas). Se o driver continuar com o papel de envio padrão (100 × 150 mm), a impressão sai reduzida e espalhada por várias fileiras, e várias fileiras avançam em branco.

**Windows** (driver 4BARCODE/Seagull ou equivalente):
1. Configurações › Impressoras › 4B-2054N › Preferências de impressão.
2. Em Papel/Página, crie um papel novo com **largura 104 mm** e **altura 21 mm** (ou o valor mostrado no app).
3. Tipo de mídia: **etiquetas com gap/espaço** (sensor de gap). Orientação: retrato.
4. Desative qualquer opção de “ajustar à página”/“escala”.

**macOS**:
1. Na janela de impressão, em Tamanho do papel, escolha **Gerenciar tamanhos personalizados**.
2. Crie um tamanho de **104 × 21 mm** com todas as margens (área não imprimível) em **0**.

## Janela de impressão do navegador

- **Escala: 100%** (ou “Tamanho real”/“Padrão”). Nunca “Ajustar à página”.
- **Margens: Nenhuma**.
- **Cabeçalhos e rodapés: desativados** (Chrome/Edge: “Mais configurações”; Safari: desmarque “Imprimir cabeçalhos e rodapés”).
- Tamanho do papel: o mesmo criado no driver.

> **Atenção:** escala, margens e tamanho de papel do driver ou do navegador também afetam a impressão física. Se algum deles estiver diferente, a etiqueta pode sair reduzida, deslocada ou em páginas a mais.

## Calibração (antes de imprimir grandes quantidades)

Os valores padrão são um ponto de partida. **A calibração física só é confirmada imprimindo na sua impressora.**

1. Em **Teste e calibração**, clique em **Imprimir 1 etiqueta de teste** (escolha a coluna) ou **Imprimir fileira de teste (3)**.
2. A etiqueta de teste tem um contorno de exatamente 33 × 21 mm e uma cruz no centro.
3. Compare com a etiqueta física e ajuste:
   - Tudo deslocado para um lado → **Deslocamento horizontal** (negativo = esquerda).
   - Tudo deslocado para cima/baixo → **Deslocamento vertical** (negativo = para cima).
   - Colunas 1 e 3 desalinhadas em sentidos opostos → **Espaço entre colunas** (padrão 3 mm, medido na bobina).
   - Conteúdo cortado na borda → confira as guias da bobina (devem centralizar o rolo) ou ajuste a **Largura da página**.
4. Repita até o contorno coincidir com a etiqueta. Depois use **Testar 1** em um produto para conferir o texto.

Outros ajustes: tamanho máximo do nome, tamanho do preço, altura e largura do código de barras, margem interna, alinhamento do texto e números abaixo do código. **Restaurar padrões** volta aos valores iniciais.

### Modo de página

- **Uma página por fileira (recomendado):** cada fileira é uma página de 21 mm; o sensor de gap da impressora posiciona a próxima fileira.
- **Página contínua:** todas as fileiras em uma única página, com o “Espaço entre fileiras” entre elas. Use se o driver estiver configurado como mídia contínua.

## Arquivos

| Arquivo | Função |
| --- | --- |
| `index.html` | Aplicativo |
| `css/styles.css` | Visual da tela e regras de impressão (`@media print` / `@page`) |
| `js/app.js` | Interface, lista e armazenamento local |
| `js/layout.js` | Dimensões, fileiras, preços e ajustes |
| `js/render.js` | Desenho das etiquetas em milímetros e ajuste automático do texto |
| `js/barcode.js` | Gerador de EAN-13, UPC-A e Code 128 (sem dependências) |
| `js/tspl.js` | Impressão direta: bitmap de 203 dpi, comandos TSPL e conexão USB |
| `tests/` | Testes automatizados (opcionais, exigem Node.js) |

## Testes automatizados (opcional)

Com o [Node.js](https://nodejs.org) instalado, na pasta do aplicativo:

```
node tests/unit-tests.js
node tests/print-tests.js
```

O primeiro testa códigos de barras, preços, fileiras e dimensões. O segundo usa Chrome/Edge para gerar PDFs com quantidades 1, 3, 6, 9 e 12 e confere a quantidade de etiquetas, duplicatas, sobreposição de texto, tamanho das páginas e ausência de páginas extras.
