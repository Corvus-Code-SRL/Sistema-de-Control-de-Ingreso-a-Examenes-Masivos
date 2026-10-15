<?php

namespace App\Services\EntryControl;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IdentificationService
{
    private EntryControlSnapshotService $snapshots;

    public function __construct(EntryControlSnapshotService $snapshots)
    {
        $this->snapshots = $snapshots;
    }

    public function identify(int $examId, array $input): array
    {
        $record = isset($input['id_estudiante'])
            ? $this->snapshots->studentById($examId, (int) $input['id_estudiante'])
            : $this->snapshots->studentBySis($examId, (string) $input['cod_sis']);

        if ($record === null) {
            return ['veredicto' => 'NO_ENCONTRADO', 'registro' => null];
        }

        return ['veredicto' => 'IDENTIFICADO', 'registro' => $record];
    }

    public function assistantBySis(int $examId, string $sis): ?array
    {
        $assistant = DB::table('examen_auxiliar as ea')
            ->join('usuario as u', 'u.id_usuario', '=', 'ea.id_usuario')
            ->where('ea.id_examen', $examId)
            ->where('u.cod_sis', $sis)
            ->where('u.estado', User::ESTADO_ACTIVO)
            ->first(['u.cod_sis', 'u.nombre', 'u.apellido_paterno', 'u.apellido_materno', 'ea.id_ambiente']);

        if ($assistant === null) {
            return null;
        }

        $rooms = DB::table('examen_ambiente as ea')
            ->join('ambiente as a', 'a.id_ambiente', '=', 'ea.id_ambiente')
            ->where('ea.id_examen', $examId)
            ->orderBy('ea.id_ambiente')
            ->get(['ea.id_ambiente', 'a.nro_aula'])
            ->map(fn ($room): array => [
                'id_ambiente' => (int) $room->id_ambiente,
                'nro_aula' => $room->nro_aula,
            ])->all();
        $roomId = EntryControlSnapshotService::controlRoomOf(
            $assistant->id_ambiente === null ? null : (int) $assistant->id_ambiente,
            $rooms
        );
        $room = collect($rooms)->firstWhere('id_ambiente', $roomId);

        return [
            'cod_sis' => $assistant->cod_sis,
            'nombre_completo' => trim(implode(' ', array_filter([
                $assistant->nombre,
                $assistant->apellido_paterno,
                $assistant->apellido_materno,
            ]))),
            'ambiente_asignado' => $room,
        ];
    }

    public function searchByName(int $examId, User $actor, string $name): array
    {
        return $this->snapshots->search($examId, $actor, $name);
    }

    public function searchByNumberPrefix(int $examId, User $actor, string $field, string $prefix): array
    {
        $this->snapshots->assertCanControl($examId, $actor);

        $column = $field === 'sis' ? 'cod_sis' : 'ci';

        return Student::query()
            ->where($column, 'like', $prefix . '%')
            ->whereExists(function ($query) use ($examId): void {
                $query->selectRaw('1')
                    ->from('grupo_estudiante as gs')
                    ->join('grupo_examen as gx', 'gx.id_grupo', '=', 'gs.id_grupo')
                    ->whereColumn('gs.id_estudiante', 'estudiante.id_estudiante')
                    ->where('gx.id_examen', $examId);
            })
            ->orderBy($column)
            ->limit(5)
            ->get($field === 'ci'
                ? ['id_estudiante', 'cod_sis', 'ci', 'nombre', 'apellido_paterno', 'apellido_materno']
                : ['id_estudiante', 'cod_sis', 'nombre', 'apellido_paterno', 'apellido_materno'])
            ->map(function (Student $student) use ($field): array {
                $match = [
                    'id_estudiante' => (int) $student->id_estudiante,
                    'cod_sis' => $student->cod_sis,
                    'nombre_completo' => trim(
                        $student->nombre . ' ' . $student->apellido_paterno . ' ' . $student->apellido_materno
                    ),
                ];

                if ($field === 'ci') {
                    $match['ci'] = $student->ci;
                }

                return $match;
            })
            ->all();
    }
}
