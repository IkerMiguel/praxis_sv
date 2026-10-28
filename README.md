# Praxis SV (API)

Backend Laravel 13 de **PRAXIS**, la plataforma de gestión y trazabilidad de incidencias de infraestructura física universitaria. Expone la API REST `/api/v1` que consume [`praxis_client`](https://github.com/IkerMiguel/praxis_client) (Next.js).
ORM: **Eloquent**. Base de datos: **PostgreSQL 16**. Autenticación: **Laravel Sanctum**.

## Levantar todo con Docker (recomendado)

Requisitos: Docker Desktop (o Docker Engine + Compose v2).

```bash
docker compose up -d --build
```

```bash
docker compose exec app php artisan migrate --seed
```

Eso es todo:

| Servicio | URL / puerto | Descripción |
|----------|--------------|-------------|
| `app` | [http://localhost:8000](http://localhost:8000) | API (PHP 8.4 + PHP-FPM + Nginx). Healthcheck en `/up`. |
| `postgres` | `localhost:5433` | PostgreSQL 16 (`praxis_sv` / `praxis` / `praxis_secret`). |
| `scheduler` | — | `php artisan schedule:work` (vencimiento de aclaraciones, RN-010). |

- La app espera a que PostgreSQL esté *healthy* antes de arrancar (`depends_on: condition: service_healthy`).
- Al primer arranque el contenedor crea su `.env` a partir de `.env.example` y genera `APP_KEY`.
- Las evidencias se guardan en el volumen `praxis_sv_storage`; los datos de la BD en `praxis_sv_pgdata`.

Comandos útiles:

```bash
docker compose logs -f app
```

```bash
docker compose exec app php artisan test
```

```bash
docker compose exec app php artisan migrate:fresh --seed
```

```bash
docker compose down
```

Para borrar también los volúmenes (BD y evidencias):

```bash
docker compose down -v
```

### Usuarios de prueba (seeder)

Todos con contraseña `Praxis*2026`:

| Correo | Rol(es) |
|--------|---------|
| `reportante@praxis.test` | R01 Reportante |
| `gestor@praxis.test` | R02 Gestor de incidencias |
| `coordinador@praxis.test` | R03 Coordinador de mantenimiento |
| `tecnico@praxis.test` | R04 Técnico (electricidad, general) |
| `plomero@praxis.test` | R04 Técnico (hidrosanitario, obra civil) |
| `cerrajero@praxis.test` | R04 Técnico (cerrajería, carpintería) |
| `autorizador@praxis.test` | R05 Responsable de autorizaciones |
| `verificador@praxis.test` | R06 Verificador de intervenciones |
| `admin@praxis.test` | R07 Administrador de plataforma |
| `docente.verificador@praxis.test` | R01 + R06 (multi-rol) |

## Setup local sin Docker para la API

Requisitos: PHP 8.4 con `pdo_pgsql` (y `pdo_sqlite` para tests) y Composer. Se puede usar solo el Postgres de Docker:

```bash
docker compose up -d postgres
```

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

## Uso de la API

- Prefijo: `/api/v1`. Respuestas JSON; errores en formato **Problem Details (RFC 7807)** con `Content-Type: application/problem+json`.
- `POST /api/v1/auth/login` devuelve un `access_token` (Bearer, 15 minutos) y deja el refresh token en la cookie HttpOnly `praxis_refresh` (rotativo; `POST /api/v1/auth/refresh`).
- Rol activo: cabecera `X-Active-Role: R0x`. Debe ser uno de los roles del usuario; si se omite se usa su primer rol. Las operaciones validan el rol activo contra la matriz D09 (403 si no corresponde).
- `POST /api/v1/incidencias/radicar` admite la cabecera `Idempotency-Key` para evitar tickets duplicados por doble envío.
- Las evidencias se suben primero (`POST /api/v1/incidencias/evidencias/upload`, multipart `archivos[]`) y luego se referencian por id al radicar o al registrar diagnóstico o intervención. El tipo real del archivo se valida por *magic bytes* (JPG, PNG, WEBP o PDF).

Rutas: `php artisan route:list --path=api`.

## Arquitectura

| Capa | Ubicación |
|------|-----------|
| Rutas | `routes/api.php` (middleware `auth:sanctum`, `rol.activo`, `rol:Rxx`) |
| Controladores | `app/Http/Controllers/Api/V1` |
| Validación (equivalente a FluentValidation) | `app/Http/Requests` |
| Servicios de dominio | `app/Services` (máquina de estados, triaje, planificación, recursos, ejecución, verificación, SLA, auditoría, notificaciones) |
| Máquina de estados (D07) | `app/Services/MaquinaEstados.php` |
| Auditoría append-only (D10) | `eventos_auditoria` + trigger en PostgreSQL que bloquea UPDATE, DELETE y TRUNCATE |
| Catálogos y parámetros en caché | `app/Services/CatalogoService.php`, `app/Services/ParametrosService.php` |
| Tareas programadas | `routes/console.php` (`praxis:vencer-aclaraciones`) |

## Tests

Pest usa SQLite en memoria por defecto (no necesita Docker):

```bash
php artisan test
```

Contra PostgreSQL (lo que hace el job `postgres` del CI):

```bash
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5433 DB_DATABASE=praxis_test DB_USERNAME=praxis DB_PASSWORD=praxis_secret php artisan test
```

(crear antes la base: `docker compose exec postgres createdb -U praxis praxis_test`).

## CI / CD

### CI (GitHub Actions)

En cada push/PR a `main`, `master` o `dev` corre `.github/workflows/ci.yml`:

1. **Pint & Pest**: Composer install, `pint --test` y Pest sobre SQLite.
2. **PostgreSQL**: `migrate:fresh --seed` y Pest sobre un servicio PostgreSQL 16.

### CD (Railway, cuando toque producción)

1. Crear proyecto en [Railway](https://railway.app) y conectar este repo.
2. Añadir servicio **PostgreSQL** y vincular variables `DB_*` / `DATABASE_URL` a Laravel.
3. Variables mínimas: `APP_KEY`, `APP_URL`, `APP_ENV=production`, `DB_*`, `FRONTEND_URL`.
4. **Release / Deploy command** (ejemplo):
   ```bash
   php artisan migrate --force && php artisan config:cache
   ```
5. En Vercel (`praxis_client`) definir `NEXT_PUBLIC_API_URL=https://tu-servicio.up.railway.app`.
6. Configurar CORS en Laravel para el dominio de Vercel y `PRAXIS_REFRESH_COOKIE_SAMESITE=none` con `PRAXIS_REFRESH_COOKIE_SECURE=true`.

## Scripts útiles

| Comando | Descripción |
|---------|-------------|
| `docker compose up -d --build` | Levanta API, scheduler y Postgres |
| `docker compose exec app php artisan migrate --seed` | Migraciones y datos iniciales |
| `php artisan test` | Pest |
| `vendor/bin/pint` | Formato de código |
| `php artisan praxis:vencer-aclaraciones` | Ejecuta a mano el vencimiento RN-010 |
