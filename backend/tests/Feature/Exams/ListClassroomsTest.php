<?php

namespace Tests\Feature\Exams;

use App\Models\Classroom;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

class ListClassroomsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsSecurityAccounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSecurityAccounts();
    }

    /** @test */
    public function un_administrador_consulta_el_catalogo_de_ambientes()
    {
        Classroom::create([
            'nro_aula' => 'Aula 101',
            'capacidad' => 30,
            'ubicacion' => 'Modulo A',
            'estado' => 'ACTIVO',
        ]);

        $this->getJson('/api/ambientes')
            ->assertOk()
            ->assertJsonPath('data.0.nro_aula', 'Aula 101');
    }

    /** @test */
    public function rechaza_la_consulta_de_un_docente()
    {
        $docente = User::factory()->create();
        $this->giveRole($docente, Role::DOCENTE);
        $this->actAs($docente);

        $this->getJson('/api/ambientes')->assertForbidden();
    }
}
