<?php

namespace Tests\Unit\Exams;

use App\Services\Exams\Distribution\SurnameDistributor;
use PHPUnit\Framework\TestCase;

class SurnameDistributorTest extends TestCase
{
    /**
     * @dataProvider capacityCases
     * @param list<int> $capacities
     * @param list<int> $expectedCounts
     */
    public function test_reparte_bloques_consecutivos_sin_exceder_capacidad(
        int $studentCount,
        array $capacities,
        array $expectedCounts,
        int $expectedUnassigned
    ): void {
        $students = $this->students($studentCount);
        $rooms = [];

        foreach ($capacities as $index => $capacity) {
            $rooms[] = ['id_ambiente' => $index + 1, 'capacidad' => $capacity];
        }

        $result = (new SurnameDistributor())->distribute(array_reverse($students), array_reverse($rooms));
        $expectedAssignments = [];
        $studentId = 1;

        foreach ($expectedCounts as $index => $count) {
            $this->assertLessThanOrEqual($capacities[$index], $count);

            for ($offset = 0; $offset < $count; $offset++) {
                $expectedAssignments[$studentId++] = $index + 1;
            }
        }

        $this->assertSame($expectedAssignments, $result['assignments']);
        $this->assertSame(
            array_column(array_slice($students, $studentCount - $expectedUnassigned), 'id_estudiante'),
            $result['unassigned']
        );
        $this->assertCount($studentCount, array_merge(array_keys($result['assignments']), $result['unassigned']));
    }

    /** @return array<string, array{0:int, 1:list<int>, 2:list<int>, 3:int}> */
    public function capacityCases(): array
    {
        return [
            '55 en dos ambientes iguales' => [55, [30, 30], [28, 27], 0],
            'capacidades desiguales' => [60, [40, 20, 20], [30, 15, 15], 0],
            'capacidad exacta' => [60, [30, 30], [30, 30], 0],
            'capacidad insuficiente' => [63, [30, 30], [30, 30], 3],
            'un ambiente' => [12, [30], [12], 0],
            'un ambiente insuficiente' => [32, [30], [30], 2],
            'sin habilitados' => [0, [30, 30], [0, 0], 0],
            'sin ambientes' => [3, [], [], 3],
            'capacidad remanente nula' => [3, [0, 0], [0, 0], 3],
            'un remanente nulo' => [3, [0, 4], [0, 3], 0],
            'mayor resto gana al primer ambiente' => [4, [2, 3], [2, 2], 0],
            'desempate por orden del ambiente' => [2, [30, 30, 30], [1, 1, 0], 0],
            'restos diferentes' => [7, [5, 3, 2], [4, 2, 1], 0],
        ];
    }

    public function test_ordena_por_paterno_materno_nombres_y_sis_con_ceros_iniciales(): void
    {
        $students = [
            $this->student(8, 'Ñandú'),
            $this->student(2, 'Álvarez', 'Zapata'),
            $this->student(5, 'álvarez', 'Ávila', 'Ana', '000000002'),
            $this->student(1, 'Alvarez', null),
            $this->student(6, 'Navarro'),
            $this->student(7, 'Nzz'),
            $this->student(4, 'ALVAREZ', 'Avila', 'Ána', '000000001'),
            $this->student(3, 'Alvarez', 'Avila', 'Adela'),
            $this->student(9, 'Ochoa'),
            $this->student(10, 'Alvarez', 'Avila', 'Ana', '2'),
        ];
        $distributor = new SurnameDistributor();

        $this->assertSame(
            [1, 3, 4, 5, 10, 2, 6, 7, 8, 9],
            array_column($distributor->orderedStudents($students), 'id_estudiante')
        );
        $this->assertSame(
            [1 => 20, 3 => 20, 4 => 20, 5 => 20, 10 => 20, 2 => 40, 6 => 40, 7 => 40, 8 => 40, 9 => 40],
            $distributor->distribute($students, [
                ['id_ambiente' => 40, 'capacidad' => 30],
                ['id_ambiente' => 20, 'capacidad' => 30],
            ])['assignments']
        );
    }

    public function test_no_modifica_los_datos_recibidos(): void
    {
        $students = array_reverse($this->students(4));
        $rooms = [['id_ambiente' => 4, 'capacidad' => 3], ['id_ambiente' => 2, 'capacidad' => 3]];
        $originalStudents = $students;
        $originalRooms = $rooms;

        (new SurnameDistributor())->distribute($students, $rooms);

        $this->assertSame($originalStudents, $students);
        $this->assertSame($originalRooms, $rooms);
    }

    /** @return list<array{id_estudiante:int, apellido_paterno:string, apellido_materno:?string,
     *     nombre:string, cod_sis:string}> */
    private function students(int $count): array
    {
        $students = [];

        for ($id = 1; $id <= $count; $id++) {
            $students[] = $this->student($id, 'Apellido' . str_pad((string) $id, 4, '0', STR_PAD_LEFT));
        }

        return $students;
    }

    /** @return array{id_estudiante:int, apellido_paterno:string, apellido_materno:?string,
     *     nombre:string, cod_sis:string} */
    private function student(
        int $id,
        string $lastName,
        ?string $secondLastName = null,
        string $firstName = 'Ana',
        ?string $sisCode = null
    ): array {
        return [
            'id_estudiante' => $id,
            'apellido_paterno' => $lastName,
            'apellido_materno' => $secondLastName,
            'nombre' => $firstName,
            'cod_sis' => $sisCode ?? (string) $id,
        ];
    }
}
