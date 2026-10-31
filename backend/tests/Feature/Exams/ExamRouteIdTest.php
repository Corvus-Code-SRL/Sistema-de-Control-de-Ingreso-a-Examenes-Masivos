<?php

namespace Tests\Feature\Exams;

use Tests\TestCase;

class ExamRouteIdTest extends TestCase
{
    /**
     * HU-024: los IDs fuera del rango admitido deben responder 404,
     * sin llegar al model binding ni producir errores HTTP 500.
     */
    public function test_rejects_oversized_exam_ids_in_all_routes(): void
    {
        $examId = '2147483648';
        $userId = '00000000-0000-4000-8000-000000000011';

        $this->getJson("/api/examenes/{$examId}/auxiliares")
            ->assertStatus(404);

        $this->postJson("/api/examenes/{$examId}/finalizar")
            ->assertStatus(404);

        $this->postJson("/api/examenes/{$examId}/grupos", [
            'grupos' => [],
        ])->assertStatus(404);

        $this->putJson(
            "/api/examenes/{$examId}/auxiliares/{$userId}/ambiente",
            ['id_ambiente' => 1]
        )->assertStatus(404);
    }
}