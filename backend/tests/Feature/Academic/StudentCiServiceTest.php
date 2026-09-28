<?php

namespace Tests\Feature\Academic;

use App\Models\Student;
use App\Services\Academic\StudentCiService;
use App\Support\RecordStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Punto de extensión de EX04: captura el CI de un estudiante de nómina que nació sin él.
 */
class StudentCiServiceTest extends TestCase
{
    use DatabaseTransactions;

    private function student(string $sisCode, ?string $ci = null): Student
    {
        return Student::create([
            'cod_sis' => $sisCode,
            'ci' => $ci,
            'nombre' => 'ANA',
            'apellido_paterno' => 'PEREZ ROJAS',
            'estado' => RecordStatus::ACTIVE,
        ]);
    }

    private function service(): StudentCiService
    {
        return new StudentCiService();
    }

    public function test_guarda_el_ci_de_un_estudiante_que_no_lo_tenia(): void
    {
        $student = $this->student('202400001');

        $result = $this->service()->captureCi($student, ' 1234567 ');

        $this->assertSame('1234567', $result->ci);
        $this->assertDatabaseHas('estudiante', [
            'id_estudiante' => $student->id_estudiante,
            'ci' => '1234567',
        ]);
    }

    public function test_repetir_el_mismo_ci_no_cambia_nada(): void
    {
        $student = $this->student('202400001', '1234567');

        $this->assertSame('1234567', $this->service()->captureCi($student, '1234567')->ci);
    }

    public function test_no_sobrescribe_un_ci_distinto(): void
    {
        $student = $this->student('202400001', '1234567');

        try {
            $this->service()->captureCi($student, '7654321');
            $this->fail('Debía rechazar un CI distinto.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ci', $exception->errors());
        }

        $this->assertDatabaseHas('estudiante', [
            'id_estudiante' => $student->id_estudiante,
            'ci' => '1234567',
        ]);
    }

    public function test_rechaza_un_ci_que_ya_pertenece_a_otro_estudiante(): void
    {
        $this->student('202400001', '1234567');
        $other = $this->student('202400002');

        try {
            $this->service()->captureCi($other, '1234567');
            $this->fail('Debía rechazar un CI repetido.');
        } catch (ValidationException $exception) {
            $this->assertSame(['Ese CI ya pertenece a otro estudiante.'], $exception->errors()['ci']);
        }

        $this->assertNull($other->ci);
    }

    /**
     * @dataProvider invalidCis
     */
    public function test_rechaza_un_ci_vacio_o_demasiado_largo(string $ci): void
    {
        $student = $this->student('202400001');

        $this->expectException(ValidationException::class);

        $this->service()->captureCi($student, $ci);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function invalidCis(): array
    {
        return [
            'vacío' => [''],
            'solo espacios' => ['   '],
            'once caracteres' => ['12345678901'],
        ];
    }
}
