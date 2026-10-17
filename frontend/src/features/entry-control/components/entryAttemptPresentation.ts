const rejectionLabels: Record<string, string> = {
  NO_ENCONTRADO: 'Estudiante no encontrado',
  NO_PERTENECE: 'No pertenece al examen',
  NO_HABILITADO: 'No está habilitado para este examen',
  DUPLICADO: 'El estudiante ya registró su ingreso',
  AULA_INCORRECTA: 'El estudiante debe ingresar por otro ambiente',
  DOCUMENTO_NO_VALIDO: 'Documento no válido',
  IDENTIDAD_DUDOSA: 'Identidad dudosa o posible suplantación',
  DECISION_CONTROLADOR: 'Rechazo por decisión del controlador',
  OTRO: 'Otro motivo',
}

export function rejectionLabel(reason: string): string {
  return rejectionLabels[reason] ?? reason.replace(/_/g, ' ')
}

export function attemptTime(value?: string | null): string {
  if (!value) return '—'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? '—' : date.toLocaleTimeString('es-BO', { hour: '2-digit', minute: '2-digit' })
}