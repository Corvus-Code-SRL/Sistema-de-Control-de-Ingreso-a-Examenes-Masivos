import { useState, useCallback } from 'react';
import { useExamFormOptions } from './useExamFormOptions';
import { useExamFormState } from './useExamFormState';
import { validateExamForm, validateGroupsStep, validateClassroomsStep } from '../utils/examValidators';
import { examsService } from '../services/examsService';
import { Group } from '../types/exams.types';

export function useCreateExam(onSuccess?: () => void) {
  const { options, loading: loadingOptions, error: optionsError } = useExamFormOptions();
  const { formData, updateFormData } = useExamFormState();

  const [currentStep, setCurrentStep] = useState<1 | 2 | 3>(1);
  const [subjectGroups, setSubjectGroups] = useState<Group[]>([]);
  const [loadingGroups, setLoadingGroups] = useState<boolean>(false);

  const [submitting, setSubmitting] = useState<boolean>(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [stepErrors, setStepErrors] = useState<Record<string, string>>({});

  // Track which steps have been validated & completed
  const [completedSteps, setCompletedSteps] = useState<Set<number>>(new Set());
  const [errorSteps, setErrorSteps] = useState<Set<number>>(new Set());

  // Helper to clear errors and mark step as completed
  const markStepSuccess = useCallback((stepNum: number) => {
    setStepErrors({});
    setErrorSteps((prev) => {
      const next = new Set(prev);
      next.delete(stepNum);
      return next;
    });
    setCompletedSteps((prev) => new Set([...prev, stepNum]));
    setSubmitError(null);
  }, []);

  // Helper to mark step as having validation errors
  const markStepError = useCallback((stepNum: number, errors: Record<string, string>) => {
    setStepErrors(errors);
    setErrorSteps((prev) => new Set([...prev, stepNum]));
  }, []);

  // Cargar grupos de la materia
  const fetchGroupsForSubject = useCallback(async (subjectId: number) => {
    try {
      setLoadingGroups(true);
      const groups = await examsService.getGroupsBySubject(subjectId);
      setSubjectGroups(groups);

      // Autoseleccionar todos los válidos por defecto
      const validGroupIds = groups
        .filter((g) => g.tiene_nomina || (g.cantidad_estudiantes ?? 0) > 0)
        .map((g) => g.id_grupo);

      updateFormData({ grupos: validGroupIds });
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Error al cargar grupos de la materia';
      setSubmitError(msg);
    } finally {
      setLoadingGroups(false);
    }
  }, [updateFormData]);

  // Paso 1 → Paso 2: Validar datos generales, cargar grupos
  const goToStep2 = async () => {
    const validation = validateExamForm(formData);
    if (!validation.isValid) {
      markStepError(1, validation.errors);
      return;
    }

    markStepSuccess(1);

    // Cargar grupos si aún no los tenemos o si la materia cambió
    if (formData.id_materia) {
      await fetchGroupsForSubject(formData.id_materia);
    }

    setCurrentStep(2);
  };

  // Paso 2 → Paso 3: Validar grupos
  const goToStep3 = () => {
    const validation = validateGroupsStep(formData, subjectGroups);
    if (!validation.isValid) {
      markStepError(2, validation.errors);
      return;
    }

    markStepSuccess(2);
    setCurrentStep(3);
  };

  // Navegar a un paso ya completado
  const goToStep = (step: 1 | 2 | 3) => {
    if (step < currentStep || completedSteps.has(step)) {
      setCurrentStep(step);
      setStepErrors({});
      setSubmitError(null);
    }
  };

  // Retroceder un paso
  const goBack = () => {
    if (currentStep === 2) {
      setCurrentStep(1);
    } else if (currentStep === 3) {
      setCurrentStep(2);
    }
    setStepErrors({});
    setSubmitError(null);
  };

  const toggleGroup = useCallback((groupId: number) => {
    updateFormData({
      grupos: formData.grupos.includes(groupId)
        ? formData.grupos.filter((id) => id !== groupId)
        : [...formData.grupos, groupId],
    });
  }, [formData.grupos, updateFormData]);

  const toggleClassroom = useCallback((classroomId: number) => {
    updateFormData({
      ambientes: formData.ambientes.includes(classroomId)
        ? formData.ambientes.filter((id) => id !== classroomId)
        : [...formData.ambientes, classroomId],
    });
  }, [formData.ambientes, updateFormData]);

  // Confirmar: validar ambientes + crear examen + asignar grupos
  const confirmAndCreate = async () => {
    const classroomValidation = validateClassroomsStep(formData);
    if (!classroomValidation.isValid) {
      markStepError(3, classroomValidation.errors);
      return;
    }

    markStepSuccess(3);
    setSubmitting(true);

    try {
      // 1. Crear examen
      const createdExam = await examsService.createExam({
        nombre_examen: formData.nombre_examen,
        id_materia: formData.id_materia!,
        categoria: formData.categoria,
        fecha: formData.fecha,
        hora_inicio: formData.hora_inicio,
        duracion: Number(formData.duracion),
        ambientes: formData.ambientes,
        normas: formData.normas || undefined,
      });

      // 2. Asignar grupos
      await examsService.assignGroups(createdExam.id_examen, {
        grupos: formData.grupos,
      });

      if (onSuccess) {
        onSuccess();
      }
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Ocurrió un error al registrar el examen';
      setSubmitError(message);
    } finally {
      setSubmitting(false);
    }
  };

  // Obtener los grupos seleccionados como objetos
  const selectedGroups = subjectGroups.filter((g) => formData.grupos.includes(g.id_grupo));

  // Calcular total de estudiantes seleccionados
  const totalStudents = selectedGroups.reduce(
    (acc, g) => acc + (g.cantidad_estudiantes ?? g.inscritos_count ?? 0),
    0,
  );

  return {
    currentStep,
    formData,
    options,
    subjectGroups,
    selectedGroups,
    totalStudents,
    loadingGroups,
    loading: loadingOptions,
    submitting,
    apiError: submitError || optionsError,
    stepErrors,
    completedSteps,
    errorSteps,
    updateFormData,
    goToStep2,
    goToStep3,
    goToStep,
    goBack,
    toggleGroup,
    toggleClassroom,
    confirmAndCreate,
  };
}
