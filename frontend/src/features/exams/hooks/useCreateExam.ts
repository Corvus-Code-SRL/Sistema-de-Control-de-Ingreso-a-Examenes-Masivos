import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { useExamFormOptions } from './useExamFormOptions'
import { useExamFormState } from './useExamFormState'
import { validateExamForm, validateGroupsStep } from '../utils/examValidators'
import { examsService } from '../services/examsService'
import type { CreateExamFormData, Exam, StepValidationResult } from '../types/exams.types'

/**
 * Crea el examen junto con sus grupos y permite confirmar advertencias de horario.
 */
export function useCreateExam(onSuccess?: (exam: Exam) => void, initialGroupId?: number) {
  const { options, loading: loadingOptions, error: optionsError } = useExamFormOptions()
  const { formData, updateFormData: setFields, resetFormData } = useExamFormState()

  const initializedContext = useRef(false)
  const [contextResetCount, setContextResetCount] = useState(0)
  const initialGroup = options.grupos.find((group) => group.id_grupo === initialGroupId)
  const initialPair = initialGroup && options.materias.find((subject) =>
    subject.id_carrera === initialGroup.id_carrera && subject.id_materia === initialGroup.id_materia
  )
  const contextError = initialGroupId !== undefined && !loadingOptions && !optionsError
    ? !initialGroup || !initialPair
      ? 'El grupo no está disponible entre las opciones actuales del formulario.'
      : !initialGroup.tiene_nomina
        ? 'El grupo no tiene nómina y no puede seleccionarse para un examen.'
        : null
    : null

  useEffect(() => {
    if (
      initialGroupId === undefined || initializedContext.current ||
      loadingOptions || optionsError || contextError
    ) return
    if (!initialGroup || !initialPair) return
    initializedContext.current = true
    // La selección inicial es atómica: cambiar la materia normalmente limpia los grupos.
    setFields({
      materia: { id_carrera: initialGroup.id_carrera, id_materia: initialGroup.id_materia },
      grupos: [initialGroup.id_grupo],
    })
  }, [initialGroupId, initialGroup, initialPair, loadingOptions, optionsError, contextError, setFields, contextResetCount])

  const [submitting, setSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState<string | null>(null)
  const [warnings, setWarnings] = useState<string[]>([])

  const requestInFlightRef = useRef(false)
  const creationCompletedRef = useRef(false)

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
    if (requestInFlightRef.current || creationCompletedRef.current) {
      return
    }

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
    if (requestInFlightRef.current || creationCompletedRef.current) {
      return
    }

    if (warnings.length > 0 && !confirmWarnings) {
      return
    }

    if (confirmWarnings && warnings.length === 0) {
      return
    }

    const validation = validateForm()

    if (!validation.isValid || !formData.materia) {
      setSubmitError(
        'Por favor complete todos los campos obligatorios del formulario.'
      )
      return
    }

    requestInFlightRef.current = true
    setSubmitting(true)
    setSubmitError(null)

    let createdExam: Exam | null = null

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

      creationCompletedRef.current = true
      setWarnings([])
      createdExam = exam
    } catch (error: unknown) {
      if (error instanceof ApiError && error.status === 409) {
        const serverWarnings = Object.values(error.errors)
          .flat()
          .filter(
            (message) =>
              typeof message === 'string' &&
              message.trim().length > 0
          )

        setWarnings(
          serverWarnings.length > 0
            ? serverWarnings
            : [
                error.message ||
                  'El examen requiere confirmación antes de registrarse.',
              ]
        )
      } else {
        setWarnings([])

        const classroomErrors =
          error instanceof ApiError && error.status === 422
            ? error.errors?.ambientes
            : undefined

        const message =
          classroomErrors && classroomErrors.length > 0
            ? classroomErrors.join(' ')
            : error instanceof Error
              ? error.message
              : 'Ocurrió un error al registrar el examen'

        setSubmitError(message)
      }
    } finally {
      requestInFlightRef.current = false
      setSubmitting(false)
    }

    if (createdExam) {
      onSuccess?.(createdExam)
    }
  }

  const dismissWarnings = () => {
    if (requestInFlightRef.current || creationCompletedRef.current) {
      return
    }

    setWarnings([])
  }

  const resetCreateExam = () => {
    if (requestInFlightRef.current) {
      return
    }

    resetFormData()
    if (initialGroupId !== undefined) {
      initializedContext.current = false
      setContextResetCount((count) => count + 1)
    }
    setSubmitError(null)
    setWarnings([])
    creationCompletedRef.current = false
  }

  const totalStudents = selectedGroups.reduce(
    (total, group) => total + group.cantidad_estudiantes,
    0
  )

  return {
    formData,
    contextError,
    options,
    subjectGroups,
    selectedGroups,
    totalStudents,
    loading: loadingOptions,
    submitting,
    apiError: submitError || optionsError,
    warnings,
    updateFormData,
    resetFormData: resetCreateExam,
    dismissWarnings,
    toggleGroup,
    validateForm,
    submitExam,
  }
}
