# Rollback

## Aplicativo de etiquetas estático

```
git checkout backup-static-20261017
```

Abra `index.html`. Os dados dessa versão estão no `localStorage` do navegador,
não no banco PHP.

## Banco

- Antes de atualizar: dump MySQL (`mysqldump`) ou cópia do arquivo SQLite em
  `storage/app/erp.sqlite`.
- Reverter o último lote: `php bin/migrate.php down`.
- Restaurar o dump em ambiente separado e validar login, uma venda de teste e
  uma impressão de etiqueta antes de voltar à produção.

Migrations possuem `up` e `down`. Não apague vendas ou movimentos para
“corrigir” caixa; use cancelamento, devolução ou ajuste auditável.
