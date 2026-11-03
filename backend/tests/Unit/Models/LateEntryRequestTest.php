<?php

namespace Tests\Unit\Models;

use App\Models\LateEntryRequest;
use App\Models\StudentExam;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

class LateEntryRequestTest extends TestCase
{
    public function test_usa_la_tabla_y_la_clave_primaria_de_la_base(): void
    {
        $request = new LateEntryRequest();

        $this->assertSame('solicitud_ingreso_retraso', $request->getTable());
        $this->assertSame('id_solicitud', $request->getKeyName());
        $this->assertTrue($request->getIncrementing());
        $this->assertFalse($request->usesTimestamps());
    }

    public function test_define_sus_columnas_asignables(): void
    {
        $this->assertSame([
            'id_examen',
            'id_estudiante',
            'id_ambiente',
            'id_usuario_solicitante',
            'id_usuario_resolutor',
            'estado',
            'solicitado_en',
            'resuelto_en',
        ], (new LateEntryRequest())->getFillable());
    }

    public function test_relaciones_apuntan_a_sus_claves(): void
    {
        $request = new LateEntryRequest();
        $relations = [
            'exam' => ['id_examen', 'id_examen'],
            'student' => ['id_estudiante', 'id_estudiante'],
            'classroom' => ['id_ambiente', 'id_ambiente'],
            'requester' => ['id_usuario_solicitante', 'id_usuario'],
            'resolver' => ['id_usuario_resolutor', 'id_usuario'],
        ];

        foreach ($relations as $name => [$foreignKey, $ownerKey]) {
            $relation = $request->{$name}();

            $this->assertInstanceOf(BelongsTo::class, $relation);
            $this->assertSame($foreignKey, $relation->getForeignKeyName());
            $this->assertSame($ownerKey, $relation->getOwnerKeyName());
        }
    }

    public function test_constantes_coinciden_con_los_valores_de_la_base(): void
    {
        $this->assertSame(
            ['PENDIENTE', 'AUTORIZADA', 'RECHAZADA'],
            [LateEntryRequest::PENDIENTE, LateEntryRequest::AUTORIZADA, LateEntryRequest::RECHAZADA]
        );
        $this->assertSame(
            ['INGRESO', 'CON_RETRASO', 'NO_INGRESO'],
            [StudentExam::INGRESO, StudentExam::CON_RETRASO, StudentExam::NO_INGRESO]
        );
    }
}
