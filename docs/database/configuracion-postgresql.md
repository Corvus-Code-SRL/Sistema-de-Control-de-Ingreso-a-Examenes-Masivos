# Configuración y monitoreo de PostgreSQL (RNF-14)

PostgreSQL trabaja con procesos en segundo plano (*daemons*). Esta guía separa lo que SCIEM aplica **sin
privilegios de superusuario** de lo que ya resuelve el deploy con Docker.

> Producción: Docker Compose (`deployment/docker/`). PostgreSQL 15 corre en el contenedor `postgres`; sus
> daemons se ven con `$DC top postgres`. La aplicación se conecta con el usuario `sciem_app`, que **no** es
> superusuario. En desarrollo (sin Docker) los mismos comandos se ejecutan directo con `php artisan`.

En esta guía, `DC` es el atajo del deploy (se ejecuta desde la raíz del repositorio):

```bash
DC="docker compose --env-file deployment/docker/.env -f deployment/docker/docker-compose.yml -p sciem-prod"
```

## Qué aplica SCIEM y qué no

| Pieza | Daemon | ¿Superusuario? | Quién lo aplica |
|---|---|---|---|
| Nombre `sciem-web` / `sciem-consola` en cada sesión | backend | No | Laravel, al conectarse (código) |
| Límites de sesión: `statement_timeout`, `lock_timeout`, `idle_in_transaction_session_timeout` | backend | No | Laravel, al conectarse, con los valores del `.env` |
| Respuesta 503 «El sistema está ocupado…» si la base corta por tiempo o por lock | — | No | Laravel (código) |
| Autovacuum más frecuente en `examen_estudiante`, `intento_ingreso` y `log` | autovacuum | No: basta ser **dueño de las tablas** | `app-init` al aplicar la migración `2026_11_19_000000` (o la versión 20 del script de creación) |
| Monitoreo con `php artisan sciem:salud-bd` | stats | No | Equipo, con `$DC exec` |
| Log de consultas lentas (`log_min_duration_statement = 500ms`, `log_lock_waits = on`) | logger | Sí, pero **ya está resuelto** | `command` del servicio `postgres` en `docker-compose.yml` |
| Recuperación tras un corte de luz (`fsync` y `synchronous_commit` en `on`) | WAL writer | No | Nadie: configuración por defecto. **No desactivarla** |
| Escritura a disco gradual | checkpointer / background writer | No | Nadie: configuración por defecto |
| Máximo de conexiones (`max_connections = 100`) | postmaster | Sí | No se toca: Apache atiende como máximo 30 peticiones a la vez (`apache/mpm-prefork.conf`) |
| Arranque automático tras reiniciar el servidor | — | No | `restart: unless-stopped` en `postgres`, `redis`, `web` y `scheduler`, con el servicio de Docker en inicio automático |

Los límites de sesión **no** se aplican con `ALTER ROLE` ni `ALTER DATABASE`: los pone Laravel en su propia
sesión al conectarse, y cualquier usuario puede hacer `SET` en su sesión.

## 1. Variables de entorno

En producción van en `deployment/docker/.env`, el único `.env` del deploy; Compose lo entrega a `web`,
`scheduler` y `app-init`. En desarrollo, en `backend/.env`.

```dotenv
DB_APPLICATION_NAME=sciem
DB_STATEMENT_TIMEOUT=15000                 # 15 s: lo mismo que espera la SPA antes de rendirse
DB_LOCK_TIMEOUT=10000                      # 10 s: deja de esperar un lockForUpdate
DB_IDLE_IN_TRANSACTION_TIMEOUT=60000       # 60 s: cierra una transacción olvidada y libera sus locks
```

- **Si no se definen, quedan vacíos (sin límite).** Encenderlos solo después de probar con datos reales: carga
  de una nómina grande, reportes Excel y varios verificadores a la vez. Si no hay tiempo de probarlos, se
  dejan sin definir y el sistema funciona igual que antes.
- Solo se aplican a las **peticiones web**. Las migraciones (`app-init`), los respaldos (`sciem:respaldar`,
  `sciem:restaurar`) y el planificador corren sin límite.
- Los límites son **por consulta**, no por petición: la carga de nómina hace muchas consultas cortas, y
  armar un Excel es trabajo de PHP, no de la base.
- `DB_STATEMENT_TIMEOUT=15000` coincide con el plazo de la SPA para una petición normal
  (`DEFAULT_REQUEST_TIMEOUT_MS`): más allá de eso el usuario ya dejó de esperar.
- Si la base corta una consulta, la API responde **503** y el frontend lo trata como cualquier error 5xx de
  hoy («El servidor no pudo responder. Intente nuevamente.»); el sondeo reintenta solo.
- Si se cierra una sesión por `DB_IDLE_IN_TRANSACTION_TIMEOUT`, la respuesta es **500**, no 503: PostgreSQL
  corta la conexión entera y PDO informa `HY000`. No es saturación sino un bug (una transacción que quedó
  abierta sin hacer nada), y debe revisarse en el log de Laravel.
- Caso raro: la comprobación del código SIS al registrar una cuenta (`useSisCodeCheck`) interpreta cualquier
  503 como «SIS no disponible». Si la base cortara justo esa consulta, el mensaje sería engañoso. No bloquea
  el registro.
- **Después de cambiar el `.env` en producción**, recrear los contenedores de Laravel:
  `$DC up -d --force-recreate web scheduler`. Al arrancar, cada contenedor hace `config:cache` con el `.env`
  nuevo; un `restart` o un `config:clear` no alcanzan. En desarrollo basta `php artisan config:clear`.

## 2. Comprobaciones del deploy (sin superusuario)

1. **Autovacuum:** `app-init` corre `php artisan migrate --force` en cada `up`, así que la migración
   `2026_11_19_000000` se aplica sola. Comprobarlo con la consulta de la sección 5.
2. **Arranque automático:** el servicio de Docker del servidor debe iniciar con el sistema. Con eso, tras un
   corte de luz `postgres`, `redis`, `web` y `scheduler` vuelven solos (`restart: unless-stopped`) y
   PostgreSQL se recupera con el WAL.
3. **UPS:** preguntar al laboratorio si el servidor tiene. Sin UPS, un corte apaga SCIEM hasta que vuelva la
   energía (los ingresos ya confirmados no se pierden).
4. **No tocar** `fsync`, `synchronous_commit` ni `full_page_writes`: son las garantías del WAL.

## 3. Log de consultas lentas (ya activo)

El servicio `postgres` de `docker-compose.yml` arranca con `log_min_duration_statement=500ms` y
`log_lock_waits=on`. Toda consulta de más de medio segundo y toda espera larga de lock quedan en el log del
contenedor:

```bash
$DC logs postgres | grep duration          # consultas de más de 500 ms
$DC logs postgres | grep "still waiting"   # esperas de lock
```

Sirve como evidencia del RNF de rendimiento: «ninguna consulta pasó de 500 ms», o cuál pasó y por qué.

## 4. Monitoreo en un día de examen

```bash
$DC exec -u www-data web php artisan sciem:salud-bd              # abiertas hace 30 s o más
$DC exec -u www-data web php artisan sciem:salud-bd --umbral=5   # más sensible, durante el ingreso
```

Muestra, sin cambiar nada:

1. **Conexiones usadas** contra `max_connections`; avisa desde el 80 %.
2. **Sesiones por aplicación y estado** (`sciem-web`, `sciem-consola`…).
3. **Consultas o transacciones largas.** Una transacción abierta y quieta (`idle in transaction`) retiene
   locks y frena la limpieza.
4. **Sesiones esperando un lock**, y qué sesión las bloquea.
5. **Tablas con más filas muertas**, y cuándo pasó el autovacuum.

El comando usa el mismo usuario que la aplicación (`sciem_app`), así que ve completas las sesiones de SCIEM.
De las sesiones de otros usuarios de la base, PostgreSQL oculta la consulta y el estado.

Sugerencia para una prueba con la ingeniera: correrlo antes de empezar, cada pocos minutos durante el ingreso
y al terminar, guardando cada salida con su hora.

`sciem:salud-bd` nunca cierra sesiones. Si una quedó trabada:

```bash
$DC exec postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "SELECT pg_cancel_backend(<pid>)"'     # cancela la consulta
$DC exec postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "SELECT pg_terminate_backend(<pid>)"'  # cierra la sesión (último recurso)
```

## 5. Comprobar que quedó aplicado

```bash
$DC exec postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"' <<'SQL'
-- Autovacuum ajustado en las tablas de alta escritura:
SELECT relname, reloptions FROM pg_class
 WHERE relname IN ('examen_estudiante', 'intento_ingreso', 'log');

-- Nombre de las sesiones de SCIEM (con la aplicación en uso):
SELECT application_name, count(*) FROM pg_stat_activity
 WHERE application_name LIKE 'sciem%' GROUP BY 1;
SQL
```
