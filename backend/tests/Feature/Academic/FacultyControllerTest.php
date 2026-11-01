<?php

namespace Tests\Feature\Academic;

use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-063: GET /api/facultades, el catálogo de facultades activas para el filtro del reporte.
 */
class FacultyControllerTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsSecurityAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSecurityAccounts();
    }

    public function test_sin_token_responde_401(): void
    {
        $this->actAsGuest();

        $this->getJson('/api/facultades')->assertUnauthorized();
    }

    public function test_un_docente_recibe_403(): void
    {
        $this->actAs($this->createAccount(Role::DOCENTE));

        $this->getJson('/api/facultades')->assertForbidden();
    }

    public function test_un_auxiliar_recibe_403(): void
    {
        $this->actAs($this->createAccount(Role::AUXILIAR));

        $this->getJson('/api/facultades')->assertForbidden();
    }

    public function test_un_administrador_recibe_la_lista_ordenada_por_nombre(): void
    {
        DB::table('facultad')->insert([
            ['nombre' => 'Facultad de Ciencias y Tecnología', 'codigo' => 'FCYT', 'estado' => 'ACTIVO'],
            ['nombre' => 'Facultad de Ciencias Económicas', 'codigo' => 'FCE', 'estado' => 'ACTIVO'],
            ['nombre' => 'Facultad Inactiva', 'codigo' => 'FIN', 'estado' => 'INACTIVO'],
        ]);

        $response = $this->getJson('/api/facultades')->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.codigo', 'FCE')
            ->assertJsonPath('data.1.codigo', 'FCYT');

        $this->assertSame(
            ['FCE', 'FCYT'],
            array_column($response->json('data'), 'codigo')
        );
    }
}
