export type EntryState = 'INGRESO' | 'CON_RETRASO' | 'NO_INGRESO';

/** Fila de los listados de exámenes EN_CURSO del docente o del auxiliar. */
export interface InProgressExamRow {
    id: number;
    title: string;
    subject: string | null;
    date: string | null;
    startsAt: string | null;
    endsAt: string | null;
    rooms: string;
}

export interface InProgressStudent {
    id_estudiante: number;
    /** Grupo por el que figura en la nómina; la tabla lo usa si la lista va separada por grupo (HU-027). */
    id_grupo?: number | null;
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

/** Grupo del examen con su docente (HU-027). */
export interface InProgressGroup {
    id_grupo: number;
    num_grupo: string;
    docente: string | null;
}

export interface ExamInProgress {
    id_examen: number;
    nombre_examen: string;
    materia: string | null;
    estado: 'PROGRAMADO' | 'EN_CURSO' | 'FINALIZADO';
    rol_controlador: 'DOCENTE' | 'AUXILIAR';
    id_ambiente_asignado: number | null;
    ambientes: {
    id_ambiente: number;
    nro_aula: string;
    capacidad: number;
    }[];
    inicio_programado: string;
    ingreso_abierto_en: string | null;
    ingreso_ordinario_cerrado_en: string | null;
    fin_real_en: string | null;
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
    /** HU-027: quien invitó pidió la lista separada por grupo; la tabla se divide con `grupos`. */
    lista_por_grupo?: boolean;
    grupos?: InProgressGroup[];
    situaciones_habilitadas?: boolean;
    incidencias_habilitadas?: boolean;
    total_situaciones?: number;
}
