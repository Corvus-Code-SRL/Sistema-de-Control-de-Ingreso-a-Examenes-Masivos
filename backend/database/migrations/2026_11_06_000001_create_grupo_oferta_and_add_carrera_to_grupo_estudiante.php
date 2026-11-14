<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HU-021 (multicarrera): un grupo puede atender a estudiantes de varias carreras.
 *
 * grupo_oferta une el grupo con cada oferta (materia + carrera + período) que atiende. La clave
 * compuesta hacia oferta_academica obliga a que la carrera ofrezca la misma materia en el mismo
 * período; la clave compuesta hacia grupo impide que la materia o el período no coincidan con
 * los del grupo. grupo.id_carrera se conserva como carrera principal: el resto del sistema la usa.
 *
 * grupo_estudiante.id_carrera guarda con qué carrera está el estudiante en ese grupo. Es nula
 * solo para inscripciones viejas que el relleno no pudo resolver; las nuevas siempre la llevan.
 *
 * Relleno:
 *   1. Cada grupo existente recibe su fila en grupo_oferta con su carrera principal.
 *   2. Cada inscripción existente toma la carrera principal de su grupo.
 */
class CreateGrupoOfertaAndAddCarreraToGrupoEstudiante extends Migration
{
    public function up()
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE public.grupo
    ADD CONSTRAINT unq_grupo_materia_periodo
        UNIQUE (id_grupo, id_materia, id_gestion_periodo);

CREATE TABLE public.grupo_oferta (
    id_grupo            integer NOT NULL,
    id_carrera          integer NOT NULL,
    id_materia          integer NOT NULL,
    id_gestion_periodo  integer NOT NULL,
    fecha_registro      timestamp(0) with time zone DEFAULT now() NOT NULL,

    CONSTRAINT pk_grupo_oferta
        PRIMARY KEY (id_grupo, id_carrera),

    CONSTRAINT fk_grupo_oferta_grupo
        FOREIGN KEY (id_grupo, id_materia, id_gestion_periodo)
        REFERENCES public.grupo(id_grupo, id_materia, id_gestion_periodo)
        ON DELETE CASCADE,

    CONSTRAINT fk_grupo_oferta_oferta_academica
        FOREIGN KEY (id_carrera, id_materia, id_gestion_periodo)
        REFERENCES public.oferta_academica(id_carrera, id_materia, id_gestion_periodo)
);

CREATE INDEX idx_grupo_oferta_oferta
    ON public.grupo_oferta(id_carrera, id_materia, id_gestion_periodo);

INSERT INTO public.grupo_oferta (id_grupo, id_carrera, id_materia, id_gestion_periodo)
SELECT g.id_grupo, g.id_carrera, g.id_materia, g.id_gestion_periodo
FROM public.grupo g
ON CONFLICT DO NOTHING;

ALTER TABLE public.grupo_estudiante
    ADD COLUMN id_carrera integer,
    ADD CONSTRAINT fk_grupo_estudiante_carrera
        FOREIGN KEY (id_carrera)
        REFERENCES public.carrera(id_carrera);

UPDATE public.grupo_estudiante ge
SET id_carrera = g.id_carrera
FROM public.grupo g
WHERE g.id_grupo = ge.id_grupo
  AND ge.id_carrera IS NULL;

CREATE INDEX idx_grupo_estudiante_carrera
    ON public.grupo_estudiante(id_grupo, id_carrera);
SQL);
    }

    public function down()
    {
        DB::unprepared(<<<'SQL'
DROP INDEX IF EXISTS public.idx_grupo_estudiante_carrera;

ALTER TABLE public.grupo_estudiante
    DROP CONSTRAINT IF EXISTS fk_grupo_estudiante_carrera,
    DROP COLUMN IF EXISTS id_carrera;

DROP TABLE IF EXISTS public.grupo_oferta;

ALTER TABLE public.grupo
    DROP CONSTRAINT IF EXISTS unq_grupo_materia_periodo;
SQL);
    }
}
