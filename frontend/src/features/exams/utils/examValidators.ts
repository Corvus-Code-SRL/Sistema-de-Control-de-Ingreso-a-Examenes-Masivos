import { CreateExamFormData, Group, StepValidationResult } from '../types/exams.types';

/**
 * Valida los datos generales del paso 1: nombre, materia, fecha, hora, duración.
 * NO incluye ambientes (se valida en paso 3).
 */
export function validateExamForm(formData: CreateExamFormData): StepValidationResult {
  const errors: Record<string, string> = {};
  const warnings: Record<string, string> = {};

  if (!formData.nombre_examen || !formData.nombre_examen.trim()) {
    errors.nombre_examen = 'El nombre del examen es obligatorio.';
  } else if (formData.nombre_examen.length > 25) {
    errors.nombre_examen = 'El nombre del examen no debe exceder los 25 caracteres.';
  }

  if (!formData.id_materia) {
    errors.id_materia = 'La materia es obligatoria. Debe seleccionar una materia válida.';
  }

  const todayDateString = new Date().toISOString().split('T')[0];
  if (!formData.fecha) {
    errors.fecha = 'La fecha del examen es obligatoria.';
  } else if (formData.fecha < todayDateString) {
    errors.fecha = 'La fecha del examen no puede ser anterior a la fecha actual.';
  }

  if (!formData.hora_inicio) {
    errors.hora_inicio = 'La hora de inicio es obligatoria.';
  }

  // Validar Duración
  if (!formData.duracion || formData.duracion < 30 || isNaN(formData.duracion)) {
    errors.duracion = 'La duración del examen debe ser de al menos 30 minutos.';
  }

  return {
    isValid: Object.keys(errors).length === 0,
    errors,
    warnings,
  };
}

/**
 * Valida la selección de grupos del paso 2.
 */
export function validateGroupsStep(
  formData: CreateExamFormData,
  availableGroups?: Group[]
): StepValidationResult {
  const errors: Record<string, string> = {};
  const warnings: Record<string, string> = {};

  if (!formData.grupos || formData.grupos.length === 0) {
    errors.grupos = 'Debe elegir al menos un grupo habilitado para vincular al examen.';
  } else if (availableGroups && availableGroups.length > 0) {
    // Verificar si alguno de los grupos seleccionados no tiene nómina
    const invalidGroups = availableGroups.filter(
      (g) => formData.grupos.includes(g.id_grupo) && (!g.tiene_nomina && (g.cantidad_estudiantes ?? 0) === 0)
    );

    if (invalidGroups.length > 0) {
      const groupNums = invalidGroups.map((g) => `Grupo ${g.num_grupo}`).join(', ');
      errors.grupos = `Los siguientes grupos no tienen una nómina de estudiantes cargada: ${groupNums}.`;
    }
  }

  return {
    isValid: Object.keys(errors).length === 0,
    errors,
    warnings,
  };
}

/**
 * Valida la selección de ambientes del paso 3.
 */
export function validateClassroomsStep(
  formData: CreateExamFormData
): StepValidationResult {
  const errors: Record<string, string> = {};
  const warnings: Record<string, string> = {};

  if (!formData.ambientes || formData.ambientes.length === 0) {
    errors.ambientes = 'Debe seleccionar al menos un ambiente para el examen.';
  }

  return {
    isValid: Object.keys(errors).length === 0,
    errors,
    warnings,
  };
}
