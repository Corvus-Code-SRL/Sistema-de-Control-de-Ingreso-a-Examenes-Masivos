-- SCIEM — HU-21 Cargar nómina de estudiantes: public.estudiante.ci admite NULL
--
-- Aplica a una base local ya creada el mismo cambio que se incorporó a
-- creation-script.sql, sin reconstruirla:
--   * estudiante.ci deja de ser NOT NULL. Los estudiantes que llegan desde una nómina no
--     traen CI: se guarda NULL hasta que se verifica el documento en el ingreso (EX04).
--   * unq_estudiante_ci se conserva. En PostgreSQL una restricción UNIQUE admite varios
--     NULL (NULL nunca es igual a NULL), así que los estudiantes sin CI no chocan.
--   * Los CI temporales que generaba la versión anterior de la carga ("TMP" + 7
--     caracteres) eran inventados y nunca podrían coincidir con un documento real:
--     se ponen en NULL.
--
-- Uso:   psql -U postgres -d sciem_db -f docs/database/alter-hu-21-estudiante-ci.sql
--
-- Todo corre en una sola transacción: si algo falla, la base queda como estaba.

BEGIN;

ALTER TABLE public.estudiante
    ALTER COLUMN ci DROP NOT NULL;

UPDATE public.estudiante
   SET ci = NULL
 WHERE ci ~ '^TMP[0-9A-Z]{7}$';

COMMIT;
