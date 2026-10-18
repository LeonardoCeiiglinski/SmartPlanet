# SmartPlanet

ERP/PDV da SmartPlanet, a Boutique do Celular, com etiquetas 33 × 21 mm.

Guia completo em português: [`LEIA-ME.md`](LEIA-ME.md)

- Instalação local: [`docs/instalacao.md`](docs/instalacao.md)
- **Vercel:** [`docs/vercel.md`](docs/vercel.md)

```
php bin/migrate.php
php bin/install.php --company="SmartPlanet" --email="admin@empresa.com" --password="senha-com-10-ou-mais"
php -S localhost:8080 -t public public/router.php
```
