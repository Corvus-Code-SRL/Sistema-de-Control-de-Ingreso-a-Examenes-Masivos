# Entorno de desarrollo con Docker

Entorno **opcional** para ejecutar el backend de SCIEM sin instalar PHP en el sistema.
Reproduce las versiones fijadas en el README principal. El frontend se sigue ejecutando
fuera de Docker (Node 22.23.2 y npm 10.9.8).

Este entorno es solo para **desarrollo local**. El despliegue con Apache se documentará
por separado.

## Servicios

| Servicio   | Imagen                 | Versión | Acceso                                    |
|------------|------------------------|---------|-------------------------------------------|
| `app`      | `sciem-php:dev` (local)| PHP 8.0.30 · Composer 2.9.6 | `http://localhost:8000`   |
| `postgres` | `postgres:15.0`        | 15.0    | Interno `postgres:5432` · Host `localhost:5433` |
| `redis`    | `redis:7.4.3`          | 7.4.3   | Solo interno: `redis:6379`                |

- Extensiones PHP instaladas: `pdo_pgsql`, `bcmath`, `redis`, `zip` (el resto de las exigidas
  por el README vienen incluidas en la imagen oficial).
- PostgreSQL se publica en **5433** para no chocar con una instalación local en 5432.
- Los datos de PostgreSQL se guardan en el volumen `sciem_postgres_data`.

## Requisitos

- Docker Engine (o Docker Desktop) con Docker Compose v2.
- En Windows: trabajar con el repositorio dentro del sistema de archivos de WSL 2.

Todos los comandos se ejecutan desde la **raíz del repositorio**.

## Primera configuración

```bash
DC="docker compose -f deployment/docker/docker-compose.dev.yml"

# 1. Levantar PostgreSQL y Redis (la primera vez crea sciem_db y sciem_test)
$DC up -d postgres redis

# 2. Instalar dependencias del backend (respeta composer.lock)
$DC run --rm app composer install

# 3. Crear el .env y ajustar las variables para Docker
cp backend/.env.example backend/.env
```

Valores que cambian respecto de `.env.example`:

```dotenv
DB_HOST=postgres
DB_PASSWORD=sciem_dev
REDIS_HOST=redis
```

```bash
# 4. Clave de la aplicación, tablas de Laravel y pruebas
$DC run --rm app php artisan key:generate
$DC run --rm app php artisan migrate
$DC run --rm app php artisan test

# 5. Levantar el backend
$DC up -d
```

## Uso diario

```bash
DC="docker compose -f deployment/docker/docker-compose.dev.yml"

$DC up -d          # iniciar
$DC ps             # estado
$DC stop           # detener al terminar (libera memoria)
```

Frontend, en otra terminal:

```bash
cd frontend
npm ci             # solo si cambió package-lock.json
npm run dev        # http://localhost:5173
```

## Comandos frecuentes

```bash
$DC exec app php artisan <comando>                  # artisan
$DC exec app composer install                       # nunca composer update
$DC exec app php artisan test                       # pruebas
$DC logs -f app                                     # logs del servidor
$DC exec postgres psql -U postgres -d sciem_db      # consola SQL
$DC build app                                       # reconstruir la imagen tras cambiar el Dockerfile
```

## Base de datos

- Al crearse el volumen por primera vez se ejecutan, en orden:
  - `postgres/init/01-create-test-database.sql`, que crea `sciem_test`.
  - `docs/database/creation-script.sql`, que crea el esquema de `sciem_db`.
- Estos scripts **solo se ejecutan con el volumen vacío**. Si el script de creación cambia,
  la base se reinicia así:

```bash
  $DC down -v        # ATENCIÓN: borra todos los datos de sciem_db
  $DC up -d
  $DC exec app php artisan migrate
```

- Las pruebas usan `sciem_test` (definido en `phpunit.xml`) y recargan el esquema en cada
  corrida.
- **No ejecutar `php artisan config:cache` en desarrollo**: con la configuración cacheada,
  las pruebas dejarían de usar `sciem_test`.
- `$DC down` elimina los contenedores pero conserva los datos. Solo `down -v` los borra.

## Notas

- **UID distinto de 1000:** la imagen crea un usuario con UID/GID 1000 para que los archivos
  generados no queden a nombre de root. Si `id -u` devuelve otro valor, construir con
  `$DC build --build-arg UID=$(id -u) --build-arg GID=$(id -g) app`.
- **Debian 11:** PHP 8.0.30 solo existe sobre Debian 11 (bullseye), ya retirado. El Dockerfile
  instala paquetes desde `archive.debian.org` y omite el repositorio de seguridad, que ya
  no está disponible. Es aceptable para desarrollo local, no para producción.
- **Credenciales:** `sciem_dev` es una contraseña solo para desarrollo local.
- **Nuevas extensiones:** si un paquete nuevo exige una extensión, `composer install` falla.
  Se verifica con `$DC exec app composer check-platform-reqs`, se agrega la extensión al
  Dockerfile y se reconstruye con `$DC build app`.