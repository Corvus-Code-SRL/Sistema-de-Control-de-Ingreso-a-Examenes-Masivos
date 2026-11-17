<?php

namespace App\Http\Resources\Exams;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Invitación enviada por el docente (HU-026).
 *
 * `estado` es el que ve el docente: una invitación PENDIENTE cuyo examen ya no está PROGRAMADO se muestra
 * SIN_EFECTO aunque el ciclo de vida aún no la haya escrito (ver ExamInvitation::effectiveState()).
 */
class SentInvitationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id_invitacion' => $this->id_invitacion,
            'estado' => $this->effectiveState(),
            'fecha_invitacion' => $this->fecha_invitacion->toIso8601String(),
            'fecha_respuesta' => $this->fecha_respuesta ? $this->fecha_respuesta->toIso8601String() : null,
            'fecha_sin_efecto' => $this->fecha_sin_efecto ? $this->fecha_sin_efecto->toIso8601String() : null,
            'permite_agregar_grupos' => (bool) $this->permite_agregar_grupos,
            'lista_por_grupo' => (bool) $this->lista_por_grupo,
            'examen' => $this->whenLoaded('exam', fn () => [
                'id_examen' => $this->exam->id_examen,
                'nombre_examen' => $this->exam->nombre_examen,
                'materia' => $this->exam->subject === null ? null : $this->exam->subject->nombre,
                'carrera' => $this->exam->career === null ? null : $this->exam->career->nombre,
                'fecha' => $this->exam->fecha ? $this->exam->fecha->format('Y-m-d') : null,
                'hora_inicio' => $this->exam->hora_inicio === null ? null : substr($this->exam->hora_inicio, 0, 5),
                'hora_fin' => $this->exam->hora_fin === null ? null : substr($this->exam->hora_fin, 0, 5),
                'estado' => $this->exam->estado,
            ]),
            'docente' => $this->whenLoaded('userInvited', fn () => [
                'id_usuario' => (string) $this->userInvited->id_usuario,
                'nombre_completo' => $this->userInvited->nombre_completo,
                'cod_sis' => $this->userInvited->cod_sis,
            ]),
        ];
    }
}
