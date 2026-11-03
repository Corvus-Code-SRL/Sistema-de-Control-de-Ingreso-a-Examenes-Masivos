<?php

namespace Tests\Unit\Support;

use App\Support\Audit\AuditLogChanges;
use App\Support\Audit\AuditLogSanitizer;
use Tests\TestCase;

/**
 * HU-015, ajuste B: la máscara recursiva y el cálculo de cambios, sin pasar por HTTP.
 */
class AuditLogSanitizerTest extends TestCase
{
    private AuditLogSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new AuditLogSanitizer();
    }

    /** @dataProvider hiddenFields */
    public function test_oculta_los_campos_de_la_lista_sin_importar_mayusculas_ni_separadores(string $field): void
    {
        $this->assertTrue($this->sanitizer->isHidden($field, 'valor'), $field);
    }

    public function hiddenFields(): array
    {
        return array_map(fn ($field) => [$field], [
            'contrasenia', 'CONTRASENIA', 'contrasenia_temporal', 'contrasenia_actual', 'nueva_contrasenia',
            'contraseña', 'Contraseña_Anterior', 'password', 'Password', 'PASSWORD', 'password_confirmation',
            'remember_token', 'rememberToken', 'Remember-Token', 'REMEMBER TOKEN', 'token', 'api_token',
            'access_token', 'refreshToken', 'API_KEY', 'apiKey', 'api-key', 'client_secret', 'Secret',
            'evidencia_url', 'Evidencia_URL', 'evidenciaPublicId', 'evidencia_public_id',
        ]);
    }

    /** @dataProvider visibleFields */
    public function test_no_oculta_los_campos_corrientes(string $field): void
    {
        $this->assertFalse($this->sanitizer->isHidden($field, 'valor'), $field);
    }

    public function visibleFields(): array
    {
        return array_map(fn ($field) => [$field], [
            'id_examen', 'num_grupo', 'nombre', 'correo', 'cod_sis', 'ip', 'motivo', 'descripcion',
            'observacion_revision', 'estado', 'evidencia', 'tipo_inicio',
        ]);
    }

    public function test_los_indicadores_booleanos_no_ocultan_pero_con_otro_valor_si(): void
    {
        foreach (['debe_cambiar_contrasenia', 'contrasenia_cambiada', 'Debe-Cambiar-Contrasenia'] as $field) {
            $this->assertFalse($this->sanitizer->isHidden($field, true), $field);
            $this->assertFalse($this->sanitizer->isHidden($field, false), $field);
            $this->assertFalse($this->sanitizer->isHidden($field, null), $field);
            $this->assertTrue($this->sanitizer->isHidden($field, 'abc'), $field);
            $this->assertTrue($this->sanitizer->isHidden($field, ['x']), $field);
        }
    }

    public function test_redact_reemplaza_a_cualquier_profundidad_y_conserva_el_resto(): void
    {
        $data = [
            'id_examen' => 1,
            'Password' => 'a',
            'perfil' => [
                'telefono' => '700',
                'API_KEY' => 'b',
                'profundo' => ['rememberToken' => 'c', 'ok' => ['x', 'y']],
            ],
            'lista' => [['token' => 'd', 'n' => 1], ['n' => 2]],
            0 => 'indice numerico',
        ];

        $this->assertSame([
            'id_examen' => 1,
            'Password' => '(oculto)',
            'perfil' => [
                'telefono' => '700',
                'API_KEY' => '(oculto)',
                'profundo' => ['rememberToken' => '(oculto)', 'ok' => ['x', 'y']],
            ],
            'lista' => [['token' => '(oculto)', 'n' => 1], ['n' => 2]],
            0 => 'indice numerico',
        ], $this->sanitizer->redact($data));
    }

    public function test_redact_deja_pasar_los_valores_que_no_son_listas(): void
    {
        $this->assertSame('texto', $this->sanitizer->redact('texto'));
        $this->assertSame(5, $this->sanitizer->redact(5));
        $this->assertNull($this->sanitizer->redact(null));
    }

    public function test_la_lista_sale_de_la_configuracion(): void
    {
        config(['sciem.bitacora.campos_ocultos.exactos' => ['campo_nuevo']]);

        $this->assertTrue($this->sanitizer->isHidden('Campo_Nuevo', 'x'));
        $this->assertFalse($this->sanitizer->isHidden('evidencia_url', 'x'));
    }

    public function test_los_cambios_de_una_modificacion_son_solo_los_campos_distintos(): void
    {
        $changes = (new AuditLogChanges($this->sanitizer))->between(
            ['a' => 1, 'b' => 'x', 'c' => [1, 2]],
            ['a' => 1, 'b' => 'y', 'c' => [1, 2, 3], 'd' => true]
        );

        $this->assertSame(['b', 'c', 'd'], array_column($changes, 'campo'));
        $this->assertSame(['x', [1, 2], null], array_column($changes, 'anterior'));
        $this->assertSame(['y', [1, 2, 3], true], array_column($changes, 'nuevo'));
    }

    public function test_un_campo_oculto_nunca_lleva_valores_ni_en_altas_ni_en_bajas(): void
    {
        $diff = new AuditLogChanges($this->sanitizer);

        foreach ([
            $diff->between(null, ['password' => 'a', 'ok' => 1]),
            $diff->between(['password' => 'a', 'ok' => 1], null),
            $diff->between(['password' => 'a'], ['password' => 'b']),
        ] as $changes) {
            $hidden = $changes[0];

            $this->assertSame('password', $hidden['campo']);
            $this->assertTrue($hidden['oculto']);
            $this->assertArrayNotHasKey('anterior', $hidden);
            $this->assertArrayNotHasKey('nuevo', $hidden);
        }
    }

    public function test_sin_datos_no_hay_cambios(): void
    {
        $this->assertSame([], (new AuditLogChanges($this->sanitizer))->between(null, null));
        $this->assertSame([], (new AuditLogChanges($this->sanitizer))->between([], []));
    }
}
