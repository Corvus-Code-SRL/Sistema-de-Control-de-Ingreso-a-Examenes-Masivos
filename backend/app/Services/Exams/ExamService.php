<?php

namespace App\Services\Exams;

use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Classroom;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class ExamService
{
    /**
     * Obtiene el ID del usuario docente actual para desarrollo/pruebas.
     */
    private function obtenerDocenteIdActual(): ?string
    {
        return auth()->id() ?? DB::table('usuario')->where('estado', 'ACTIVO')->value('id_usuario');
    }

    public function crearExamen(array $data): Exam
    {
        // Cálculo de la hora de finalización
        $horaInicio = Carbon::createFromFormat('H:i', $data['hora_inicio']);
        $horaFin = (clone $horaInicio)->addMinutes($data['duracion'])->format('H:i');

        // Verificación de superposición de ambientes en mismo horario y fecha
        $ambienteOcupado = DB::table('examen')
            ->join('examen_ambiente', 'examen.id_examen', '=', 'examen_ambiente.id_examen')
            ->whereIn('examen_ambiente.id_ambiente', $data['ambientes'])
            ->where('examen.fecha', $data['fecha'])
            ->where(function ($query) use ($data, $horaFin) {
                $query->whereBetween('examen.hora_inicio', [$data['hora_inicio'], $horaFin])
                      ->orWhereBetween('examen.hora_fin', [$data['hora_inicio'], $horaFin])
                      ->orWhere(function ($q) use ($data, $horaFin) {
                          $q->where('examen.hora_inicio', '<=', $data['hora_inicio'])
                            ->where('examen.hora_fin', '>=', $horaFin);
                      });
            })
            ->exists();

        if ($ambienteOcupado) {
            throw new Exception('Uno o más ambientes seleccionados ya están reservados en ese horario');
        }

        // Buscar o crear el tipo de examen por categoría (REGULAR, FINAL, MESA, ADMISION)
        $categoria = $data['categoria'] ?? 'REGULAR';
        $tipoExamen = ExamType::where('categoria', $categoria)->first();
        if ($tipoExamen) {
            $idTipoExamen = $tipoExamen->id_tipo_examen;
        } else {
            $tipoExamen = ExamType::create([
                'nombre'    => $data['nombre_examen'],
                'categoria' => $categoria,
            ]);
            $idTipoExamen = $tipoExamen->id_tipo_examen;
        }

        // Registrar el examen y asociar sus ambientes dentro de una transacción
        return DB::transaction(function () use ($data, $horaFin, $idTipoExamen) {
            $examen = Exam::create([
                'nombre_examen'  => $data['nombre_examen'],
                'fecha'          => $data['fecha'],
                'hora_inicio'    => $data['hora_inicio'],
                'hora_fin'       => $horaFin,
                'duracion'       => $data['duracion'],
                'normas'         => $data['normas'] ?? null,
                'id_tipo_examen' => $idTipoExamen,
            ]);

            $examen->classrooms()->attach($data['ambientes']);
            return $examen;
        });
    }

    public function obtenerDatosFormulario()
    {
        $docenteId = $this->obtenerDocenteIdActual();
        if (!$docenteId) {
            throw new Exception('No se pudo obtener el id del usuario docente actual');
        }

        $gruposQuery = DB::table('grupo')->where('estado', 'ACTIVO');
        if ($docenteId) {
            $gruposQuery->where('id_usuario_docente', $docenteId);
        }
        
        $grupos = $gruposQuery->get();

        $materias = DB::table('materia')->where('estado', 'ACTIVO')->get();
        $ambientes = DB::table('ambiente')->where('estado', 'ACTIVO')->get();

        return [
            'materias'  => $materias,
            'ambientes' => $ambientes,
            'grupos'    => $grupos,
        ];
    }
}