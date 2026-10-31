<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * La oferta académica: qué pares materia-carrera se dictan en cada período (gestion_periodo).
 *
 * El catálogo (materia, materia_carrera) es permanente; una materia que no se ofrece en un período
 * simplemente no tiene fila de oferta ahí y puede ofrecerse de nuevo después. Un grupo solo puede
 * existir para un par ofertado en su período: grupo apunta con una clave compuesta a la oferta.
 *
 * Relleno de las bases que ya tienen datos:
 *   1. Un par ACTIVO de materia_carrera → una oferta ACTIVA en el período activo (si no hay ninguno
 *      activo este paso se omite y se deja un aviso).
 *   2. Cada par que ya usa un grupo → una oferta en el período de ese grupo, con el estado del par.
 *      Sin ella la clave compuesta nueva de grupo fallaría.
 *
 * grupo.gestion se elimina (el año vive en gestion) y unq_grupo pasa a (carrera, materia, número,
 * período). fk_grupo_materia_carrera se elimina porque la clave compuesta a oferta_academica la implica.
 */
class CreateOfertaAcademica extends Migration
{
    public function up()
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE public.oferta_academica (
    id_carrera          integer NOT NULL,
    id_materia          integer NOT NULL,
    id_gestion_periodo  integer NOT NULL,
    estado              public.estado_registro DEFAULT 'ACTIVO' NOT NULL,
    fecha_registro      timestamp(0) with time zone DEFAULT now() NOT NULL,

    CONSTRAINT pk_oferta_academica
        PRIMARY KEY (id_carrera, id_materia, id_gestion_periodo),

    CONSTRAINT fk_oferta_academica_materia_carrera
        FOREIGN KEY (id_carrera, id_materia)
        REFERENCES public.materia_carrera(id_carrera, id_materia),

    CONSTRAINT fk_oferta_academica_gestion_periodo
        FOREIGN KEY (id_gestion_periodo)
        REFERENCES public.gestion_periodo(id_gestion_periodo)
);

CREATE INDEX idx_oferta_academica_gestion_periodo
    ON public.oferta_academica(id_gestion_periodo);

CREATE INDEX idx_oferta_academica_materia_carrera
    ON public.oferta_academica(id_carrera, id_materia);

INSERT INTO public.oferta_academica (id_carrera, id_materia, id_gestion_periodo, estado)
SELECT mc.id_carrera, mc.id_materia, gp.id_gestion_periodo, 'ACTIVO'
FROM public.materia_carrera mc
CROSS JOIN public.gestion_periodo gp
WHERE mc.estado = 'ACTIVO'
  AND gp.activo
ON CONFLICT DO NOTHING;

INSERT INTO public.oferta_academica (id_carrera, id_materia, id_gestion_periodo, estado)
SELECT DISTINCT g.id_carrera, g.id_materia, g.id_gestion_periodo, mc.estado
FROM public.grupo g
JOIN public.materia_carrera mc
    ON mc.id_carrera = g.id_carrera AND mc.id_materia = g.id_materia
ON CONFLICT DO NOTHING;

ALTER TABLE public.grupo
    DROP CONSTRAINT unq_grupo,
    DROP CONSTRAINT fk_grupo_materia_carrera,
    DROP COLUMN gestion;

ALTER TABLE public.grupo
    ADD CONSTRAINT unq_grupo
        UNIQUE (id_carrera, id_materia, num_grupo, id_gestion_periodo),
    ADD CONSTRAINT fk_grupo_oferta_academica
        FOREIGN KEY (id_carrera, id_materia, id_gestion_periodo)
        REFERENCES public.oferta_academica(id_carrera, id_materia, id_gestion_periodo);
SQL);

        if (! DB::table('gestion_periodo')->where('activo', true)->exists()) {
            Log::warning(
                'oferta_academica: no hay un período activo; solo se ofertaron los pares que ya tenían grupos.'
            );
        }
    }

    public function down()
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE public.grupo
    DROP CONSTRAINT fk_grupo_oferta_academica,
    DROP CONSTRAINT unq_grupo,
    ADD COLUMN gestion varchar(10);

UPDATE public.grupo g
SET gestion = ge.anio::text
FROM public.gestion_periodo gp
JOIN public.gestion ge ON ge.id_gestion = gp.id_gestion
WHERE gp.id_gestion_periodo = g.id_gestion_periodo;

ALTER TABLE public.grupo
    ALTER COLUMN gestion SET NOT NULL,
    ADD CONSTRAINT fk_grupo_materia_carrera
        FOREIGN KEY (id_carrera, id_materia)
        REFERENCES public.materia_carrera(id_carrera, id_materia),
    ADD CONSTRAINT unq_grupo
        UNIQUE (id_carrera, id_materia, num_grupo, gestion, id_gestion_periodo);

CREATE INDEX idx_grupo_gestion ON public.grupo(gestion);

DROP TABLE public.oferta_academica;
SQL);
    }
}
