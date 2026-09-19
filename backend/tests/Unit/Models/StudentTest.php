<?php

namespace Tests\Unit\Models;

use App\Models\Group;
use App\Models\Student;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class StudentTest extends TestCase
{
    private ?ConnectionResolverInterface $previousResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousResolver = Model::getConnectionResolver();

        $resolver = new ConnectionResolver([
            'unit' => new Connection(null),
        ]);

        $resolver->setDefaultConnection('unit');

        Model::setConnectionResolver($resolver);
    }

    protected function tearDown(): void
    {
        if ($this->previousResolver === null) {
            Model::unsetConnectionResolver();
        } else {
            Model::setConnectionResolver($this->previousResolver);
        }

        parent::tearDown();
    }

    public function testStudentUsesOfficialTableAndPrimaryKey(): void
    {
        $student = new Student();

        $this->assertSame('estudiante', $student->getTable());
        $this->assertSame('id_estudiante', $student->getKeyName());
        $this->assertTrue($student->getIncrementing());
        $this->assertFalse($student->usesTimestamps());
    }

    public function testStudentDefinesExpectedFillableColumns(): void
    {
        $student = new Student();

        $this->assertSame([
            'cod_sis',
            'ci',
            'nombre',
            'apellido_paterno',
            'apellido_materno',
            'correo_institucional',
            'telefono',
            'estado',
        ], $student->getFillable());
    }

    public function testStudentPreservesIdentifiersAsStrings(): void
    {
        $student = new Student([
            'cod_sis' => '00123456',
            'ci' => '001234567',
        ]);

        $this->assertSame('00123456', $student->cod_sis);
        $this->assertSame('001234567', $student->ci);
    }

    public function testStudentGroupsRelationUsesOfficialPivot(): void
    {
        $relation = (new Student())->groups();

        $this->assertInstanceOf(Group::class, $relation->getRelated());

        $this->assertSame(
            'grupo_estudiante',
            $relation->getTable()
        );

        $this->assertSame(
            'id_estudiante',
            $relation->getForeignPivotKeyName()
        );

        $this->assertSame(
            'id_grupo',
            $relation->getRelatedPivotKeyName()
        );

        $this->assertSame(
            ['fecha_inscripcion', 'estado'],
            $relation->getPivotColumns()
        );
    }

    public function testGroupStudentsRelationUsesOfficialPivot(): void
    {
        $relation = (new Group())->students();

        $this->assertInstanceOf(Student::class, $relation->getRelated());

        $this->assertSame(
            'grupo_estudiante',
            $relation->getTable()
        );

        $this->assertSame(
            'id_grupo',
            $relation->getForeignPivotKeyName()
        );

        $this->assertSame(
            'id_estudiante',
            $relation->getRelatedPivotKeyName()
        );

        $this->assertSame(
            ['fecha_inscripcion', 'estado'],
            $relation->getPivotColumns()
        );
    }
}