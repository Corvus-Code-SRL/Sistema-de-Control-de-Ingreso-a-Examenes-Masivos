<?php

namespace App\Services\Exams\Distribution;

/** Huella determinista de las filas persistidas para detectar guardados concurrentes. */
final class DistributionVersion
{
    /**
     * @param list<array{id_examen:int, id_estudiante:int, id_ambiente:int,
     *     id_usuario_asignador:string, asignado_en:string}> $rows
     */
    public static function fromRows(array $rows): string
    {
        $canonical = [];

        foreach ($rows as $row) {
            $canonical[] = [
                'id_examen' => $row['id_examen'],
                'id_estudiante' => $row['id_estudiante'],
                'id_ambiente' => $row['id_ambiente'],
                'id_usuario_asignador' => $row['id_usuario_asignador'],
                'asignado_en' => $row['asignado_en'],
            ];
        }

        usort($canonical, fn (array $left, array $right): int =>
            ($left['id_examen'] <=> $right['id_examen']) ?: ($left['id_estudiante'] <=> $right['id_estudiante']));

        return sha1(json_encode($canonical, JSON_THROW_ON_ERROR));
    }
}
