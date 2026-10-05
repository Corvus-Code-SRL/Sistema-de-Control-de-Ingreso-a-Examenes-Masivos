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
| `scheduler` | `sciem-php:dev` (local)| PHP 8.0.30 | **Perfil opcional** (`--profile scheduler`): no arranca con `up -d`. Sin puerto; ejecuta el planificador cada minuto |
| `postgres` | `postgres:15.0`        | 15.0    | Interno `postgres:5432` · Host `localhost:5433` |
| `redis`    | `redis:7.4.3`          | 7.4.3   | Interno `redis:6379` · Host `localhost:6379` |

- Extensiones PHP instaladas: `pdo_pgsql`, `bcmath`, `redis`, `zip`, `gd` (el resto de las
  exigidas por el README vienen incluidas en la imagen oficial). `gd` la exige
  `phpoffice/phpspreadsheet`, con el que HU-021 lee las nóminas en XLSX.
- Límites de subida: `upload_max_filesize = 12M` y `post_max_size = 16M`, definidos en
  `php/conf.d/sciem-uploads.ini`. Los valores por defecto de PHP (2M y 8M) se quedan por
  debajo de los 10 MB que admite la nómina de HU-021, y un archivo descartado por PHP
  nunca llega al validador de Laravel.
- PostgreSQL se publica en **5433** para no chocar con una instalación local en 5432.
- Redis se publica en **6379** para que el backend ejecutado fuera de Docker use `REDIS_HOST=127.0.0.1`.
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
$DC logs -f scheduler                               # aperturas automáticas (solo si se levantó con el perfil)
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

## Apertura automática de ingreso

Un examen pasa a `EN_INGRESO` **solo** por el planificador de Laravel: no hay acción manual de "abrir
control" ni ruta HTTP para hacerlo (eso es de la HU-12). Cada minuto, `OpenEntryControlJob` busca los
exámenes `PROGRAMADO` cuya hora de inicio esté dentro de `examen.minutos_apertura` minutos (10 por defecto,
entre 0 y 30, en la zona `SCIEM_ZONA_HORARIA`) y que aún no hayan terminado. Si tienen nómina y ambientes
con capacidad suficiente, prepara en Redis un snapshot versionado (identidades, pertenencia, aulas,
antecedentes, permisos, contadores y últimos ingresos), lo activa y recién entonces cambia el estado. Si no
tienen nómina o capacidad, conservan `PROGRAMADO` y el motivo queda en el log de Laravel. Durante
`EN_INGRESO`, verificar, buscar y consultar `/estado` leen Redis; confirmar persiste en PostgreSQL y
actualiza Redis después del commit. Si se pierden las claves de un examen abierto, el siguiente ciclo
reconstruye el snapshot. El cambio de estado no requiere ninguna migración.

### ⚠ Nunca con la base compartida

El planificador **escribe**: cambia el estado de exámenes y llena Redis. Con el `.env` de Supabase movería
exámenes reales del equipo. Por eso el servicio `scheduler` está en el perfil opcional `scheduler`
(`docker compose up -d` jamás lo inicia) y, además, fija `DB_*` a la PostgreSQL local de este compose, que
pisa lo que diga `backend/.env`. El servicio `app` **no** hace eso: usa `backend/.env` tal cual, así que para
una demostración completa ese archivo debe apuntar también a la base local.

### Pasos para una demostración local

```bash
DC="docker compose -f deployment/docker/docker-compose.dev.yml"

# 1. Respaldar el .env compartido y apuntar el .env a la base local (restáuralo al terminar)
cp backend/.env backend/.env.compartido
```

En `backend/.env` deja estos valores (el resto sigue igual):

```dotenv
APP_ENV=local
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=sciem_db
DB_USERNAME=postgres
DB_PASSWORD=sciem_dev
DB_SSLMODE=prefer
REDIS_HOST=redis
SCIEM_DOCENTE_FIJO_ID=00000000-0000-4000-8000-000000000011
SCIEM_PERIODO_ACTIVO_ID=9303
```

```bash
# 2. Levantar solo PostgreSQL y Redis y recrear sciem_db con las migraciones
$DC up -d postgres redis
$DC exec postgres psql -U postgres -c "DROP DATABASE IF EXISTS sciem_db" -c "CREATE DATABASE sciem_db"

# 3. Migrar y cargar los datos de prueba. `run` con el perfil usa las DB_* fijas del scheduler,
#    así que estos comandos no pueden tocar la base compartida
R="$DC --profile scheduler run --rm scheduler"
$R php artisan migrate --force
$R php artisan db:seed --class="Database\Seeders\TestData\TestDataSeeder"

# 4. Comprobar el destino ANTES de levantar nada más (debe decir: postgres / sciem_db)
$R php artisan tinker --execute="echo config('database.connections.pgsql.host').' / '.DB::connection()->getDatabaseName();"

# 5. Levantar el backend y, a propósito, el planificador
$DC up -d app
$DC --profile scheduler up -d scheduler
$DC logs -f scheduler
```

Para ver el control de ingreso:

1. Entra a la interfaz con el docente fijo: código SIS **`10452`** y contraseña **`password`** (Marcelo
   Quiroga; es la cuenta de `SCIEM_DOCENTE_FIJO_ID` y dueña de los exámenes que crea la aplicación). Los
   auxiliares sembrados son `201800451` (Daniela Ferrufino) y `201900782` (Iván Choque), con la misma
   contraseña. Las rutas de control de ingreso exigen sesión; no hay usuario de desarrollo implícito.
2. Crea un examen con grupos que tengan nómina y ambientes con capacidad, y una hora de inicio dentro de
   los próximos `minutos_apertura` minutos (por ejemplo, 5 minutos desde ahora).
3. Si quieres que un auxiliar controle, habilítalo (HU-08) y, si el examen tiene más de un ambiente,
   asígnale uno (HU-09). Con un solo ambiente controla ese sin asignación.
4. Espera el siguiente minuto: el log del `scheduler` muestra `open-entry-control` y el examen aparece en
   **Control de ingreso**. Para forzar un ciclo sin esperar: `$R php artisan schedule:run`.

Al terminar:

```bash
$DC --profile scheduler stop scheduler    # o: $DC --profile scheduler down
cp backend/.env.compartido backend/.env   # vuelve a la base compartida
```

En una instalación sin Docker, ejecuta `php artisan schedule:work` en otro proceso (o `schedule:run` por cron
cada minuto), **siempre con un `.env` que apunte a una base local**.
