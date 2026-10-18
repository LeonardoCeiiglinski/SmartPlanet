# Pesquisa de fluxos oficiais do MarketUP

Consulta realizada em 17/10/2026, exclusivamente em páginas públicas da Central
de Atendimento MarketUP e páginas oficiais relacionadas. Não houve login,
automação autenticada, uso de endpoints privados ou tentativa de contornar
controles.

O objetivo é compreender fluxos de negócio para criar um sistema independente.
Não serão copiados código, identidade visual, textos ou estruturas internas.

## Conclusões principais

- Não foi localizada documentação pública atual de uma API MarketUP para
  terceiros. A resposta oficial explícita encontrada, de 17/08/2023, informa
  que a funcionalidade não existia.
- Conectores de Loja Integrada, Conta Azul, delivery e TEF são integrações
  específicas; não demonstram uma API pública do MarketUP.
- A integração opcional deste projeto será limitada a planilhas e exportações
  oficialmente suportadas.
- Recibos não fiscais não substituem NF-e, NFC-e ou NFS-e.
- Regras fiscais dependem de contador, UF, município, credenciamento,
  certificado e homologação; não serão inferidas.

## Fontes consultadas por assunto

### Primeiros passos, empresa e usuários

- [Guia de primeiros passos](https://suporte.marketup.com/hc/pt-br/articles/39121563352980-Guia-de-primeiros-passos-no-MarketUP-Onboarding-Por-onde-come%C3%A7o)
- [Como criar uma conta](https://suporte.marketup.com/hc/pt-br/articles/205580419-Como-criar-uma-conta-no-MarketUP)
- [Dados gerais da empresa](https://suporte.marketup.com/hc/pt-br/articles/360024388431-DADOS-GERAIS-COMO-CONFIGURAR)
- [Endereços da empresa](https://suporte.marketup.com/hc/pt-br/articles/360000075043-CONFIGURE-OS-ENDERE%C3%87OS-DA-SUA-EMPRESA)
- [Parâmetros de venda](https://suporte.marketup.com/hc/pt-br/articles/360000099726-CONFIGURE-OS-PAR%C3%82METROS-DE-VENDA-DA-SUA-EMPRESA)
- [Funcionários e usuários](https://suporte.marketup.com/hc/pt-br/articles/360000085483-CADASTRO-Como-cadastrar-um-Funcion%C3%A1rio-e-ou-Usu%C3%A1rio-no-MarketUP)
- [Permissões](https://suporte.marketup.com/hc/pt-br/articles/205123145-CADASTRO-Como-cadastrar-permiss%C3%B5es-para-os-Usu%C3%A1rios-Funcionarios-no-sistema)

Fluxos documentados: dados legais e endereços funcionais; parâmetros de venda;
funcionário sem acesso; usuário associado a perfil e funções; permissões por
grupo, módulo e funcionalidade.

### Produtos, serviços e códigos

- [Cadastro de produtos](https://suporte.marketup.com/hc/pt-br/articles/203708385-CADASTRO-DE-PRODUTOS)
- [Cadastro de serviços](https://suporte.marketup.com/hc/pt-br/articles/202977359-CADASTRO-DE-SERVI%C3%87OS)
- [Categorias e subcategorias](https://suporte.marketup.com/hc/pt-br/articles/203725085-DIVIDA-SEUS-PRODUTOS-EM-CATEGORIAS-E-SUBCATEGORIAS)
- [Marcas](https://suporte.marketup.com/hc/pt-br/articles/360000074583-ADICIONE-UMA-NOVA-MARCA)
- [Unidades de medida](https://suporte.marketup.com/hc/pt-br/articles/360000076186-CONFIGURE-A-UNIDADE-DE-MEDIDA-DE-UM-PRODUTO)
- [Leitor de código de barras](https://suporte.marketup.com/hc/pt-br/articles/202941149-UTILIZE-O-LEITOR-DE-CODIGO-DE-BARRAS)
- [Impressão de etiquetas](https://suporte.marketup.com/hc/pt-br/articles/115000766483-IMPRIMA-SUAS-ETIQUETAS)
- [Importação de produtos](https://suporte.marketup.com/hc/pt-br/articles/202856059-PRODUTOS-Como-importar-uma-planilha-de-produto)
- [Importação de serviços](https://suporte.marketup.com/hc/pt-br/articles/115000973126-IMPORTE-UMA-PLANILHA-DE-SERVI%C3%87OS)
- [Exportação de produtos](https://suporte.marketup.com/hc/pt-br/articles/203708995-IMPRIMA-OU-EXPORTE-A-LISTAGEM-DE-PRODUTOS)

Fluxos documentados: cadastro manual ou por código; descrição, tipo e unidade
obrigatórios; preços, estoque e fiscal separados; leitor USB plug-and-play;
importação por arquivo-modelo com validação e protocolo; código de barras único
e estável.

### Estoque, compras e fornecedores

- [Movimentação manual](https://suporte.marketup.com/hc/pt-br/articles/203005879-REALIZE-UMA-MOVIMENTA%C3%87%C3%83O-DE-ESTOQUE-MANUALMENTE)
- [Ajuste por local](https://suporte.marketup.com/hc/pt-br/articles/360040423132-ATUALIZE-A-QUANTIDADE-EM-ESTOQUE-DOS-PRODUTOS-EM-DIFERENTES-LOCAIS-DE-ESTOQUE)
- [Inventário](https://suporte.marketup.com/hc/pt-br/articles/360032866791-ESTOQUE-Tudo-sobre-a-realiza%C3%A7%C3%A3o-de-Invent%C3%A1rio-de-estoque)
- [Histórico de movimentos](https://suporte.marketup.com/hc/pt-br/articles/360000083766-VERIFIQUE-SUAS-MOVIMENTA%C3%87%C3%95ES-DE-ESTOQUE)
- [Fornecedor](https://suporte.marketup.com/hc/pt-br/articles/202971309-CADASTRE-UM-FORNECEDOR-NO-SISTEMA)
- [Pedido de compra](https://suporte.marketup.com/hc/pt-br/articles/202971189-CRIE-UM-PEDIDO-DE-COMPRA)
- [Compras em aberto](https://suporte.marketup.com/hc/pt-br/articles/203725025-RELAT%C3%93RIO-COMPRAS-EM-ABERTO)
- [Importar XML de compra](https://suporte.marketup.com/hc/pt-br/articles/203706505-FISCAL-Como-importar-uma-Nota-Fiscal-em-XML-de-Compra)

Fluxos documentados: movimento com origem/destino; inventário bloqueante por
local; histórico de movimentos automáticos e manuais; pedido concluído não é
editável; recebimento confirmado adiciona estoque.

### Clientes, vendas, PDV e caixa

- [Cliente](https://suporte.marketup.com/hc/pt-br/articles/202926939-CADASTRE-UM-CLIENTE)
- [Vendedor](https://suporte.marketup.com/hc/pt-br/articles/205001959-CADASTRE-UM-NOVO-VENDEDOR)
- [Orçamento](https://suporte.marketup.com/hc/pt-br/articles/202912159-CRIE-UM-OR%C3%87AMENTO-DE-VENDA)
- [Pedido de venda](https://suporte.marketup.com/hc/pt-br/articles/203643525-CRIE-UM-PEDIDO-DE-VENDA)
- [O que é PDV](https://suporte.marketup.com/hc/pt-br/articles/202939729-PDV-O-que-%C3%A9-um-PDV)
- [Venda no PDV](https://suporte.marketup.com/hc/pt-br/articles/203676695-PDV-Realize-uma-venda-e-emiss%C3%A3o-de-cupom-fiscal-no-PDV)
- [Habilitar PDV](https://suporte.marketup.com/hc/pt-br/articles/360000116126-PDV-Como-Habilitar-ou-Desabilitar-um-PDV)
- [Sangria](https://suporte.marketup.com/hc/pt-br/articles/202941089-COMO-REALIZAR-UMA-SANGRIA-NO-PDV)
- [Suprimento/reforço](https://suporte.marketup.com/hc/pt-br/articles/203676225-Realize-um-Refor%C3%A7o-no-PDV)
- [Fechamento](https://suporte.marketup.com/hc/pt-br/articles/203674945-APRENDA-A-FECHAR-O-CAIXA)
- [Operações do PDV](https://suporte.marketup.com/hc/pt-br/articles/202856029-RELAT%C3%93RIO-OPERA%C3%87%C3%95ES-PDV)

Fluxos documentados: caixa aberto; cliente e vendedor opcionais; item por botão,
busca ou código; fundo, sangria, suprimento e fechamento previsto/realizado;
ações sensíveis podem exigir administrador.

### Pagamentos, cancelamentos e devoluções

- [Formas e condições](https://suporte.marketup.com/hc/pt-br/articles/202889799-CONFIGURE-AS-FORMAS-E-CONDI%C3%87%C3%95ES-DE-PAGAMENTO)
- [Configuração de descontos](https://suporte.marketup.com/hc/pt-br/articles/4406770930836-COMO-CONFIGURAR-DESCONTOS)
- [Desconto no PDV](https://suporte.marketup.com/hc/pt-br/articles/202942869-LANCE-UM-DESCONTO-NO-PDV)
- [Cancelar venda no PDV](https://suporte.marketup.com/hc/pt-br/articles/203674805-CANCELE-UMA-VENDA-NO-PDV)
- [Cancelar cupom](https://suporte.marketup.com/hc/pt-br/articles/202938829-CANCELE-UM-CUPOM-DE-VENDA)
- [Devolver pedido](https://suporte.marketup.com/hc/pt-br/articles/360046654031-REALIZE-UMA-DEVOLU%C3%87%C3%83O-DE-UM-PEDIDO-DE-VENDA)
- [Devolução no PDV](https://suporte.marketup.com/hc/pt-br/articles/360042617311-COMO-REALIZAR-UMA-DEVOLU%C3%87%C3%83O-NO-PDV)

Fluxos documentados: disponibilidade e parcelamento por forma; limites de
desconto; autorização administrativa; cancelamento preserva histórico;
devolução pode recompor estoque, estornar ou gerar vale.

### Financeiro, relatórios e fiscal

- [Conta a pagar](https://suporte.marketup.com/hc/pt-br/articles/203607345-FINANCEIRO-Como-adicionar-uma-conta-a-pagar)
- [Registrar pagamento](https://suporte.marketup.com/hc/pt-br/articles/202881919-COMO-REGISTRAR-UM-PAGAMENTO)
- [Registrar recebimento](https://suporte.marketup.com/hc/pt-br/articles/203596965-FINANCEIRO-Como-Registrar-um-Recebimento)
- [Fluxo de caixa](https://suporte.marketup.com/hc/pt-br/articles/203619005-EXPLORE-O-FLUXO-DE-CAIXA)
- [Conta corrente](https://suporte.marketup.com/hc/pt-br/articles/203612025-CADASTRE-UMA-CONTA-CORRENTE)
- [Relatórios](https://suporte.marketup.com/hc/pt-br/categories/200261845-Relat%C3%B3rios)
- [Certificados](https://suporte.marketup.com/hc/pt-br/articles/202894869-CERTIFICADO-Quais-s%C3%A3o-os-Certificados-aceitos-no-MarketUP)
- [NF-e](https://suporte.marketup.com/hc/pt-br/articles/202892989-HABILITE-A-EMISS%C3%83O-DE-NF-E-NO-MARKETUP)
- [NFC-e](https://suporte.marketup.com/hc/pt-br/articles/4406899784852-FISCAL-Configure-NFC-e-para-emitir)
- [NFS-e](https://suporte.marketup.com/hc/pt-br/articles/360016069892-CONFIGURE-O-MARKETUP-PARA-EMITIR-NFS-E)

Fluxos documentados: contas com vencimento e liquidação; juros/descontos;
fluxo por competência e caixa; relatórios filtráveis/exportáveis; emissão
fiscal depende de credenciamento, certificado e parâmetros tributários.

### Integrações, filiais e backup

- [API e integrações](https://suporte.marketup.com/hc/pt-br/community/posts/18366888113684-API-s-e-integra%C3%A7%C3%B5es-com-outros-sistemas)
- [Seção de integrações](https://suporte.marketup.com/hc/pt-br/sections/360012378611-Integra%C3%A7%C3%B5es)
- [Loja Integrada](https://suporte.marketup.com/hc/pt-br/articles/360042378111--INTEGRA%C3%87%C3%95ES-LOJA-INTEGRADA)
- [Migração Conta Azul](https://suporte.marketup.com/hc/pt-br/articles/4401921010836-CONECTORES-Como-realizar-integra%C3%A7%C3%A3o-Importa%C3%A7%C3%A3o-dos-dados-da-Conta-Azul-para-o-MarketUP)
- [MultiLoja](https://suporte.marketup.com/hc/pt-br/articles/5071359387668-MULTILOJA-FRANQUIAS-Como-incluir-uma-filial-no-m%C3%B3dulo-Franquias-MultiLoja)
- [Arquivo+](https://suporte.marketup.com/hc/pt-br/articles/4403287976724-Como-contratar-o-Arquivo)
- [Limpeza de dados](https://suporte.marketup.com/hc/pt-br/articles/204507095-EMPRESA-Como-solicitar-limpeza-de-todos-os-dados-do-sistema)

## Requisitos derivados para este projeto

As decisões abaixo são inferências de engenharia, não funcionalidades atribuídas
ao MarketUP:

- usar RBAC e autorização de supervisor;
- movimentos e lançamentos confirmados serão imutáveis;
- cancelamentos e devoluções usarão lançamentos compensatórios;
- estoque será razão de movimentos, não campo editável isolado;
- venda, pagamentos, estoque e financeiro serão atômicos e idempotentes;
- importações terão validação, prévia, protocolo e relatório por linha;
- backups incluirão testes regulares de restauração;
- integrações próprias futuras terão contrato documentado e autenticação segura.

## Limitações da pesquisa

- Datas editoriais não estavam visíveis na maioria dos artigos.
- Vídeos e detalhes presentes somente em imagens não foram reproduzidos.
- Menus divergem entre artigos, possivelmente por versões diferentes.
- Recursos após login, contratação ou atendimento comercial não foram testados.
- Não foram confirmados SLA, RPO/RTO, sandbox fiscal ou backup geral do ERP.
- Não foram testados hardware, emissão fiscal, TEF ou comportamento concorrente.
- A resposta sobre API é oficial, porém de 2023; ela não prova a inexistência
  de integrações privadas posteriores, apenas que não há API pública documentada
  localizada.
