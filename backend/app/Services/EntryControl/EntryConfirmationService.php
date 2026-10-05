<?php

namespace App\Services\EntryControl;

use App\Models\Exam;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EntryConfirmationService
{
    private VerificationService $verification;
    private EntryControlSnapshotService $snapshots;

    public function __construct(
        VerificationService $verification,
        EntryControlSnapshotService $snapshots
    ) {
        $this->verification = $verification;
        $this->snapshots = $snapshots;
    }

    public function confirm(Exam $exam, array $input, User $actor): array
    {
        try {
            $result = DB::transaction(function () use ($exam, $input, $actor): array {
                $student = Student::query()
                    ->whereKey((int) $input['id_estudiante'])
                    ->lockForUpdate()
                    ->first();

                if ($student === null) {
                    return [
                        'creado' => false,
                        'veredicto' => 'NO_ENCONTRADO',
                        'motivo' => 'No se encontró al estudiante.',
                    ];
                }

                $verdict = $this->verification->verify(
                    (int) $exam->id_examen,
                    $input,
                    $actor
                );

                if (! $verdict['autorizado']) {
                    return [
                        'creado' => false,
                        'veredicto' => $verdict['veredicto'],
                        'motivo' => $verdict['motivo'],
                        'ingreso_previo' => $verdict['ingreso_previo'],
                    ];
                }

                // PostgreSQL conserva la última palabra ante un snapshot atrasado o
                // dos confirmaciones concurrentes.
                $existing = DB::table('examen_estudiante')
                    ->where('id_examen', $exam->id_examen)
                    ->where('id_estudiante', $student->id_estudiante)
                    ->first(['estado_ingreso']);

                if ($existing !== null && in_array(
                    $existing->estado_ingreso,
                    ['INGRESO', 'CON_RETRASO'],
                    true
                )) {
                    return [
                        'creado' => false,
                        'veredicto' => 'DUPLICADO',
                        'motivo' => 'El estudiante ya registró su ingreso.',
                        'ingreso_previo' => null,
                    ];
                }

                $instant = Carbon::now('UTC');
                $local = $instant->copy()->setTimezone(config('sciem.zona_horaria'));
                $attributes = [
                    'id_grupo' => $verdict['grupo']['id_grupo'],
                    'estado_habilitacion' => 'HABILITADO',
                    'estado_ingreso' => 'INGRESO',
                    'hora_ingreso' => $local->format('H:i:s'),
                    'id_ambiente' => (int) $input['id_ambiente'],
                    'id_usuario_controlador' => $actor->id_usuario,
                    'registrado_en' => $instant->format('Y-m-d H:i:sP'),
                ];

                if ($existing === null) {
                    DB::table('examen_estudiante')->insert($attributes + [
                        'id_examen' => $exam->id_examen,
                        'id_estudiante' => $student->id_estudiante,
                    ]);
                } else {
                    DB::table('examen_estudiante')
                        ->where('id_examen', $exam->id_examen)
                        ->where('id_estudiante', $student->id_estudiante)
                        ->where('estado_ingreso', 'NO_INGRESO')
                        ->update($attributes);
                }

                return [
                    'creado' => true,
                    'veredicto' => 'INGRESO_REGISTRADO',
                    'id_estudiante' => (int) $student->id_estudiante,
                    'id_ambiente' => (int) $input['id_ambiente'],
                    'id_usuario_controlador' => $actor->id_usuario,
                    'registrado_en' => $instant->toIso8601String(),
                    'hora_ingreso' => $local->format('H:i:s'),
                ];
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23505') {
                throw $exception;
            }

            return ['creado' => false, 'veredicto' => 'DUPLICADO', 'motivo' => 'El estudiante ya registró su ingreso.'];
        }

        if ($result['creado']) {
            try {
                $this->snapshots->markEntered(
                    (int) $exam->id_examen,
                    $result['id_estudiante'],
                    $result['id_ambiente'],
                    $actor,
                    $result['registrado_en'],
                    $result['hora_ingreso']
                );
            } catch (\Throwable $exception) {
                // El ingreso ya quedó confirmado en PostgreSQL. El job reconstruirá
                // el snapshot si Redis perdió el estado operativo.
                Log::error('Ingreso persistido, pero no se pudo actualizar Redis', [
                    'id_examen' => $exam->id_examen,
                    'id_estudiante' => $result['id_estudiante'],
                    'motivo' => $exception->getMessage(),
                ]);
                try {
                    $this->snapshots->deactivate((int) $exam->id_examen);
                } catch (\Throwable $deactivationError) {
                    Log::error('Tampoco se pudo invalidar el snapshot Redis', [
                        'id_examen' => $exam->id_examen,
                        'motivo' => $deactivationError->getMessage(),
                    ]);
                }
            }
        }

        return $result;
    }
}
