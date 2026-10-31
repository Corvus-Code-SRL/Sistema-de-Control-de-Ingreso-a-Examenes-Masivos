-- SCIEM — Lleva una base con el esquema de la HU-24 al esquema final de creation-script.sql
--
-- Para bases que ya existían (la base compartida de Supabase) y no se pueden reconstruir.
-- Una base nueva no lo necesita: la migración base ya trae todo esto.
--
-- Requisitos: haber aplicado antes alter-hu-21-estudiante-ci.sql y alter-hu-24-examen.sql.
--
-- Cambios:
--   * periodo.gestion pasa de varchar(10) a smallint, entre 1900 y 2200.
--   * examen.minutos_apertura (0 a 30, por defecto 10).
--   * Trigger que solo permite cancelar un examen que está PROGRAMADO.
--   * Tablas grupo_auxiliar y examen_auxiliar (HU-08, HU-09), con sus índices.
--
-- Uso:   psql -U postgres -d sciem_db -f docs/database/alter-esquema-final.sql
--
-- Todo corre en una sola transacción: si algo falla, la base queda como estaba.

BEGIN;

---------------------------------------------------
-- 1. periodo.gestion
---------------------------------------------------

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = 'periodo'
           AND column_name = 'gestion' AND data_type = 'character varying'
    ) THEN
        IF EXISTS (SELECT 1 FROM public.periodo WHERE gestion !~ '^[0-9]{4}$') THEN
            RAISE EXCEPTION 'periodo.gestion tiene valores que no son un año de cuatro dígitos.';
        END IF;

        ALTER TABLE public.periodo
            ALTER COLUMN gestion TYPE smallint USING gestion::smallint;
    END IF;
END$$;

ALTER TABLE public.periodo
    DROP CONSTRAINT IF EXISTS chk_periodo_gestion;

ALTER TABLE public.periodo
    ADD CONSTRAINT chk_periodo_gestion
        CHECK (gestion BETWEEN 1900 AND 2200);

---------------------------------------------------
-- 2. examen.minutos_apertura
---------------------------------------------------

ALTER TABLE public.examen
    ADD COLUMN IF NOT EXISTS minutos_apertura integer DEFAULT 10 NOT NULL;

ALTER TABLE public.examen
    DROP CONSTRAINT IF EXISTS chk_examen_minutos_apertura;

ALTER TABLE public.examen
    ADD CONSTRAINT chk_examen_minutos_apertura
        CHECK (minutos_apertura BETWEEN 0 AND 30);

---------------------------------------------------
-- 3. Cancelación solo desde PROGRAMADO
---------------------------------------------------

CREATE OR REPLACE FUNCTION public.fn_examen_cancelar_solo_programado()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.estado = 'CANCELADO' AND OLD.estado <> 'PROGRAMADO' THEN
        RAISE EXCEPTION
            'Un examen solo puede cancelarse desde el estado PROGRAMADO (estado actual: %).', OLD.estado
            USING ERRCODE = 'check_violation';
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_examen_cancelar_solo_programado ON public.examen;

CREATE TRIGGER trg_examen_cancelar_solo_programado
    BEFORE UPDATE OF estado ON public.examen
    FOR EACH ROW
    WHEN (NEW.estado IS DISTINCT FROM OLD.estado)
    EXECUTE FUNCTION public.fn_examen_cancelar_solo_programado();

---------------------------------------------------
-- 4. grupo_auxiliar
---------------------------------------------------

CREATE TABLE IF NOT EXISTS public.grupo_auxiliar (
    id_grupo             integer NOT NULL,
    id_usuario           uuid NOT NULL,
    fecha_incorporacion  date NOT NULL,
    estado               public.estado_registro NOT NULL,

    CONSTRAINT pk_grupo_auxiliar
        PRIMARY KEY (id_grupo, id_usuario),

    CONSTRAINT fk_grupo_auxiliar_grupo
        FOREIGN KEY (id_grupo)
        REFERENCES public.grupo(id_grupo),

    CONSTRAINT fk_grupo_auxiliar_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES public.usuario(id_usuario)
);

CREATE INDEX IF NOT EXISTS idx_grupo_auxiliar_usuario
    ON public.grupo_auxiliar(id_usuario);

CREATE INDEX IF NOT EXISTS idx_grupo_auxiliar_grupo_estado
    ON public.grupo_auxiliar(id_grupo, estado);

---------------------------------------------------
-- 5. examen_auxiliar
---------------------------------------------------

CREATE TABLE IF NOT EXISTS public.examen_auxiliar (
    id_examen                    integer NOT NULL,
    id_usuario                   uuid NOT NULL,
    id_usuario_docente_habilita  uuid NOT NULL,
    fecha_habilitacion           timestamptz DEFAULT CURRENT_TIMESTAMP NOT NULL,
    id_ambiente                  integer,

    CONSTRAINT pk_examen_auxiliar
        PRIMARY KEY (id_examen, id_usuario),

    CONSTRAINT fk_examen_auxiliar_examen
        FOREIGN KEY (id_examen)
        REFERENCES public.examen(id_examen),

    CONSTRAINT fk_examen_auxiliar_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES public.usuario(id_usuario),

    CONSTRAINT fk_examen_auxiliar_usuario_docente_habilita
        FOREIGN KEY (id_usuario_docente_habilita)
        REFERENCES public.usuario(id_usuario),

    CONSTRAINT fk_examen_auxiliar_examen_ambiente
        FOREIGN KEY (id_examen, id_ambiente)
        REFERENCES public.examen_ambiente(id_examen, id_ambiente)
);

CREATE INDEX IF NOT EXISTS idx_examen_auxiliar_usuario
    ON public.examen_auxiliar(id_usuario);

CREATE INDEX IF NOT EXISTS idx_examen_auxiliar_examen_ambiente
    ON public.examen_auxiliar(id_examen, id_ambiente);

CREATE INDEX IF NOT EXISTS idx_examen_auxiliar_docente_habilita
    ON public.examen_auxiliar(id_usuario_docente_habilita);

COMMIT;
