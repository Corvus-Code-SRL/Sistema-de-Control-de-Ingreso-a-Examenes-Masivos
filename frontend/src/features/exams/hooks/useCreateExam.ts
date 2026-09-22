import { useCallback, useMemo, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { useExamFormOptions } from './useExamFormOptions'
import { useExamFormState } from './useExamFormState'
import { validateExamForm, validateGroupsStep } from '../utils/examValidators'
import { examsService } from '../services/examsService'
import type { CreateExamFormData, Exam, StepValidationResult } from '../types/exams.types'

/**
 * Crea el examen junto con sus grupos y permite confirmar advertencias de horario.
 */
export function useCreateExam(onSuccess?: (exam: Exam) => void) {
  const { options, loading: loadingOptions, error: optionsError } = useExamFormOptions()
  const { formData, updateFormData: setFields, resetFormData } = useExamFormState()

  const [submitting, setSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState<string | null>(null)
  const [warnings, setWarnings] = useState<string[]>([])

  const subjectGroups = useMemo(() => {
    if (!formData.materia) {
      return []
    }

    return options.grupos.filter(
      (group) =>
        group.id_carrera === formData.materia?.id_carrera &&
        group.id_materia === formData.materia.id_materia
    )
  }, [formData.materia, options.grupos])

  const selectedGroups = useMemo(
    () => subjectGroups.filter((group) => formData.grupos.includes(group.id_grupo)),
    [formData.grupos, subjectGroups]
  )

  const validateForm = useCallback((): StepValidationResult => {
    const examValidation = validateExamForm(formData)
    const groupValidation = validateGroupsStep(formData, subjectGroups)
    const errors = { ...examValidation.errors, ...groupValidation.errors }

    return {
      isValid: Object.keys(errors).length === 0,
      errors,
      warnings: { ...examValidation.warnings, ...groupValidation.warnings },
    }
  }, [formData, subjectGroups])

  const updateFormData = useCallback(
    (fields: Partial<CreateExamFormData>) => {
      setWarnings([])
      setSubmitError(null)

      if (fields.materia !== undefined) {
        const previous = formData.materia
        const next = fields.materia
        const changed =
          previous?.id_carrera !== next?.id_carrera ||
          previous?.id_materia !== next?.id_materia

        setFields(changed ? { ...fields, grupos: [] } : fields)
        return
      }

      setFields(fields)
    },
    [formData.materia, setFields]
  )

  const toggleGroup = useCallback(
    (groupId: number) => {
      updateFormData({
        grupos: formData.grupos.includes(groupId)
          ? formData.grupos.filter((id) => id !== groupId)
          : [...formData.grupos, groupId],
      })
    },
    [formData.grupos, updateFormData]
  )

  const submitExam = async (confirmWarnings = false) => {
    const validation = validateForm()

    if (!validation.isValid || !formData.materia) {
      setSubmitError('Por favor complete todos los campos obligatorios del formulario.')
      return
    }

    setSubmitting(true)
    setSubmitError(null)

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
        grupos: formData.grupos,
        normas: formData.normas || undefined,
        confirmar_advertencias: confirmWarnings || undefined,
      })

      setWarnings([])
      onSuccess?.(exam)
    } catch (error: unknown) {
      if (error instanceof ApiError && error.status === 409) {
        setWarnings(Object.values(error.errors).flat())
      } else {
        const message =
          error instanceof Error ? error.message : 'Ocurrió un error al registrar el examen'
        setSubmitError(message)
      }
    } finally {
      setSubmitting(false)
    }
  }

  const totalStudents = selectedGroups.reduce(
    (total, group) => total + group.cantidad_estudiantes,
    0
  )

  return {
    formData,
    options,
    subjectGroups,
    selectedGroups,
    totalStudents,
    loading: loadingOptions,
    submitting,
    apiError: submitError || optionsError,
    warnings,
    updateFormData,
    resetFormData,
    toggleGroup,
    validateForm,
    submitExam,
  }
}
