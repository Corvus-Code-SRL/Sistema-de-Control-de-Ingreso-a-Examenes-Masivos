<?php

namespace Tests\Feature\Academic;

use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdminSubjectListTest extends TestCase
{
    use DatabaseTransactions;

    private User $adminUser;
    private User $teacherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $adminRole = Role::where('nombre_rol', Role::ADMINISTRADOR)->first();
        $teacherRole = Role::where('nombre_rol', Role::DOCENTE)->first();

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

    /** @test */
    public function administrador_puede_consultar_el_catalogo_de_materias()
    {
        Subject::create([
            'nombre' => 'Calculo I',
            'codigo' => '2008057',
            'estado' => Subject::ESTADO_ACTIVO,
        ]);

        $this->actingAs($this->adminUser)
            ->getJson('/api/materias/administracion')
            ->assertOk()
            ->assertJsonPath('data.0.nombre', 'Calculo I')
            ->assertJsonPath('data.0.codigo', '2008057');
    }

    /** @test */
    public function docente_no_puede_consultar_el_catalogo_administrativo()
    {
        $this->actingAs($this->teacherUser)
            ->getJson('/api/materias/administracion')
            ->assertStatus(403);
    }

    /** @test */
    public function incluye_materias_sin_relacion_materia_carrera()
    {
        $subject = Subject::create([
            'nombre' => 'Fisica I',
            'codigo' => '2008058',
            'estado' => Subject::ESTADO_ACTIVO,
        ]);

        $this->actingAs($this->adminUser)
            ->getJson('/api/materias/administracion')
            ->assertOk()
            ->assertJsonFragment([
                'id_materia' => $subject->id_materia,
                'nombre' => 'Fisica I',
                'codigo' => '2008058',
            ]);
    }

    /** @test */
    public function incluye_materias_activas_e_inactivas()
    {
        Subject::create([
            'nombre' => 'Algebra',
            'codigo' => '2008059',
            'estado' => Subject::ESTADO_ACTIVO,
        ]);

        Subject::create([
            'nombre' => 'Materia Antigua',
            'codigo' => '2008060',
            'estado' => Subject::ESTADO_INACTIVO,
        ]);

        $this->actingAs($this->adminUser)
            ->getJson('/api/materias/administracion')
            ->assertOk()
            ->assertJsonFragment([
                'codigo' => '2008059',
                'estado' => Subject::ESTADO_ACTIVO,
            ])
            ->assertJsonFragment([
                'codigo' => '2008060',
                'estado' => Subject::ESTADO_INACTIVO,
            ]);
    }

    /** @test */
    public function cada_materia_aparece_una_sola_vez()
    {
        $subject = Subject::create([
            'nombre' => 'Programacion I',
            'codigo' => '2008061',
            'estado' => Subject::ESTADO_ACTIVO,
        ]);

        $data = $this->actingAs($this->adminUser)
            ->getJson('/api/materias/administracion')
            ->assertOk()
            ->json('data');

        $matches = array_filter(
            $data,
            fn (array $item): bool =>
                $item['id_materia'] === $subject->id_materia
        );

        $this->assertCount(1, $matches);
    }
}