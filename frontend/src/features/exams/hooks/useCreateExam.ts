import { useState, useCallback } from 'react';
import { useExamFormOptions } from './useExamFormOptions';
import { useExamFormState } from './useExamFormState';
import { validateExamForm } from '../utils/examValidators';
import { examsService } from '../services/examsService';

export function useCreateExam(onSuccess?: () => void) {
  const { options, loading: loadingOptions, error: optionsError } = useExamFormOptions();
  const { formData, updateFormData } = useExamFormState();

  const [submitting, setSubmitting] = useState<boolean>(false);
  const [submitError, setSubmitError] = useState<string | null>(null);

  const validateForm = useCallback(() => {
    return validateExamForm(formData);
  }, [formData]);

  const submitExam = async () => {
    const validation = validateForm();
    if (!validation.isValid) {
      setSubmitError('Por favor complete todos los campos obligatorios del formulario.');
      return;
    }

    setSubmitting(true);
    setSubmitError(null);

    try {
      await examsService.createExam({
        nombre_examen: formData.nombre_examen,
        id_materia: formData.id_materia!,
        categoria: formData.categoria,
        fecha: formData.fecha,
        hora_inicio: formData.hora_inicio,
        duracion: Number(formData.duracion),
        ambientes: formData.ambientes,
        normas: formData.normas || undefined,
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

  return {
    formData,
    options,
    loading: loadingOptions,
    submitting,
    apiError: submitError || optionsError,
    updateFormData,
    validateForm,
    submitExam,
  };
}
