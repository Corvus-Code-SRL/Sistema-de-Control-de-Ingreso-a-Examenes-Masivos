<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Ayudas para probar la rectificación de la Central de Riesgo (HU-062). Se usa junto con
 * SeedsRiskCenterRecords y SeedsSecurityAccounts: quien actúa es la cuenta autenticada en la prueba.
 *
 * `rectify()` usa la vía directa (sin confirmación): deja antes al docente aprobador inactivo desde hace más de
 * los días configurados, que es lo que permite retirar el registro de inmediato. La vía normal es la solicitud
 * al docente ({@see self::requestUrl()}).
 */
trait SeedsRectifications
{
    protected function rectifyUrl(int $recordId): string
    {
        return "/api/rectificacion-riesgo/registros/{$recordId}/rectificar-sin-confirmacion";
    }

    protected function requestUrl(int $recordId): string
    {
        return "/api/rectificacion-riesgo/registros/{$recordId}/solicitudes";
    }

    /** Pide enviar la solicitud al docente por la API con un motivo válido, salvo lo que se indique. */
    protected function sendRequest(int $recordId, array $overrides = []): TestResponse
    {
        return $this->postJson($this->requestUrl($recordId), array_merge([
            'motivo' => 'Se solicita retirar: el reporte no tenía sustento.',
        ], $overrides));
    }

    /**
     * Deja al docente que aprobó el registro activo, con rol docente abierto: es a quien se le puede enviar una
     * solicitud. Devuelve su id.
     */
    protected function makeApproverActive(int $recordId, string $role = 'Docente'): string
    {
        $teacherId = $this->approverId($recordId);

        DB::table('usuario')->where('id_usuario', $teacherId)->update(['estado' => 'ACTIVO']);
        DB::table('usuario_rol')->where('id_usuario', $teacherId)->delete();
        DB::table('usuario_rol')->insert([
            'id_usuario' => $teacherId,
            'id_rol' => $this->roleId($role),
            'fecha_inicio' => '2026-01-10 08:00:00',
        ]);

        return $teacherId;
    }

    /**
     * Deja al docente que aprobó el registro sin rol docente desde hace `$daysAgo` días (su rol se cerró
     * entonces): es la fecha de inactividad ROL_CERRADO.
     */
    protected function makeApproverInactive(int $recordId, int $daysAgo): ?string
    {
        $teacherId = $this->approverId($recordId);

        if ($teacherId === '') {
            return null;
        }
        $closedAt = now()->subDays($daysAgo)->startOfSecond();

        DB::table('usuario_rol')->where('id_usuario', $teacherId)->delete();
        DB::table('usuario_rol')->insert([
            'id_usuario' => $teacherId,
            'id_rol' => $this->roleId('Docente'),
            'fecha_inicio' => $closedAt->copy()->subDays(30),
            'fecha_fin' => $closedAt,
        ]);

        return $teacherId;
    }

    protected function approverId(int $recordId): string
    {
        return (string) DB::table('central_riesgo')
            ->join('reporte_estudiante', 'reporte_estudiante.id_reporte_est', '=', 'central_riesgo.id_reporte_est')
            ->where('central_riesgo.id_cr_estudiante', $recordId)
            ->value('reporte_estudiante.id_usuario_revisor');
    }

    /** Inserta una solicitud directamente, sin pasar por la API. @return int id de la solicitud */
    protected function insertRectificationRequest(int $recordId, array $overrides = []): int
    {
        $sentAt = $overrides['fecha_solicitud'] ?? now()->subDay();

        return DB::table('solicitud_rectificacion')->insertGetId(array_merge([
            'id_cr_estudiante' => $recordId,
            'id_usuario_solicitante' => $this->administratorId,
            'motivo_solicitud' => 'Se solicita retirar el registro por un error de identificación.',
            'id_usuario_docente' => $this->approverId($recordId),
            'estado' => 'PENDIENTE',
            'fecha_solicitud' => $sentAt,
            'vence_en' => now()->addDays(6),
        ], $overrides), 'id_solicitud_rect');
    }

    /** Pide rectificar sin confirmación por la API con un motivo válido, salvo lo que se indique en $overrides. */
    protected function rectify(int $recordId, array $overrides = []): TestResponse
    {
        $this->makeApproverInactive($recordId, 400);

        return $this->postJson($this->rectifyUrl($recordId), array_merge([
            'motivo' => 'Se retira tras la reunión: el reporte no tenía sustento.',
        ], $overrides));
    }

    /**
     * Deja un registro ya rectificado sin pasar por la API, para probar lo que lo ignora. El administrador
     * es el de la prueba salvo que se indique otro.
     */
    protected function voidRecord(
        int $recordId,
        ?string $adminId = null,
        string $reason = 'Registro retirado por error de identificación.',
        string $at = '2026-10-20 15:00:00+00'
    ): void {
        DB::table('central_riesgo')->where('id_cr_estudiante', $recordId)->update([
            'rectificado_en' => $at,
            'id_usuario_rectificador' => $adminId ?? $this->administratorId,
            'motivo_rectificacion' => $reason,
        ]);
    }

    /** @return array<string, mixed> la fila cruda de central_riesgo, incluidas las columnas de rectificación */
    protected function recordRow(int $recordId): array
    {
        return (array) DB::table('central_riesgo')->where('id_cr_estudiante', $recordId)->first();
    }
}
