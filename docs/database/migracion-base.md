# Migración base del esquema

`backend/database/migrations/2026_09_25_000000_create_baseline_schema.php` reproduce el esquema vigente
(7 tipos enum, 34 tablas, índices y restricciones, con los cambios de HU-21 y HU-24 ya incorporados).
Ejecuta una copia **congelada** de `docs/database/creation-script.sql` guardada en
`backend/database/schema/baseline.sql`. Ese archivo y la migración no se editan nunca: todo cambio
estructural posterior va en una migración nueva (`php artisan make:migration ...`).

Sustituye a las seis migraciones parciales anteriores (`create_enum_types`, `create_rol_table`,
`create_usuario_table`, `create_usuario_rol_table`, `create_accion_table`, `create_log_table`), que solo
cubrían 6 de las 34 tablas y ya no coincidían con el script. Se conservan las de Laravel/Sanctum
(`failed_jobs`, `personal_access_tokens`), que no forman parte del script.

`down()` lanza una excepción a propósito: revertir la base borraría todo el esquema.

## 1. Base nueva (PostgreSQL 15 limpio, p. ej. el servidor del Sprint 6)

```bash
createdb -U postgres sciem_db
cd backend
php artisan migrate
```

Requiere que el usuario pueda ejecutar `CREATE EXTENSION pgcrypto` (el script lo incluye). Verificado en
PostgreSQL 15.19 limpio: el resultado es idéntico al de cargar `creation-script.sql`, más las tablas
`migrations`, `failed_jobs` y `personal_access_tokens` de Laravel.

## 2. Base compartida donde el esquema ya existe (Supabase)

La migración base **no debe ejecutarse** ahí: fallaría en el primer `CREATE TYPE`. Se marca como aplicada.
Lo hace **una sola persona**, avisando antes en el canal del equipo.

1. Confirmar que el esquema está al día con HU-21 y HU-24 (si no, aplicar primero
   `alter-hu-21-estudiante-ci.sql` y `alter-hu-24-examen.sql`, que son idempotentes):

   ```sql
   SELECT count(*) FROM pg_tables WHERE schemaname = 'public';           -- 34 (más las de Laravel, si existen)
   SELECT is_nullable FROM information_schema.columns
    WHERE table_name = 'estudiante' AND column_name = 'ci';              -- YES
   SELECT column_name FROM information_schema.columns
    WHERE table_name = 'examen' AND column_name IN ('id_carrera','id_materia','id_usuario_docente','estado'); -- 4 filas
   ```

2. Crear la tabla de control (solo agrega una tabla; no toca datos):

   ```bash
   php artisan migrate:install
   ```

3. Marcar la migración base como ejecutada:

   ```bash
   php artisan tinker --execute="DB::table('migrations')->insert(['migration' => '2026_09_25_000000_create_baseline_schema', 'batch' => DB::table('migrations')->max('batch') + 1]);"
   ```

   Equivale al SQL: `INSERT INTO migrations (migration, batch) VALUES ('2026_09_25_000000_create_baseline_schema', 1);`

4. Verificar con `--pretend`, que no ejecuta nada:

   ```bash
   php artisan migrate --pretend
   ```

   La salida solo debe listar `failed_jobs` y `personal_access_tokens`, y solo si esas tablas no existen
   en la base (crearlas es aditivo e inocuo). Si ya existen, márquelas igual que en el paso 3 con sus
   nombres (`2019_08_19_000000_create_failed_jobs_table`, `2019_12_14_000001_create_personal_access_tokens_table`).
   La migración base **no** debe aparecer. Con todo marcado, la salida es `Nothing to migrate.`

5. Ejecutar `php artisan migrate` y comprobar con `php artisan migrate:status` que todo figura como `Yes`.

Este procedimiento se probó en una base local creada solo con `creation-script.sql`.

## 3. Bases locales creadas con las migraciones parciales antiguas

Su tabla `migrations` conserva seis filas de archivos que ya no existen (es inofensivo), pero la migración
base figura como pendiente y fallaría. Opciones: recrear la base local (`DROP DATABASE` / `createdb` /
`php artisan migrate`) o marcar la migración base como en el punto 2, pasos 3 a 5.

## 4. Pruebas automáticas

`tests/TestCase.php` sigue cargando `docs/database/creation-script.sql` en `sciem_test`. Mientras ese
script no se actualice a la par de cada migración nueva, las pruebas no verán los cambios estructurales
posteriores a la base. Pendiente de decisión del equipo: que `TestCase` cargue el esquema con
`php artisan migrate` en lugar del script, conservando intacta la verificación del nombre `sciem_test`.
