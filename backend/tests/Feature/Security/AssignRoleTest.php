<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssignRoleTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Role $docente;
    private Role $auxiliar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(ActionSeeder::class);

        $this->usuario  = User::factory()->create();
        $this->docente  = Role::where('nombre_rol', Role::DOCENTE)->first();
        $this->auxiliar = Role::where('nombre_rol', Role::AUXILIAR)->first();

        // Forzamos la configuración SOLO para que esta prueba use el usuario recién creado
        config(['sciem.usuario_prueba' => $this->usuario->id_usuario]);
        config(['sciem.docente_fijo_id' => $this->usuario->id_usuario]);
    }

    /** @test */
    public function asigna_un_rol_a_una_cuenta_sin_rol()          // CA 1
    {
        $this->postJson("/api/usuarios/{$this->usuario->id_usuario}/rol", [
            'id_rol' => $this->docente->id_rol,
        ])->assertStatus(200)
          ->assertJsonPath('data.nombre_rol', 'Docente');

        $this->assertDatabaseHas('usuario_rol', [
            'id_usuario' => $this->usuario->id_usuario,
            'id_rol'     => $this->docente->id_rol,
            'fecha_fin'  => null,
        ]);
    }

    /** @test */
    public function al_cambiar_de_rol_cierra_el_anterior_sin_borrarlo()   // CA 3 + CA 9
    {
        $url = "/api/usuarios/{$this->usuario->id_usuario}/rol";

        $this->postJson($url, ['id_rol' => $this->auxiliar->id_rol]);
        $this->postJson($url, ['id_rol' => $this->docente->id_rol]);

        // El historial conserva las dos filas
        $this->assertDatabaseCount('usuario_rol', 2);

        // El rol anterior quedó cerrado
        $anterior = DB::table('usuario_rol')
            ->where('id_rol', $this->auxiliar->id_rol)
            ->first();
        $this->assertNotNull($anterior->fecha_fin);

        // Solo hay un rol vigente
        $vigentes = DB::table('usuario_rol')
            ->where('id_usuario', $this->usuario->id_usuario)
            ->whereNull('fecha_fin')
            ->count();
        $this->assertSame(1, $vigentes);
    }

    /** @test */
    public function no_registra_nada_si_el_rol_no_cambia()        // CA 13
    {
        $url = "/api/usuarios/{$this->usuario->id_usuario}/rol";

        $this->postJson($url, ['id_rol' => $this->docente->id_rol]);
        $this->postJson($url, ['id_rol' => $this->docente->id_rol]);

        $this->assertDatabaseCount('usuario_rol', 1);
    }

    /** @test */
    public function rechaza_un_rol_inexistente()                  // CA 4
    {
        $this->postJson("/api/usuarios/{$this->usuario->id_usuario}/rol", ['id_rol' => 9999])
             ->assertStatus(422)
             ->assertJsonValidationErrors('id_rol');
    }

    /** @test */
    public function rechaza_la_peticion_sin_rol()                 // CA 6
    {
        $this->postJson("/api/usuarios/{$this->usuario->id_usuario}/rol", [])
             ->assertStatus(422)
             ->assertJsonValidationErrors('id_rol');
    }

    /** @test */
    public function asignar_rol_no_habilita_una_cuenta_deshabilitada()  // CA 10
    {
        $inactivo = User::factory()->inactivo()->create();

        $this->postJson("/api/usuarios/{$inactivo->id_usuario}/rol", [
            'id_rol' => $this->docente->id_rol,
        ])->assertStatus(200);

        $this->assertDatabaseHas('usuario', [
            'id_usuario' => $inactivo->id_usuario,
            'estado'     => 'INACTIVO',
        ]);
    }

    /** @test */
    public function consulta_el_rol_actual()                      // CA 16
    {
        $url = "/api/usuarios/{$this->usuario->id_usuario}/rol";

        $this->postJson($url, ['id_rol' => $this->docente->id_rol]);

        $this->getJson($url)
             ->assertStatus(200)
             ->assertJsonPath('data.nombre_rol', 'Docente');
    }

    /** @test */
    public function devuelve_null_si_la_cuenta_no_tiene_rol()
    {
        $this->getJson("/api/usuarios/{$this->usuario->id_usuario}/rol")
             ->assertStatus(200)
             ->assertJsonPath('data', null);
    }

    /** @test */
    public function devuelve_404_si_el_usuario_no_existe()
    {
        $this->getJson('/api/usuarios/00000000-0000-0000-0000-000000000099/rol')
             ->assertStatus(404);
    }
}