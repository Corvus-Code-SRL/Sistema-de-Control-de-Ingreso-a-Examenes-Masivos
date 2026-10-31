export type EntryState = 'INGRESO' | 'CON_RETRASO' | 'NO_INGRESO';

export interface InProgressStudent {
    id_estudiante: number;
    cod_sis: string;
    ci: string | null;
    nombre_completo: string;
    estado_ingreso: EntryState;
    hora_ingreso: string | null;
    expulsado_en?: string | null;
    total_incidencias?: number;
    id_ambiente: number | null;
    ambiente: string | null;
    /** Resumen completo, independiente de la paginación del historial. */
    situaciones?: {
        identidad_observada: boolean;
        ultimo_cambio_ambiente: null | {
            id_situacion: number;
            registrado_en: string;
            ambiente_origen: { id_ambiente: number; nro_aula: string };
            ambiente_destino: { id_ambiente: number; nro_aula: string };
        };
    };
}

export interface ExamInProgress {
    id_examen: number;
    nombre_examen: string;
    materia: string | null;
    estado: 'EN_CURSO' | 'FINALIZADO';
    rol_controlador: 'DOCENTE' | 'AUXILIAR';
    id_ambiente_asignado: number | null;
    ambientes: {
    id_ambiente: number;
    nro_aula: string;
    capacidad: number;
    }[];
    inicio_real_en: string | null;
    fin_real_en: string | null;
    inicio_automatico: boolean;
    fin_automatico: boolean;
    fin_programado_en: string;
    hora_servidor_en: string;
    totales: {
    a_tiempo: number;
    con_retraso: number;
    ausentes: number;
    presentes: number;
    expulsados?: number;
    total: number;
    incidencias_pendientes: number;
    };
    estudiantes: InProgressStudent[];
    situaciones_habilitadas?: boolean;
    incidencias_habilitadas?: boolean;
    total_situaciones?: number;
}