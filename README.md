# Praxis SV (API)

Backend Laravel de Praxis. Expone la API que consume [`praxis_client`](https://github.com/IkerMiguel/praxis_client) (Next.js en Vercel).  
ORM: **Eloquent**. Base de datos local: **PostgreSQL en Docker**.

## Requisitos

- PHP 8.4+ con `pdo_pgsql`, `pgsql` (y `pdo_sqlite` para tests)
- Composer
- Docker Desktop

## Setup local

```bash
docker compose up -d
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

API: [http://127.0.0.1:8000](http://127.0.0.1:8000)

## Base de datos (Docker)

```bash
docker compose up -d
```

| Variable | Valor |
|----------|--------|
| Host | `127.0.0.1` |
| Puerto | `5433` |
| Database | `praxis_sv` |
| User | `praxis` |
| Password | `praxis_secret` |

```bash
docker compose down
```

## ORM (Eloquent)

Modelos en `app/Models/`. Migraciones en `database/migrations/`.

```php
use App\Models\User;

User::query()->where('email', 'like', '%@example.com')->get();
```

## Tests

Pest usa SQLite en memoria (no necesita Docker):

```bash
php artisan test
```

## CI / CD

### CI (GitHub Actions)

En cada push/PR a `main` corre `.github/workflows/ci.yml`:

1. Composer install  
2. Laravel Pint (`--test`)  
3. Pest (`php artisan test`)

### CD (Railway — cuando toque producción)

1. Crear proyecto en [Railway](https://railway.app) y conectar este repo.
2. Añadir servicio **PostgreSQL** y vincular variables `DB_*` / `DATABASE_URL` a Laravel.
3. Variables mínimas: `APP_KEY`, `APP_URL`, `APP_ENV=production`, `DB_*`.
4. **Release / Deploy command** (ejemplo):
   ```bash
   php artisan migrate --force && php artisan config:cache
   ```
5. Start command típico:
   ```bash
   php artisan serve --host=0.0.0.0 --port=$PORT
   ```
   (o el que indique la plantilla PHP/Laravel de Railway).
6. En Vercel (`praxis_client`) definir:
   ```text
   NEXT_PUBLIC_API_URL=https://tu-servicio.up.railway.app
   ```
7. Configurar CORS en Laravel para el dominio de Vercel.

Hasta tener endpoints reales, el deploy a Railway puede esperar.

## Scripts útiles

| Comando | Descripción |
|---------|-------------|
| `docker compose up -d` | Levanta Postgres |
| `php artisan migrate` | Migraciones |
| `php artisan serve` | Servidor local |
| `php artisan test` | Pest |
| `vendor/bin/pint` | Formato de código |
