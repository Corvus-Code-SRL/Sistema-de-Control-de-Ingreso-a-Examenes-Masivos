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
        ];

        foreach ($acciones as $accion) {
            DB::table('accion')->updateOrInsert(['operacion' => $accion['operacion']], $accion);
        }
    }
}
