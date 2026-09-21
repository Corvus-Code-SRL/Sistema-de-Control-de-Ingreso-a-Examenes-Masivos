import { useState, useCallback } from 'react';
import { ApiError } from '@/lib/api-client';
import { useExamFormOptions } from './useExamFormOptions';
import { useExamFormState } from './useExamFormState';
import { validateExamForm } from '../utils/examValidators';
import { examsService } from '../services/examsService';
import { CreateExamFormData, Exam } from '../types/exams.types';

/**
 * Crea el examen en dos pasos cuando hace falta: si el backend advierte nombre
 * duplicado o superposición de horario/ambiente (409), las advertencias se muestran
 * y el docente decide si confirma el envío.
 */
export function useCreateExam(onSuccess?: (exam: Exam) => void) {
  const { options, loading: loadingOptions, error: optionsError } = useExamFormOptions();
  const { formData, updateFormData: setFields, resetFormData } = useExamFormState();

  const [submitting, setSubmitting] = useState<boolean>(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [warnings, setWarnings] = useState<string[]>([]);

  const validateForm = useCallback(() => {
    return validateExamForm(formData);
  }, [formData]);

  // Cualquier cambio invalida las advertencias: se calcularon para los datos anteriores.
  const updateFormData = useCallback(
    (fields: Partial<CreateExamFormData>) => {
      setWarnings([]);
      setFields(fields);
    },
    [setFields]
  );

  const submitExam = async (confirmWarnings = false) => {
    const validation = validateForm();
    if (!validation.isValid || !formData.materia) {
      setSubmitError('Por favor complete todos los campos obligatorios del formulario.');
      return;
    }

    setSubmitting(true);
    setSubmitError(null);

    try {
      const exam = await examsService.createExam({
        nombre_examen: formData.nombre_examen,
        id_carrera: formData.materia.id_carrera,
        id_materia: formData.materia.id_materia,
        categoria: formData.categoria,
        fecha: formData.fecha,
        hora_inicio: formData.hora_inicio,
        duracion: Number(formData.duracion),
        ambientes: formData.ambientes,
        normas: formData.normas || undefined,
        confirmar_advertencias: confirmWarnings || undefined,
      });

      setWarnings([]);
      onSuccess?.(exam);
    } catch (err: unknown) {
      if (err instanceof ApiError && err.status === 409) {
        setWarnings(Object.values(err.errors).flat());
      } else {
        const message = err instanceof Error ? err.message : 'Ocurrió un error al registrar el examen';
        setSubmitError(message);
      }
    } finally {
      setSubmitting(false);
    }
  };

  return {
    formData,
    options,
    loading: loadingOptions,
    submitting,
    apiError: submitError || optionsError,
    warnings,
    updateFormData,
    resetFormData,
    validateForm,
    submitExam,
  };
}
