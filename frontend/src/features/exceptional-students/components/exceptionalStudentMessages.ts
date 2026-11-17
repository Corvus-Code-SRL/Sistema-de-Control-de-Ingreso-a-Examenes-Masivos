/** Textos de la HU-023, tal como los pide el criterio de aceptación. */
export const EXCEPTIONAL_MESSAGES = {
  emptyTitle: 'No tiene exámenes En curso',
  emptyDescription:
    'Solo puede registrar estudiantes excepcionales mientras su examen está En curso.',
  sisRequired: 'Ingrese el código SIS del estudiante.',
  sisInvalid: 'El código SIS debe tener entre 8 y 9 dígitos.',
  notFound: 'No se encontró a ningún estudiante con este código',
  alreadyInExam: 'Ya figura en la lista de estudiantes de este examen',
  reasonRequired: 'Indique el motivo del registro excepcional',
  reasonTooShort: 'El motivo debe tener al menos 5 caracteres.',
  groupLabel: 'Grupo en el que se contará en el examen',
  groupRequired: 'Elija el grupo en el que se contará al estudiante.',
  careerRequired: 'Elija la carrera del estudiante.',
  roomRequired: 'Elija el ambiente en el que rendirá el examen.',
  roomFull: 'El ambiente está lleno; puede registrar al estudiante igualmente.',
  noCareer: 'Sin carrera registrada',
  registered: 'Estudiante excepcional registrado.',
  searchFailed: 'No se pudo buscar al estudiante. Inténtelo de nuevo.',
  registerFailed: 'No se pudo registrar al estudiante. Inténtelo de nuevo.',
  optionsFailed: 'No se pudieron cargar los grupos, carreras y ambientes del examen.'
}

/** Código SIS de un estudiante: 8 o 9 dígitos. */
export const SIS_PATTERN = /^\d{8,9}$/
