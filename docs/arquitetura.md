# Arquitetura do ERP/PDV

## Escopo

Sistema para uma empresa específica, preparado para filiais. Não será uma
plataforma SaaS multiempresa. A primeira produção será em hospedagem PHP/MySQL
com HTTPS e haverá ambiente local de desenvolvimento.

## Princípios

1. PHP 8.2+ e MySQL 8/MariaDB 10.6.
2. Interface renderizada no servidor com JavaScript progressivo.
3. PDO e consultas preparadas em todos os repositórios.
4. Regras de negócio em serviços, não em controllers ou JavaScript.
5. Autorização sempre validada no servidor.
6. Transações e idempotência em vendas, caixa, estoque e financeiro.
7. Lançamentos confirmados não são apagados; correções são compensatórias.
8. Impressão térmica permanece no navegador.
9. Segredos e arquivos privados permanecem fora do webroot.
10. Cada migration possui reversão e cada etapa preserva uma versão executável.

## Camadas

- `public/`: front controller e arquivos públicos;
- `app/Http`: controllers, middleware, request/response;
- `app/Domain`: entidades, valores, políticas e serviços de aplicação;
- `app/Infrastructure`: PDO, repositórios, e-mail, arquivos e logs;
- `app/Views`: templates da interface comercial;
- `config/`: configuração não sensível e carregamento de ambiente;
- `database/migrations`: evolução versionada do schema;
- `storage/`: logs, cache, uploads privados e backups locais;
- `tests/`: testes PHP e preservação da suíte de impressão.

## Fluxo de requisição

1. O front controller inicia configuração, sessão e tratamento de erros.
2. O roteador seleciona controller e middleware.
3. Middleware valida autenticação, CSRF, filial e permissão.
4. Controller valida a forma da entrada e chama um serviço.
5. Serviço aplica regras e abre transação quando necessário.
6. Repositórios usam PDO preparado.
7. A resposta é uma view HTML ou JSON da API interna.
8. Operações críticas criam auditoria na mesma transação.

## Segurança

- `password_hash`/`password_verify`;
- renovação do identificador após autenticação;
- cookies `HttpOnly`, `Secure` em HTTPS e `SameSite=Lax`;
- tokens CSRF vinculados à sessão;
- rate limit e log de tentativas de login;
- recuperação por token aleatório, hashado, de uso único e expirável;
- escape contextual em toda saída;
- uploads com nome aleatório, validação MIME e armazenamento fora de `public`;
- página de erro genérica em produção;
- `.env`, certificados, banco, logs e backups ignorados pelo Git.

## Modelo comercial

As entidades operacionais terão `company_id` e `branch_id`. Há uma única empresa,
mas a chave evita refatoração destrutiva caso uma filial seja criada.

Agregados principais:

- identidade: empresas, filiais, usuários, perfis, permissões e sessões;
- catálogo: produtos/serviços, códigos, categorias, marcas e unidades;
- pessoas: clientes e fornecedores;
- estoque: locais, movimentos, lotes de inventário e saldos derivados;
- compras: pedidos, itens e recebimentos;
- vendas: vendas, itens, pagamentos, suspensões e idempotência;
- caixa: terminais, sessões, movimentos e fechamento;
- financeiro: contas, parcelas, liquidações, categorias e contas financeiras;
- pós-venda: cancelamentos, devoluções e estornos;
- suporte: configurações, impressões, importações e auditoria.

Valores monetários usam `DECIMAL(15,2)` no banco. Quantidades usam
`DECIMAL(15,4)`. Códigos de barras usam `VARCHAR`, nunca tipos numéricos.

## Transação de venda

A finalização bloqueia a venda e a sessão de caixa, verifica uma chave de
idempotência e, numa única transação:

1. revalida preços, descontos, permissões e total;
2. grava os itens finais;
3. grava pagamentos explicitamente confirmados;
4. gera movimentos de estoque únicos por item;
5. gera contas/recebimentos quando aplicável;
6. gera movimento de caixa para dinheiro;
7. muda a venda para concluída;
8. registra auditoria.

Qualquer falha reverte todas as etapas.

## Impressão preservada

O backend entrega produtos e registra histórico, mas não gera TSPL. O navegador
continua responsável por:

- `layout.js`: dimensões e fileiras;
- `barcode.js`: simbologias;
- `render.js`: CSS print;
- `tspl.js`: bitmap, WebUSB e PRN.

Configurações físicas são associadas à estação, porque dois computadores podem
usar impressoras e calibrações diferentes. Preferências podem ser sincronizadas,
mas uma calibração remota nunca deve sobrescrever automaticamente a estação.

## Compatibilidade com MarketUP

Não será usada API privada. Serão oferecidos:

- exportação CSV;
- importação em duas fases;
- modelo de produtos/serviços ajustável aos arquivos oficiais;
- relatório de erros e protocolo;
- manutenção de códigos com zeros à esquerda.

## Fiscal

O banco armazenará NCM, CEST, origem, CFOP, CST/CSOSN e parâmetros necessários,
mas não haverá emissão no primeiro ciclo. A emissão depende de contador,
credenciamento, certificado, UF/município, provedor e homologação próprios.

## Backup e reversão

- tag do sistema estático: `backup-static-20261017`;
- migrations com `up` e `down`;
- dump do banco antes de atualização;
- uploads incluídos no backup;
- restauração testada em ambiente separado;
- retenção e RPO/RTO definidos antes da produção.
