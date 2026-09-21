-- SCIEM — HU-24 Crear el examen: cambios sobre public.examen
--
-- Aplica a una base local ya creada los mismos cambios que se incorporaron a
-- creation-script.sql, sin reconstruirla:
--   * Enum public.estado_examen (PROGRAMADO, EN_INGRESO, EN_CURSO, FINALIZADO, CANCELADO).
--   * examen.id_carrera, examen.id_materia -> materia_carrera (clave compuesta, como grupo).
--   * examen.id_usuario_docente -> usuario (docente que creó el examen).
--   * examen.estado, por defecto PROGRAMADO.
--   * chk_examen_horas se reemplaza por chk_examen_hora_fin: permite exámenes que
--     cruzan la medianoche. chk_examen_duracion limita la duración a menos de un día.
--
-- Uso:   psql -U postgres -d sciem_db -f docs/database/alter-hu-24-examen.sql
--
-- Todo corre en una sola transacción: si algo falla, la base queda como estaba.

BEGIN;

---------------------------------------------------
-- 1. Enum del ciclo de vida
---------------------------------------------------

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'estado_examen') THEN
        CREATE TYPE public.estado_examen AS ENUM (
            'PROGRAMADO',
            'EN_INGRESO',
            'EN_CURSO',
            'FINALIZADO',
            'CANCELADO'
        );
    END IF;
END$$;

---------------------------------------------------
-- 2. Columnas nuevas
---------------------------------------------------

ALTER TABLE public.examen
    ADD COLUMN IF NOT EXISTS id_carrera         integer,
    ADD COLUMN IF NOT EXISTS id_materia         integer,
    ADD COLUMN IF NOT EXISTS id_usuario_docente uuid,
    ADD COLUMN IF NOT EXISTS estado             public.estado_examen
                                                DEFAULT 'PROGRAMADO' NOT NULL;

---------------------------------------------------
-- 3. Exámenes creados antes de este cambio
---------------------------------------------------
-- id_usuario_docente es NOT NULL y no hay forma de deducir quién creó los
-- exámenes existentes. Si la base tiene exámenes de prueba, asígneles un docente
-- descomentando la sentencia siguiente (por ejemplo, el de SCIEM_DOCENTE_FIJO_ID):
--
-- UPDATE public.examen
--    SET id_usuario_docente = '00000000-0000-4000-8000-000000000000'
--  WHERE id_usuario_docente IS NULL;

DO $$
DECLARE
    sin_docente integer;
BEGIN
    SELECT count(*) INTO sin_docente FROM public.examen WHERE id_usuario_docente IS NULL;

    IF sin_docente > 0 THEN
        RAISE EXCEPTION
            'Hay % examen(es) sin docente creador. Asigne uno en la sección 3 del script y vuelva a ejecutarlo.',
            sin_docente;
    END IF;
END$$;

ALTER TABLE public.examen
    ALTER COLUMN id_usuario_docente SET NOT NULL;

---------------------------------------------------
-- 4. Restricciones
---------------------------------------------------

ALTER TABLE public.examen
    DROP CONSTRAINT IF EXISTS chk_examen_horas,
    DROP CONSTRAINT IF EXISTS chk_examen_hora_fin,
    DROP CONSTRAINT IF EXISTS chk_examen_duracion,
    DROP CONSTRAINT IF EXISTS chk_examen_materia_carrera,
    DROP CONSTRAINT IF EXISTS fk_examen_usuario_docente,
    DROP CONSTRAINT IF EXISTS fk_examen_materia_carrera;

ALTER TABLE public.examen
    ADD CONSTRAINT fk_examen_usuario_docente
        FOREIGN KEY (id_usuario_docente)
        REFERENCES public.usuario(id_usuario),

    ADD CONSTRAINT fk_examen_materia_carrera
        FOREIGN KEY (id_carrera, id_materia)
        REFERENCES public.materia_carrera(id_carrera, id_materia),

    ADD CONSTRAINT chk_examen_materia_carrera
        CHECK ((id_carrera IS NULL) = (id_materia IS NULL)),

    ADD CONSTRAINT chk_examen_hora_fin
        CHECK (
            hora_fin IS NULL
            OR duracion IS NULL
            OR hora_fin = hora_inicio + make_interval(mins => duracion)
        ),

    ADD CONSTRAINT chk_examen_duracion
        CHECK (duracion IS NULL OR (duracion > 0 AND duracion < 1440));

---------------------------------------------------
-- 5. Índices
---------------------------------------------------

CREATE INDEX IF NOT EXISTS idx_examen_materia_carrera
    ON public.examen(id_carrera, id_materia);

CREATE INDEX IF NOT EXISTS idx_examen_usuario_docente
    ON public.examen(id_usuario_docente, fecha);

CREATE INDEX IF NOT EXISTS idx_examen_estado
    ON public.examen(estado);

COMMIT;

---------------------------------------------------
-- Después de aplicar el script
---------------------------------------------------
-- 1. Sembrar los catálogos que usa la creación y la cancelación de exámenes:
--      php artisan db:seed --class=ExamTypeSeeder
--      php artisan db:seed --class=ActionSeeder
--
-- 2. La versión anterior de HU-24 creaba tipos de examen con el nombre del examen.
--    Para revisar los que hayan quedado en la base (no se borran automáticamente):
--
--    SELECT t.id_tipo_examen, t.nombre, t.categoria, count(e.id_examen) AS examenes
--      FROM public.tipo_examen t
--      LEFT JOIN public.examen e ON e.id_tipo_examen = t.id_tipo_examen
--     GROUP BY t.id_tipo_examen
--     ORDER BY t.id_tipo_examen;
