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

        // HU-038: también al final, después de los anteriores, para no mover los ids existentes.
        $timer = [
            [
                'operacion' => 'INICIAR_CRONOMETRO',
                'tipo_operacion' => 'UPDATE',
                'descripcion' => 'Inicio del cronómetro de un examen',
            ],
            [
                'operacion' => 'AJUSTAR_CRONOMETRO',
                'tipo_operacion' => 'UPDATE',
                'descripcion' => 'Ajuste del cronómetro de un examen',
            ],
        ];

        foreach ($timer as $action) {
            DB::table('accion')->updateOrInsert(['operacion' => $action['operacion']], $action);
        }

        // HU-062: confirmación del docente en la rectificación; al final para no mover los ids existentes.
        $rectification = [
            [
                'operacion' => 'SOLICITAR_RECTIFICACION',
                'tipo_operacion' => 'INSERT',
                'descripcion' => 'Solicitud de rectificación de un registro de la Central de Riesgo',
            ],
            [
                'operacion' => 'ACEPTAR_RECTIFICACION',
                'tipo_operacion' => 'UPDATE',
                'descripcion' => 'Aceptación de una solicitud de rectificación por el docente',
            ],
            [
                'operacion' => 'RECHAZAR_RECTIFICACION',
                'tipo_operacion' => 'UPDATE',
                'descripcion' => 'Rechazo de una solicitud de rectificación por el docente',
            ],
            [
                'operacion' => 'RECTIFICAR_SIN_CONFIRMACION',
                'tipo_operacion' => 'UPDATE',
                'descripcion' => 'Rectificación de un registro sin confirmación por docente inactivo',
            ],
        ];

        foreach ($rectification as $action) {
            DB::table('accion')->updateOrInsert(['operacion' => $action['operacion']], $action);
        }

        DB::table('accion')->updateOrInsert(
            ['operacion' => 'GUARDAR_DISTRIBUCION'],
            [
                'operacion' => 'GUARDAR_DISTRIBUCION',
                'tipo_operacion' => 'UPDATE',
                'descripcion' => 'Guardado de la distribución de habilitados por ambiente',
            ]
        );

        // HU-031: distribución opcional; al final para no mover los ids existentes.
        $optionalDistribution = [
            [
                'operacion' => 'DEJAR_USAR_DISTRIBUCION',
                'tipo_operacion' => 'UPDATE',
                'descripcion' => 'El docente deja de usar la distribución de ambientes del examen',
            ],
            [
                'operacion' => 'INGRESO_CON_AVISO_AMBIENTE',
                'tipo_operacion' => 'UPDATE',
                'descripcion' => 'Ingreso registrado pese a un aviso de ambiente distinto o lleno',
            ],
        ];

        foreach ($optionalDistribution as $action) {
            DB::table('accion')->updateOrInsert(['operacion' => $action['operacion']], $action);
        }

        // HU-008: desvincular una materia de una carrera en el período vigente.
        // Al final para no mover los ids existentes.
        DB::table('accion')->updateOrInsert(
            ['operacion' => 'DESVINCULAR_MATERIA_CARRERA'],
            [
                'operacion' => 'DESVINCULAR_MATERIA_CARRERA',
                'tipo_operacion' => 'UPDATE',
                'descripcion' => 'Desvincular materia de carrera',
            ]
        );

        // HU-008: reactivar una oferta desvinculada. Al final para no mover los ids existentes.
        DB::table('accion')->updateOrInsert(
            ['operacion' => 'REACTIVAR_MATERIA_CARRERA'],
            [
                'operacion' => 'REACTIVAR_MATERIA_CARRERA',
                'tipo_operacion' => 'UPDATE',
                'descripcion' => 'Reactivar materia de carrera',
            ]
        );
    }
}
