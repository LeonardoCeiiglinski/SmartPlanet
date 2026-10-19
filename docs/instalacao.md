# Instalação e operação

Ambiente local imediato usa SQLite. Produção deve usar MySQL 8 ou MariaDB 10.6
com PHP 8.2+, HTTPS e `pdo_mysql`.

## Restauração do aplicativo estático

Tag: `backup-static-20261017`

```
git checkout backup-static-20261017
```

O aplicativo de etiquetas original continua em `index.html` e pode ser aberto
sem PHP. O GitHub Pages permanece como versão estática.

## Instalação local

1. Copie `.env.example` para `.env` e defina `APP_KEY`.
2. `php bin/migrate.php`
3. `php bin/install.php --company="Empresa" --email="admin@empresa.com" --password="senha-com-10-ou-mais"`
4. `php -S localhost:8080 -t public public/router.php`
5. Abra `http://localhost:8080/login`.

O comando de instalação só roda se o banco estiver vazio.

## Vercel

Siga [`docs/vercel.md`](vercel.md). Não use SQLite na Vercel.

## Produção

1. Aponte o document root para `public/`.
2. Configure `.env` com `DB_DRIVER=mysql`, credenciais e `SESSION_SECURE=true`.
3. Gere `APP_KEY` aleatória de 64 caracteres.
4. Execute migrations e o instalador.
5. Garanta HTTPS para WebUSB das etiquetas.
6. Bloqueie acesso HTTP a `storage/`, `.env`, `database/` e `app/`.
7. Faça backup do banco e de `storage/` antes de atualizar.

## Testes

```
node tests/unit-tests.js
node tests/labels-integration-tests.js
node tests/print-tests.js
php tests/run-domain-tests.php
```

O teste de leitor físico e a calibração da impressora só podem ser confirmados
no equipamento. Os testes automatizados simulam o código de barras e a
geometria da etiqueta.

## Atualização

1. Backup do banco e de `storage/`.
2. `git pull`
3. `php bin/migrate.php`
4. Teste de login, PDV, etiquetas e um fechamento de caixa em homologação.
5. Para reverter o último lote de migrations: `php bin/migrate.php down`.
