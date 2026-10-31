<?php

namespace Tests\Feature\Academic;

use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * HU-006: las materias no se crean ni se editan desde la app. Los endpoints legados ya no
 * existen, ni siquiera para el Administrador.
 */
class LegacySubjectEndpointsRemovedTest extends TestCase
{
    use DatabaseTransactions;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->subject = Subject::create([
            'nombre' => 'Calculo I',
            'codigo' => '2008057',
            'estado' => Subject::ESTADO_ACTIVO,
        ]);

        $administrator = User::factory()->create();
        $administrator->roles()->attach(
            Role::where('nombre_rol', Role::ADMINISTRADOR)->value('id_rol'),
            ['fecha_inicio' => now()]
        );

        $this->actingAs($administrator);
    }

    public function test_editar_una_materia_ya_no_existe(): void
    {
        $this->putJson("/api/materias/{$this->subject->id_materia}", [
            'nombre' => 'Otro nombre',
            'codigo' => '1234567',
        ])->assertNotFound();

        $this->assertUnchanged();
    }

    public function test_editar_con_un_id_invalido_responde_404_y_nunca_500(): void
    {
        foreach (['abc', '0', '99999999999999999999', '-1'] as $id) {
            $this->putJson("/api/materias/{$id}", ['nombre' => 'X', 'codigo' => '1234567'])
                ->assertNotFound();
        }
    }

    public function test_registrar_una_materia_ya_no_existe(): void
    {
        $before = Subject::count();

        $this->postJson('/api/materias', [
            'nombre' => 'Materia Nueva',
            'codigo' => '7654321',
        ])->assertStatus(405);

        $this->assertSame($before, Subject::count());
    }

    public function test_no_hay_otra_forma_de_modificar_el_catalogo_por_la_api(): void
    {
        foreach (['PATCH', 'DELETE'] as $method) {
            $this->json($method, "/api/materias/{$this->subject->id_materia}", ['nombre' => 'X'])
                ->assertNotFound();
        }

        foreach (['PUT', 'PATCH', 'DELETE', 'POST'] as $method) {
            $this->assertContains(
                $this->json($method, '/api/materias/administracion', ['nombre' => 'X'])->getStatusCode(),
                [404, 405]
            );
        }

        $this->assertUnchanged();
    }

    public function test_las_rutas_de_materias_solo_aceptan_lectura(): void
    {
        $writeRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/materias'))
            ->filter(fn ($route): bool => array_diff($route->methods(), ['GET', 'HEAD']) !== [])
            ->map(fn ($route): string => implode('|', $route->methods()) . ' ' . $route->uri())
            ->values()
            ->all();

        $this->assertSame([], $writeRoutes);
    }

    private function assertUnchanged(): void
    {
        $this->assertSame(
            ['nombre' => 'Calculo I', 'codigo' => '2008057'],
            [
                'nombre' => DB::table('materia')->where('id_materia', $this->subject->id_materia)->value('nombre'),
                'codigo' => DB::table('materia')->where('id_materia', $this->subject->id_materia)->value('codigo'),
            ]
        );
    }
}
