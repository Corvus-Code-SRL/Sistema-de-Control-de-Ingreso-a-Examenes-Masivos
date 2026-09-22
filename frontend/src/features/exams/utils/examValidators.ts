import { CreateExamFormData, Group, StepValidationResult } from '../types/exams.types';

/** Menos de un día: el backend guarda la hora de fin como hora del día. */
export const MAX_DURATION_MINUTES = 1439;

function pad(value: number): string {
  return String(value).padStart(2, '0');
}

/**
 * Fecha de hoy en la hora local del navegador. `toISOString()` devolvería la
 * fecha UTC, que en Bolivia ya es "mañana" a partir de las 20:00.
 */
export function localToday(now: Date = new Date()): string {
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

export function validateExamForm(
  formData: CreateExamFormData,
  now: Date = new Date()
): StepValidationResult {
  const errors: Record<string, string> = {};
  const warnings: Record<string, string> = {};

  if (!formData.nombre_examen || !formData.nombre_examen.trim()) {
    errors.nombre_examen = 'El nombre del examen es obligatorio.';
  } else if (formData.nombre_examen.length > 25) {
    errors.nombre_examen = 'El nombre del examen no debe exceder los 25 caracteres.';
  }

  if (!formData.materia) {
    errors.materia = 'La materia es obligatoria. Debe seleccionar una materia válida.';
  }

  if (!formData.fecha) {
    errors.fecha = 'La fecha del examen es obligatoria.';
  }

  if (!formData.hora_inicio) {
    errors.hora_inicio = 'La hora de inicio es obligatoria.';
  }

  if (formData.fecha && formData.hora_inicio) {
    const startsAt = new Date(`${formData.fecha}T${formData.hora_inicio}`);
    if (startsAt.getTime() <= now.getTime()) {
      errors.fecha = 'La fecha y hora del examen no pueden ser anteriores al momento actual.';
    }
  }

  if (
    !Number.isInteger(formData.duracion) ||
    formData.duracion <= 0 ||
    formData.duracion > MAX_DURATION_MINUTES
  ) {
    errors.duracion = 'La duración debe ser un número entero de minutos, mayor a cero y menor a 24 horas.';
  }

  if (!formData.ambientes || formData.ambientes.length === 0) {
    errors.ambientes = 'El ambiente es obligatorio. Debe seleccionar un ambiente para el examen.';
  }

  return {
    isValid: Object.keys(errors).length === 0,
    errors,
    warnings,
  };
}

export function validateGroupsStep(
  formData: CreateExamFormData,
  availableGroups: Group[]
): StepValidationResult {
  const errors: Record<string, string> = {};
  const warnings: Record<string, string> = {};

  if (!formData.grupos || formData.grupos.length === 0) {
    errors.grupos = 'Debe elegir al menos un grupo habilitado para vincular al examen.';
  } else {
    const selectedGroups = availableGroups.filter((group) =>
      formData.grupos.includes(group.id_grupo)
    );

    if (
      selectedGroups.length !== formData.grupos.length ||
      selectedGroups.some((group) => !group.tiene_nomina)
    ) {
      errors.grupos = 'Todos los grupos seleccionados deben pertenecer al par y tener nómina activa.';
    }
  }

  return {
    isValid: Object.keys(errors).length === 0,
    errors,
    warnings,
  };
}
