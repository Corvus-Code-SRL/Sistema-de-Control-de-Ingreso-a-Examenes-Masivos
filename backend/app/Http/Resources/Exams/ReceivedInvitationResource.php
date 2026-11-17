<?php

namespace App\Http\Resources\Exams;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Invitación recibida por el docente invitado (HU-027).
 *
 * `estado` es el que se muestra: una PENDIENTE cuyo examen ya no está PROGRAMADO sale SIN_EFECTO (ver
 * ExamInvitation::effectiveState()). Las fechas van en UTC con desfase (ISO 8601); la fecha y las horas del
 * examen son locales. El detalle (ambientes, grupos del examen y grupos propios) solo viene cuando el servicio
 * lo cargó: la bandeja no lo trae.
 */
class ReceivedInvitationResource extends JsonResource
{
    public function toArray($request): array
    {
        $exam = $this->exam;

        $summary = [
            'id_invitacion' => $this->id_invitacion,
            'estado' => $this->effectiveState(),
            'fecha_invitacion' => $this->fecha_invitacion->toIso8601String(),
            'fecha_respuesta' => $this->fecha_respuesta ? $this->fecha_respuesta->toIso8601String() : null,
            'fecha_sin_efecto' => $this->fecha_sin_efecto ? $this->fecha_sin_efecto->toIso8601String() : null,
            'permite_agregar_grupos' => (bool) $this->permite_agregar_grupos,
            'lista_por_grupo' => (bool) $this->lista_por_grupo,
            'examen' => [
                'id_examen' => $exam->id_examen,
                'nombre_examen' => $exam->nombre_examen,
                'materia' => $exam->subject === null ? null : $exam->subject->nombre,
                'carrera' => $exam->career === null ? null : $exam->career->nombre,
                'periodo' => $this->getAttribute('periodo'),
                'fecha' => $exam->fecha ? $exam->fecha->format('Y-m-d') : null,
                'hora_inicio' => $exam->hora_inicio === null ? null : substr($exam->hora_inicio, 0, 5),
                'hora_fin' => $exam->hora_fin === null ? null : substr($exam->hora_fin, 0, 5),
            ],
            'invitante' => [
                'nombre_completo' => $this->userCreator === null ? null : $this->userCreator->nombre_completo,
            ],
        ];

        if ($this->getAttribute('mis_grupos') === null) {
            return $summary;
        }

        return array_merge($summary, [
            'ambientes' => $exam->classrooms
                ->sortBy('id_ambiente')
                ->map(fn ($room) => [
                    'id_ambiente' => (int) $room->id_ambiente,
                    'nro_aula' => (string) $room->nro_aula,
                    'capacidad' => (int) $room->capacidad,
                ])
                ->values()
                ->all(),
            'grupos_examen' => $exam->groups
                ->map(fn ($group) => [
                    'id_grupo' => (int) $group->id_grupo,
                    'num_grupo' => (string) $group->num_grupo,
                    'inscritos' => (int) $group->cantidad_estudiantes,
                ])
                ->values()
                ->all(),
            'mis_grupos' => $this->getAttribute('mis_grupos'),
        ]);
    }
}
