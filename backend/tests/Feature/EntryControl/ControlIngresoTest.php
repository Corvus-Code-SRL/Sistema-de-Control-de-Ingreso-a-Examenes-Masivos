<?php

namespace Tests\Feature\EntryControl;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ControlIngresoTest extends TestCase
{
    use DatabaseTransactions;

    public function testReturnsCurrentExamStatusWithHeartbeat(): void
    {
        // 1. Solo creamos al usuario falso para pasar la aduana (auth:sanctum)
        // (La línea anterior funcionó bien porque Laravel trae un UserFactory por defecto)
        $user = User::factory()->create();
        
        // 2. Simulamos el ID de un examen cualquiera (ej. el Examen #1)
        $examId = 1;

        // 3. Hacemos la petición
        $response = $this->actingAs($user)
            ->getJson("/api/control-ingreso/examenes/{$examId}/estado");

        // 4. Verificamos que devuelva 200 OK y la estructura correcta
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'ingresados',
                    'pendientes',
                    'total',
                    'ultimos_ingresos',
                    'conectados',
                    'version',
                ]
            ]);
    }
}