<?php

namespace App\Services\Academic\Importers;

use App\Models\Student;
use App\Support\RecordStatus;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Incorpora al grupo las filas confirmadas, en una sola transacción y por lotes.
 *
 * Una confirmación cuesta un número fijo de consultas, no una por estudiante: el grupo se
 * bloquea, se clasifican las filas, se insertan los estudiantes nuevos y se inscribe a todos.
 * Ambas inserciones usan ON CONFLICT DO NOTHING, así que repetir una carga o dos cargas
 * simultáneas del mismo estudiante no fallan ni duplican.
 */
class StudentRosterConfirmer
{
    private const CHUNK_SIZE = 1000;

    private StudentRosterDatabaseMatcher $matcher;

    private StudentRosterStudentCreator $studentCreator;

    public function __construct(
        StudentRosterDatabaseMatcher $matcher,
        StudentRosterStudentCreator $studentCreator
    ) {
        $this->matcher = $matcher;
        $this->studentCreator = $studentCreator;
    }

    public function confirm(
        int $groupId,
        StudentRosterAnalysisResult $analysis
    ): StudentRosterConfirmationResult {
        $enrollmentDate = now()->toDateString();

        return DB::transaction(function () use (
            $groupId,
            $analysis,
            $enrollmentDate
        ): StudentRosterConfirmationResult {
            // Serializa las confirmaciones del mismo grupo: la segunda ve lo que dejó la primera.
            DB::table('grupo')
                ->where('id_grupo', $groupId)
                ->lockForUpdate()
                ->value('id_grupo');

            $newRows = [];
            $studentIds = [];
            $alreadyEnrolled = 0;

            foreach ($this->matcher->classify($groupId, $analysis) as $match) {
                switch ($match->status()) {
                    case StudentRosterDatabaseMatch::NEW_STUDENT:
                        $newRows[] = $match->rowAnalysis()->row();
                        break;

                    case StudentRosterDatabaseMatch::EXISTING_STUDENT:
                        if ($match->studentId() === null) {
                            throw new LogicException(
                                'El estudiante existente no tiene identificador.'
                            );
                        }

                        $studentIds[] = $match->studentId();
                        break;

                    case StudentRosterDatabaseMatch::ALREADY_ENROLLED:
                        $alreadyEnrolled++;
                        break;

                    default:
                        throw new LogicException(
                            'Estado de clasificación de nómina no soportado.'
                        );
                }
            }

            $createdStudents = $this->studentCreator->createMany($newRows);

            // Los ids se leen por cod_sis: incluye también a quien otro docente creó entre tanto.
            $studentIds = array_merge($studentIds, $this->idsOf($newRows));

            $enrolledStudents = $this->enrollStudents(
                $groupId,
                array_values(array_unique($studentIds)),
                $enrollmentDate
            );

            // Un estudiante que otra confirmación inscribió antes cuenta como ya inscrito.
            $alreadyEnrolled += count(array_unique($studentIds)) - $enrolledStudents;

            return new StudentRosterConfirmationResult(
                $analysis->totalRows(),
                $analysis->inconsistentRows(),
                $createdStudents,
                $enrolledStudents,
                $alreadyEnrolled
            );
        });
    }

    /**
     * @param array<int, StudentRosterRow> $rows
     *
     * @return array<int, int>
     */
    private function idsOf(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $sisCodes = array_map(
            static function (StudentRosterRow $row): ?string {
                return $row->sisCode();
            },
            $rows
        );

        return Student::query()
            ->whereIn('cod_sis', $sisCodes)
            ->pluck('id_estudiante')
            ->map(static function ($id): int {
                return (int) $id;
            })
            ->all();
    }

    /**
     * @param array<int, int> $studentIds
     */
    private function enrollStudents(
        int $groupId,
        array $studentIds,
        string $enrollmentDate
    ): int {
        $enrolled = 0;

        foreach (array_chunk($studentIds, self::CHUNK_SIZE) as $chunk) {
            $bindings = [];

            foreach ($chunk as $studentId) {
                array_push($bindings, $groupId, $studentId, $enrollmentDate, RecordStatus::ACTIVE);
            }

            $enrolled += DB::affectingStatement(
                'insert into grupo_estudiante (id_grupo, id_estudiante, fecha_inscripcion, estado) values '
                . implode(', ', array_fill(0, count($chunk), '(?, ?, ?, ?)'))
                . ' on conflict (id_grupo, id_estudiante) do nothing',
                $bindings
            );
        }

        return $enrolled;
    }
}
