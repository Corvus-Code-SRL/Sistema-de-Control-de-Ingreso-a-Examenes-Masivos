<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsAuditLogRows;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

/**
 * HU-015: ningún campo sensible sale de la API, ni en el listado ni en el detalle (ajustes A y B).
 *
 * Las filas se escriben directo en `log` con claves que el AuditLogService de hoy ya filtra (y otras que no),
 * porque la máscara de lectura debe sostenerse sola: no depende de cómo se escribió la fila.
 */
class AuditLogSensitiveDataTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAuditLogRows;
    use SeedsSecurityAccounts;

    private const SENTINELS = [
        'HASH-SENTINEL-1', 'TEMP-SENTINEL-2', 'TOKEN-SENTINEL-3', 'APIKEY-SENTINEL-4', 'REMEMBER-SENTINEL-5',
        'NESTED-SENTINEL-6', 'EVID-URL-SENTINEL-7', 'EVID-ID-SENTINEL-8', 'MIXED-SENTINEL-9', 'LIST-SENTINEL-10',
        'ANTES-SENTINEL-11', 'ACCENT-SENTINEL-12',
    ];

    private User $docente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareAuditLog();

        $this->docente = $this->namedAccount('Marcelo', 'Quiroga', '10452', Role::DOCENTE);
    }

    /** @return array<string, mixed> */
    private function sensitivePayload(): array
    {
        return [
            'id_usuario' => $this->docente->id_usuario,
            'nombre' => 'Visible',
            'contrasenia' => 'HASH-SENTINEL-1',
            'contrasenia_temporal' => 'TEMP-SENTINEL-2',
            'Password' => 'MIXED-SENTINEL-9',
            'API_KEY' => 'APIKEY-SENTINEL-4',
            'rememberToken' => 'REMEMBER-SENTINEL-5',
            'access_token' => 'TOKEN-SENTINEL-3',
            'contraseña_anterior' => 'ACCENT-SENTINEL-12',
            'evidencia_url' => 'EVID-URL-SENTINEL-7',
            'evidencia_public_id' => 'EVID-ID-SENTINEL-8',
            'perfil' => ['telefono' => '70000000', 'Remember-Token' => 'NESTED-SENTINEL-6'],
            'intentos' => [['password' => 'LIST-SENTINEL-10', 'ok' => true]],
        ];
    }

    public function test_ningun_valor_sensible_aparece_en_el_listado_ni_en_el_detalle(): void
    {
        $payload = $this->sensitivePayload();
        $antes = $payload;
        $antes['contrasenia'] = 'ANTES-SENTINEL-11';

        $ids = [
            $this->logRow($this->docente, 'CREAR', 'usuario', null, $payload),
            $this->logRow($this->docente, 'MODIFICAR', 'usuario', $antes, $payload),
            $this->logRow($this->docente, 'QUITAR_AUXILIAR_EXAMEN', 'examen_auxiliar', $antes, null),
            $this->logRow($this->docente, 'CREAR', 'reporte_estudiante', null, $payload),
        ];

        $bodies = [$this->getJson('/api/bitacora')->assertOk()->getContent()];

        foreach ($ids as $id) {
            $bodies[] = $this->getJson("/api/bitacora/{$id}")->assertOk()->getContent();
        }

        foreach ($bodies as $body) {
            foreach (self::SENTINELS as $sentinel) {
                $this->assertStringNotContainsString($sentinel, $body, "Se filtró {$sentinel}");
            }
        }
    }

    public function test_un_campo_oculto_sale_sin_valores_y_el_resto_sigue_visible(): void
    {
        $id = $this->logRow($this->docente, 'CREAR', 'usuario', null, $this->sensitivePayload());

        $changes = collect($this->getJson("/api/bitacora/{$id}")->assertOk()->json('data.cambios'))->keyBy('campo');

        foreach ([
            'contrasenia', 'contrasenia_temporal', 'Password', 'API_KEY', 'rememberToken', 'access_token',
            'contraseña_anterior', 'evidencia_url', 'evidencia_public_id',
        ] as $hidden) {
            $this->assertTrue($changes[$hidden]['oculto'], $hidden);
            $this->assertSame(['campo', 'etiqueta', 'oculto'], array_keys($changes[$hidden]), $hidden);
        }

        $this->assertFalse($changes['nombre']['oculto']);
        $this->assertSame('Visible', $changes['nombre']['nuevo']);
    }

    public function test_los_campos_ocultos_anidados_se_reemplazan_por_oculto(): void
    {
        $id = $this->logRow($this->docente, 'CREAR', 'usuario', null, $this->sensitivePayload());

        $changes = collect($this->getJson("/api/bitacora/{$id}")->assertOk()->json('data.cambios'))->keyBy('campo');

        // assertEquals: jsonb no conserva el orden de las claves.
        $this->assertEquals(['telefono' => '70000000', 'Remember-Token' => '(oculto)'], $changes['perfil']['nuevo']);
        $this->assertEquals([['password' => '(oculto)', 'ok' => true]], $changes['intentos']['nuevo']);
    }

    public function test_un_campo_oculto_que_no_cambio_no_se_lista(): void
    {
        $id = $this->logRow(
            $this->docente,
            'MODIFICAR',
            'usuario',
            ['id_usuario' => 'x', 'contrasenia' => 'HASH-SENTINEL-1', 'nombre' => 'A'],
            ['id_usuario' => 'x', 'contrasenia' => 'HASH-SENTINEL-1', 'nombre' => 'B']
        );

        $campos = array_column($this->getJson("/api/bitacora/{$id}")->assertOk()->json('data.cambios'), 'campo');

        $this->assertSame(['nombre'], $campos);
    }

    public function test_los_indicadores_booleanos_de_contrasenia_si_se_muestran(): void
    {
        $id = $this->logRow(
            $this->docente,
            'MODIFICAR',
            'usuario',
            ['debe_cambiar_contrasenia' => true],
            ['debe_cambiar_contrasenia' => false, 'contrasenia_cambiada' => true]
        );

        $changes = collect($this->getJson("/api/bitacora/{$id}")->assertOk()->json('data.cambios'))->keyBy('campo');

        $this->assertFalse($changes['debe_cambiar_contrasenia']['oculto']);
        $this->assertTrue($changes['debe_cambiar_contrasenia']['anterior']);
        $this->assertFalse($changes['debe_cambiar_contrasenia']['nuevo']);
        $this->assertTrue($changes['contrasenia_cambiada']['nuevo']);
    }

    public function test_un_indicador_con_un_valor_que_no_es_booleano_se_oculta(): void
    {
        $id = $this->logRow(
            $this->docente,
            'MODIFICAR',
            'usuario',
            null,
            ['debe_cambiar_contrasenia' => 'HASH-SENTINEL-1'],
        );

        $response = $this->getJson("/api/bitacora/{$id}")->assertOk();

        $this->assertTrue($response->json('data.cambios.0.oculto'));
        $this->assertStringNotContainsString('HASH-SENTINEL-1', $response->getContent());
    }

    public function test_correo_ip_motivo_y_texto_libre_se_muestran_tal_como_se_guardaron(): void
    {
        $id = $this->logRow($this->docente, 'CREAR', 'reporte_estudiante', null, [
            'id_reporte_est' => 3,
            'correo' => 'persona@example.test',
            'ip' => '203.0.113.9',
            'motivo' => 'credenciales_invalidas',
            'descripcion' => 'Texto libre del docente',
            'observacion_revision' => 'Observación libre',
        ]);

        $changes = collect($this->getJson("/api/bitacora/{$id}")->assertOk()->json('data.cambios'))->keyBy('campo');

        foreach ([
            'correo' => 'persona@example.test',
            'ip' => '203.0.113.9',
            'motivo' => 'credenciales_invalidas',
            'descripcion' => 'Texto libre del docente',
            'observacion_revision' => 'Observación libre',
        ] as $field => $value) {
            $this->assertFalse($changes[$field]['oculto'], $field);
            $this->assertSame($value, $changes[$field]['nuevo'], $field);
        }
    }

    public function test_el_registro_afectado_tampoco_filtra_claves_ocultas(): void
    {
        $id = $this->logRow($this->docente, 'CREAR', 'tabla_sin_modulo', null, [
            'id_cosa' => 5,
            'id_token' => 'TOKEN-SENTINEL-3',
        ]);

        $body = $this->getJson("/api/bitacora/{$id}")->assertOk()->getContent();

        $this->assertStringNotContainsString('TOKEN-SENTINEL-3', $body);
    }
}
