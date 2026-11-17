<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * HU-023: el estudiante excepcional vive en la lista de UN examen (examen_estudiante), no en el padrón.
 *
 * fk_examen_estudiante_grupo_estudiante se conserva: una clave compuesta con un componente NULL no se
 * verifica, así que la fila excepcional lleva id_grupo = NULL y su grupo en id_grupo_excepcional. El CHECK
 * obliga a que toda fila tenga una inscripción en un grupo (id_grupo) o un registro excepcional completo,
 * nunca ninguno ni ambos. La unicidad por examen y estudiante la sigue dando pk_examen_estudiante.
 *
 * id_grupo_efectivo (columna generada) es el grupo con el que se cuenta al estudiante en el examen.
 */
class AddExcepcionalToExamenEstudiante extends Migration
{
    public function up()
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE public.examen_estudiante
    ALTER COLUMN id_grupo DROP NOT NULL,
    ADD COLUMN es_excepcional boolean NOT NULL DEFAULT false,
    ADD COLUMN motivo_excepcional varchar(255),
    ADD COLUMN id_usuario_docente_excepcional uuid,
    ADD COLUMN id_grupo_excepcional integer,
    ADD COLUMN id_carrera_excepcional integer;

ALTER TABLE public.examen_estudiante
    ADD COLUMN id_grupo_efectivo integer
        GENERATED ALWAYS AS (COALESCE(id_grupo, id_grupo_excepcional)) STORED;

ALTER TABLE public.examen_estudiante
    ADD CONSTRAINT fk_examen_estudiante_docente_excepcional
        FOREIGN KEY (id_usuario_docente_excepcional) REFERENCES public.usuario(id_usuario),
    ADD CONSTRAINT fk_examen_estudiante_grupo_examen_excepcional
        FOREIGN KEY (id_grupo_excepcional, id_examen) REFERENCES public.grupo_examen(id_grupo, id_examen),
    ADD CONSTRAINT fk_examen_estudiante_grupo_oferta_excepcional
        FOREIGN KEY (id_grupo_excepcional, id_carrera_excepcional)
        REFERENCES public.grupo_oferta(id_grupo, id_carrera),
    ADD CONSTRAINT chk_examen_estudiante_excepcional
    CHECK (
        (
            es_excepcional = false
            AND id_grupo IS NOT NULL
            AND id_grupo_excepcional IS NULL
            AND id_carrera_excepcional IS NULL
            AND id_usuario_docente_excepcional IS NULL
            AND motivo_excepcional IS NULL
        )
        OR
        (
            es_excepcional = true
            AND id_grupo IS NULL
            AND id_grupo_excepcional IS NOT NULL
            AND id_carrera_excepcional IS NOT NULL
            AND id_usuario_docente_excepcional IS NOT NULL
            AND motivo_excepcional IS NOT NULL
            AND length(btrim(motivo_excepcional)) > 0
        )
    );

CREATE INDEX idx_examen_estudiante_excepcional
    ON public.examen_estudiante(id_examen) WHERE es_excepcional;
CREATE INDEX idx_examen_estudiante_grupo_efectivo
    ON public.examen_estudiante(id_grupo_efectivo);
SQL);
    }

    public function down()
    {
        // No se destruye información de excepcionales durante una reversión.
        if (DB::table('examen_estudiante')->where('es_excepcional', true)->exists()) {
            throw new RuntimeException(
                'HU-023: no se puede revertir: existen estudiantes excepcionales. '
                . 'Conserve los datos y realice una migración correctiva.'
            );
        }

        DB::unprepared(<<<'SQL'
DROP INDEX public.idx_examen_estudiante_grupo_efectivo;
DROP INDEX public.idx_examen_estudiante_excepcional;

ALTER TABLE public.examen_estudiante
    DROP CONSTRAINT chk_examen_estudiante_excepcional,
    DROP CONSTRAINT fk_examen_estudiante_grupo_oferta_excepcional,
    DROP CONSTRAINT fk_examen_estudiante_grupo_examen_excepcional,
    DROP CONSTRAINT fk_examen_estudiante_docente_excepcional,
    DROP COLUMN id_grupo_efectivo,
    DROP COLUMN id_carrera_excepcional,
    DROP COLUMN id_grupo_excepcional,
    DROP COLUMN id_usuario_docente_excepcional,
    DROP COLUMN motivo_excepcional,
    DROP COLUMN es_excepcional,
    ALTER COLUMN id_grupo SET NOT NULL;
SQL);
    }
}
