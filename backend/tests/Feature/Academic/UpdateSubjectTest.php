<?php

namespace Tests\Feature\Academic;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class UpdateSubjectTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    private User $adminUser;
    private User $teacherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(ActionSeeder::class);

        $adminRole = Role::where(
            'nombre_rol',
            Role::ADMINISTRADOR
        )->firstOrFail();

        $teacherRole = Role::where(
            'nombre_rol',
            Role::DOCENTE
        )->firstOrFail();

        $this->adminUser = User::factory()->create();
        $this->adminUser->roles()->attach(
            $adminRole->id_rol,
            ['fecha_inicio' => now()]
        );

        $this->teacherUser = User::factory()->create();
        $this->teacherUser->roles()->attach(
            $teacherRole->id_rol,
            ['fecha_inicio' => now()]
        );
    }

    private function createSubject(array $overrides = []): Subject
    {
        return Subject::create(array_merge([
            'nombre'      => 'Calculo I',
            'codigo'      => '2008057',
            'descripcion' => 'Descripcion original',
            'estado'      => Subject::ESTADO_ACTIVO,
        ], $overrides));
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Calculo II',
            'codigo' => '2008058',
        ], $overrides);
    }

    private function updateUrl(Subject $subject): string
    {
        return "/api/materias/{$subject->id_materia}";
    }

    /** @test */
    public function actualiza_nombre_y_codigo_siendo_administrador()
    {
        $subject = $this->createSubject();

        $this->actingAs($this->adminUser)
            ->putJson(
                $this->updateUrl($subject),
                $this->validData()
            )
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Calculo II')
            ->assertJsonPath('data.codigo', '2008058')
            ->assertJsonPath(
                'mensaje',
                'Materia actualizada correctamente.'
            );

        $this->assertDatabaseHas('materia', [
            'id_materia' => $subject->id_materia,
            'nombre'     => 'Calculo II',
            'codigo'     => '2008058',
        ]);
    }

    /** @test */
    public function rechaza_un_nombre_vacio()
    {
        $subject = $this->createSubject();

        $this->actingAs($this->adminUser)
            ->putJson(
                $this->updateUrl($subject),
                $this->validData(['nombre' => ''])
            )
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre');
    }

    /** @test */
    public function rechaza_un_codigo_vacio()
    {
        $subject = $this->createSubject();

        $this->actingAs($this->adminUser)
            ->putJson(
                $this->updateUrl($subject),
                $this->validData(['codigo' => ''])
            )
            ->assertStatus(422)
            ->assertJsonValidationErrors('codigo');
    }

    /** @test */
    public function rechaza_un_codigo_con_formato_invalido()
    {
        $subject = $this->createSubject();

        $this->actingAs($this->adminUser)
            ->putJson(
                $this->updateUrl($subject),
                $this->validData(['codigo' => '200805'])
            )
            ->assertStatus(422)
            ->assertJsonValidationErrors('codigo');
    }

    /** @test */
    public function rechaza_un_codigo_duplicado_contra_materia_activa()
    {
        $subject = $this->createSubject();

        Subject::create([
            'nombre' => 'Otra materia activa',
            'codigo' => '2008058',
            'estado' => Subject::ESTADO_ACTIVO,
        ]);

        $this->actingAs($this->adminUser)
            ->putJson(
                $this->updateUrl($subject),
                $this->validData()
            )
            ->assertStatus(422)
            ->assertJsonValidationErrors('codigo');

        $this->assertDatabaseHas('materia', [
            'id_materia' => $subject->id_materia,
            'codigo'     => '2008057',
        ]);
    }

    /** @test */
    public function rechaza_un_codigo_duplicado_contra_materia_inactiva()
    {
        $subject = $this->createSubject();

        Subject::create([
            'nombre' => 'Materia archivada',
            'codigo' => '2008058',
            'estado' => Subject::ESTADO_INACTIVO,
        ]);

        $this->actingAs($this->adminUser)
            ->putJson(
                $this->updateUrl($subject),
                $this->validData()
            )
            ->assertStatus(422)
            ->assertJsonValidationErrors('codigo');

        $this->assertDatabaseHas('materia', [
            'id_materia' => $subject->id_materia,
            'codigo'     => '2008057',
        ]);
    }

    /** @test */
    public function permite_conservar_el_mismo_codigo_de_la_materia()
    {
        $subject = $this->createSubject();

        $this->actingAs($this->adminUser)
            ->putJson(
                $this->updateUrl($subject),
                [
                    'nombre' => 'Calculo Avanzado',
                    'codigo' => '2008057',
                ]
            )
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Calculo Avanzado')
            ->assertJsonPath('data.codigo', '2008057');

        $this->assertDatabaseHas('materia', [
            'id_materia' => $subject->id_materia,
            'nombre'     => 'Calculo Avanzado',
            'codigo'     => '2008057',
        ]);
    }

    /** @test */
    public function devuelve_404_si_la_materia_no_existe()
    {
        $this->actingAs($this->adminUser)
            ->putJson(
                '/api/materias/999999',
                $this->validData()
            )
            ->assertStatus(404);
    }

    /** @test */
    public function rechaza_la_edicion_si_el_usuario_es_docente()
    {
        $subject = $this->createSubject();

        $this->actingAs($this->teacherUser)
            ->putJson(
                $this->updateUrl($subject),
                $this->validData()
            )
            ->assertStatus(403);

        $this->assertDatabaseHas('materia', [
            'id_materia' => $subject->id_materia,
            'nombre'     => 'Calculo I',
            'codigo'     => '2008057',
        ]);
    }

    /** @test */
    public function no_modifica_descripcion_ni_estado_de_la_materia()
    {
        $subject = $this->createSubject([
            'descripcion' => 'Contenido historico',
            'estado'      => Subject::ESTADO_INACTIVO,
        ]);

        $this->actingAs($this->adminUser)
            ->putJson(
                $this->updateUrl($subject),
                $this->validData()
            )
            ->assertOk();

        $subject->refresh();

        $this->assertSame(
            'Contenido historico',
            $subject->descripcion
        );

        $this->assertSame(
            Subject::ESTADO_INACTIVO,
            $subject->estado
        );
    }

    /** @test */
    public function preserva_el_examen_y_las_relaciones_academicas_asociadas()
    {
        $this->seedExamCatalog();

        $subject = Subject::findOrFail($this->calculoId);

        // Los fixtures académicos heredados todavía usan MAT-102.
        // Para esta HU se normaliza solo la materia que pasa por el PUT.
        $subject->codigo = '2008057';
        $subject->save();

        $exam = $this->createExam();

        $examBefore = $exam->only([
            'id_examen',
            'nombre_examen',
            'id_tipo_examen',
            'id_carrera',
            'id_materia',
            'id_usuario_docente',
            'estado',
        ]);

        $this->actingAs($this->adminUser)
            ->putJson(
                $this->updateUrl($subject),
                [
                    'nombre' => 'Calculo II Actualizado',
                    'codigo' => '2008058',
                ]
            )
            ->assertOk();

        $examAfter = $exam->fresh();

        $this->assertSame(
            $examBefore,
            $examAfter->only([
                'id_examen',
                'nombre_examen',
                'id_tipo_examen',
                'id_carrera',
                'id_materia',
                'id_usuario_docente',
                'estado',
            ])
        );

        $this->assertDatabaseHas('materia_carrera', [
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
        ]);

        $this->assertDatabaseHas('grupo', [
            'id_grupo'   => $this->grupoPropioId,
            'id_carrera' => $this->sistemasId,
            'id_materia' => $this->calculoId,
        ]);
    }

    /** @test */
    public function refleja_inmediatamente_los_cambios_en_el_catalogo()
    {
        $this->seedAcademicCatalog();

        $subject = Subject::findOrFail($this->calculoId);

        $this->actingAs($this->adminUser)
            ->putJson(
                $this->updateUrl($subject),
                [
                    'nombre' => 'Calculo II Actualizado',
                    'codigo' => '2008058',
                ]
            )
            ->assertOk();

        $data = $this->getJson('/api/materias')
            ->assertOk()
            ->json('data');

        $pairs = array_values(array_filter(
            $data,
            fn (array $pair): bool =>
                $pair['id_materia'] === $subject->id_materia
        ));

        $this->assertNotEmpty($pairs);

        foreach ($pairs as $pair) {
            $this->assertSame(
                'Calculo II Actualizado',
                $pair['nombre']
            );

            $this->assertSame(
                '2008058',
                $pair['codigo']
            );
        }
    }

    /** @test */
    public function registra_en_bitacora_solo_los_campos_modificados()
    {
        $subject = $this->createSubject();

        $this->actingAs($this->adminUser)
            ->putJson(
                $this->updateUrl($subject),
                [
                    'nombre' => 'Calculo Avanzado',
                    'codigo' => '2008057',
                ]
            )
            ->assertOk();

        $auditLog = AuditLog::where(
            'tabla_afectada',
            'materia'
        )
            ->latest('id_log')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertNotNull($auditLog->accion);

        $this->assertSame(
            'MODIFICAR',
            $auditLog->accion->operacion
        );

        $this->assertSame(
            ['nombre' => 'Calculo I'],
            $auditLog->antiguo_valor
        );

        $this->assertSame(
            ['nombre' => 'Calculo Avanzado'],
            $auditLog->nuevo_valor
        );

        $this->assertSame(
            $this->adminUser->id_usuario,
            $auditLog->id_usuario
        );

        $this->assertNotNull($auditLog->fecha_hora);
    }

    /** @test */
    public function no_registra_modificacion_si_los_datos_no_cambiaron()
    {
        $subject = $this->createSubject();

        $logsBefore = AuditLog::where(
            'tabla_afectada',
            'materia'
        )->count();

        $this->actingAs($this->adminUser)
            ->putJson(
                $this->updateUrl($subject),
                [
                    'nombre' => $subject->nombre,
                    'codigo' => $subject->codigo,
                ]
            )
            ->assertOk();

        $logsAfter = AuditLog::where(
            'tabla_afectada',
            'materia'
        )->count();

        $this->assertSame(
            $logsBefore,
            $logsAfter
        );
    }
}