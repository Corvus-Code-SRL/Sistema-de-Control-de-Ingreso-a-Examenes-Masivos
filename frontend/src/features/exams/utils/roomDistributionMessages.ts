export const DISTRIBUTION_PATH = '/examenes/distribucion'
export const DISTRIBUTION_EXAM_PARAM = 'examen'

export const DISTRIBUTION_MESSAGES = {
  title: 'Distribuir habilitados',
  subtitle: 'Distribuya a los estudiantes habilitados entre los ambientes del examen y revise los ajustes antes de guardar.',
  roomFull: 'El ambiente ya está lleno',
  saved: 'Distribución guardada',
  saveSuccess: 'La distribución confirmada quedó guardada correctamente.',
  proposalApplied: 'Propuesta aplicada',
  proposalPending: 'Revise los ambientes y pulse «Guardar distribución» para confirmar.',
  unsaved: 'Los ajustes son locales. La distribución anterior se conserva hasta que pulse «Guardar distribución».',
  changed: 'Los datos del examen cambiaron. Recargue la distribución para revisar la nómina y los ambientes actuales.',
  reloadTitle: 'Es necesario revisar la distribución',
  validationTitle: 'Revise la distribución antes de guardar',
  failure: 'No se pudo completar la operación. Intente de nuevo.',
  notFound: 'No se encontró la distribución solicitada. Seleccione otro examen o vuelva a consultar.',
  unassigned: 'Sin ambiente asignado',
  unassignedEditable: 'pueden ingresar por cualquier ambiente del examen. Si desea, ubíquelos ahora y guarde la distribución.',
  examLabel: 'Examen',
  inProgressSuffix: 'En curso',
  stopTitle: 'La distribución sigue activa en el control de ingreso',
  stopDescription: 'Ya no se puede modificar. Si deja de usarla, los estudiantes ingresarán por cualquier ambiente sin aviso.',
  stopAction: 'Dejar de usar la distribución',
  stopped: 'Se dejó de usar la distribución',
  stoppedDescription: 'Los estudiantes pueden ingresar por cualquier ambiente del examen.',
  zero: 'No hay estudiantes habilitados. Puede guardar una distribución vacía para confirmar y limpiar asignaciones anteriores.',
  unassignedCapacity: 'No hay capacidad suficiente para ubicar a todos. Revise los ambientes del examen antes de guardar.',
} as const

export const DISTRIBUTION_CONFIRMATIONS = {
  leave: {
    title: '¿Salir sin guardar?',
    description: 'Se perderán los ajustes locales. La distribución confirmada anteriormente se conservará.',
    confirm: 'Salir sin guardar',
  },
  discard: {
    title: '¿Descartar los cambios?',
    description: 'Se restaurará la última distribución cargada o guardada en esta vista.',
    confirm: 'Descartar cambios',
  },
  overwrite: {
    title: '¿Reemplazar los ajustes actuales?',
    description: 'La propuesta por apellido reemplazará los ajustes locales. Solo se confirmará al guardar.',
    confirm: 'Distribuir por apellido',
  },
  stop: {
    title: '¿Dejar de usar la distribución?',
    description: 'Los estudiantes podrán ingresar por cualquier ambiente del examen, sin avisos de ambiente. Esta acción no se puede deshacer.',
    confirm: 'Dejar de usar',
    cancel: 'Cancelar',
  },
  reload: {
    title: '¿Recargar y revisar?',
    description: 'Se consultará la distribución actual del servidor y se descartarán los ajustes locales cuando se cargue.',
    confirm: 'Recargar distribución',
  },
} as const
