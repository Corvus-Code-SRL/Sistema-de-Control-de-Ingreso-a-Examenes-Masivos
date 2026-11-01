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
    cod_sis: string;
    ci: string | null;
    nombre_completo: string;
    estado_ingreso: EntryState;
    hora_ingreso: string | null;
    id_ambiente: number | null;
    ambiente: string | null;
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
    total: number;
    incidencias_pendientes: number;
    };
    estudiantes: InProgressStudent[];
}
