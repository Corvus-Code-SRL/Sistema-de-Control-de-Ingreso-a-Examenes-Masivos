<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ActionSeeder extends Seeder
{
    public function run()
    {
        $acciones = [
            ['operacion' => 'CREAR',        'tipo_operacion' => 'INSERT', 'descripcion' => 'Creación de un registro'],
            ['operacion' => 'MODIFICAR',    'tipo_operacion' => 'UPDATE', 'descripcion' => 'Modificación de un registro'],
            ['operacion' => 'DESHABILITAR', 'tipo_operacion' => 'UPDATE', 'descripcion' => 'Cambio de estado a INACTIVO'],
            ['operacion' => 'HABILITAR',    'tipo_operacion' => 'UPDATE', 'descripcion' => 'Cambio de estado a ACTIVO'],
            ['operacion' => 'ASIGNAR_ROL',  'tipo_operacion' => 'INSERT', 'descripcion' => 'Asignación de rol a una cuenta'],
            ['operacion' => 'CANCELAR',     'tipo_operacion' => 'UPDATE', 'descripcion' => 'Cancelación de un examen'],
            ['operacion' => 'INICIAR',      'tipo_operacion' => 'UPDATE', 'descripcion' => 'Inicio manual o automático de un examen'],
            ['operacion' => 'FINALIZAR',    'tipo_operacion' => 'UPDATE', 'descripcion' => 'Finalización manual de un examen'],
            ['operacion' => 'INICIO_SESION_FALLIDO', 'tipo_operacion' => 'LOGIN', 'descripcion' => 'Intento fallido de inicio de sesión'],
            ['operacion' => 'ANADIR_AUXILIAR_GRUPO', 'tipo_operacion' => 'INSERT', 'descripcion' => 'Incorporación de un auxiliar a un grupo'],
            ['operacion' => 'HABILITAR_AUXILIAR_EXAMEN', 'tipo_operacion' => 'INSERT', 'descripcion' => 'Habilitación de un auxiliar para un examen'],
            ['operacion' => 'QUITAR_AUXILIAR_GRUPO', 'tipo_operacion' => 'UPDATE', 'descripcion' => 'Baja de un auxiliar de un grupo'],
            ['operacion' => 'QUITAR_AUXILIAR_EXAMEN', 'tipo_operacion' => 'DELETE', 'descripcion' => 'Retiro de la habilitación de un auxiliar en un examen'],
            ['operacion' => 'APROBAR_REPORTE', 'tipo_operacion' => 'UPDATE', 'descripcion' => 'Aprobación de un reporte de falta'],
            ['operacion' => 'RECHAZAR_REPORTE', 'tipo_operacion' => 'UPDATE', 'descripcion' => 'Rechazo de un reporte de falta'],
            [
                'operacion' => 'SOLICITAR_INGRESO_RETRASO',
                'tipo_operacion' => 'INSERT',
                'descripcion' => 'Solicitud de ingreso con retraso de un estudiante',
            ],
            [
                'operacion' => 'AUTORIZAR_INGRESO_RETRASO',
                'tipo_operacion' => 'UPDATE',
                'descripcion' => 'Autorización de una solicitud de ingreso con retraso',
            ],
            [
                'operacion' => 'RECHAZAR_INGRESO_RETRASO',
                'tipo_operacion' => 'UPDATE',
                'descripcion' => 'Rechazo de una solicitud de ingreso con retraso',
            ],
            [
                'operacion' => 'REGISTRAR_INGRESO_RETRASO',
                'tipo_operacion' => 'INSERT',
                'descripcion' => 'Registro directo de un ingreso con retraso por el docente',
            ],
            [
                'operacion' => 'EXPULSAR_ESTUDIANTE',
                'tipo_operacion' => 'UPDATE',
                'descripcion' => 'Retiro de un estudiante durante el examen',
            ],
            [
                'operacion' => 'RECTIFICAR_REGISTRO',
                'tipo_operacion' => 'UPDATE',
                'descripcion' => 'Rectificación de un registro de la Central de Riesgo',
            ],
            [
                'operacion' => 'INVITAR_COLABORADOR',
                'tipo_operacion' => 'INSERT',
                'descripcion' => 'Invitación de un docente colaborador a un examen',
            ],
        ];

        foreach ($acciones as $accion) {
            DB::table('accion')->updateOrInsert(['operacion' => $accion['operacion']], $accion);
        }

        $this->call(ExamSituationActionSeeder::class);

        DB::table('accion')->updateOrInsert(
            ['operacion' => 'GUARDAR_HABILITADOS'],
            [
                'operacion' => 'GUARDAR_HABILITADOS',
                'tipo_operacion' => 'UPDATE',
                'descripcion' => 'Guardado de cambios en la lista de habilitados de un examen',
            ]
        );

        // Van al final: los ids existentes no cambian y, en una base nueva, quedan en el mismo orden que en producción.
        $responses = [
            [
                'operacion' => 'ACEPTAR_INVITACION',
                'tipo_operacion' => 'UPDATE',
                'descripcion' => 'Aceptación de una invitación a colaborar en un examen',
            ],
            [
                'operacion' => 'RECHAZAR_INVITACION',
                'tipo_operacion' => 'UPDATE',
                'descripcion' => 'Rechazo de una invitación a colaborar en un examen',
            ],
            [
                'operacion' => 'REGISTRAR_ESTUDIANTE_EXCEPCIONAL',
                'tipo_operacion' => 'INSERT',
                'descripcion' => 'Registro de un estudiante excepcional en un examen',
            ],
        ];

        foreach ($responses as $response) {
            DB::table('accion')->updateOrInsert(['operacion' => $response['operacion']], $response);
        }

        // HU-038: se agregan al final para conservar los identificadores 1 a 27.
        foreach ([28 => 'INICIAR_CRONOMETRO', 29 => 'AJUSTAR_CRONOMETRO'] as $id => $operation) {
            DB::table('accion')->updateOrInsert(['id_accion' => $id], [
                'operacion' => $operation,
                'tipo_operacion' => 'UPDATE',
                'descripcion' => $id === 28 ? 'Inicio del cronómetro de un examen' : 'Ajuste del cronómetro de un examen',
            ]);
        }

        DB::statement("SELECT setval(pg_get_serial_sequence('accion', 'id_accion'), (SELECT MAX(id_accion) FROM accion))");
    }
}
