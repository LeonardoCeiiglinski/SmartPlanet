# Publicar o SmartPlanet na Vercel

A Vercel não guarda arquivo de banco. O ERP usa **PHP 8.5** (`vercel-php`) e **MySQL/MariaDB persistente**.

## 1. Banco MySQL

Crie um banco (PlanetScale, Railway, Aiven, TiDB Cloud, Amazon Aurora etc.) e copie a URL:

`mysql://USUARIO:SENHA@HOST:3306/NOME_DO_BANCO`

## 2. GitHub

Importe [https://github.com/LeonardoCeiiglinski/SmartPlanet](https://github.com/LeonardoCeiiglinski/SmartPlanet) no [dashboard da Vercel](https://vercel.com/new). Framework: **Other**.

## 3. Variáveis de ambiente na Vercel

| Nome | Valor |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | 64 caracteres aleatórios |
| `APP_TIMEZONE` | `America/Sao_Paulo` |
| `DATABASE_URL` | URL MySQL |
| `DB_SSL` | `true` |
| `AUTO_MIGRATE` | `true` |
| `SESSION_DRIVER` | `database` |
| `SESSION_SECURE` | `true` |
| `INSTALL_COMPANY` | `SmartPlanet` |
| `INSTALL_EMAIL` | e-mail do dono da loja |
| `INSTALL_PASSWORD` | senha com 10+ caracteres |
| `INSTALL_NAME` | nome do administrador |

Na primeira requisição o sistema aplica as migrations e cria a empresa se `INSTALL_*` estiver preenchido. Depois você pode apagar `INSTALL_PASSWORD` das variáveis.

## 4. Deploy

A cada push em `main` a Vercel publica. Root do projeto: a pasta do repositório (não só `public/`).

Acesse `https://SEU-PROJETO.vercel.app/login`.

## Limites

- Disco local na Vercel é temporário: estoque, vendas e login ficam no MySQL.
- WebUSB da impressora térmica exige HTTPS (a Vercel já entrega HTTPS).
- Upload de CSV grande pode estourar o tempo da função (hoje 30 s).
