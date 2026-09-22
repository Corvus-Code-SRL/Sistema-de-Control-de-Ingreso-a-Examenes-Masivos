import { useCallback, useEffect, useMemo, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { useExamFormOptions } from './useExamFormOptions'
import { useExamFormState } from './useExamFormState'
import { validateExamForm, validateGroupsStep } from '../utils/examValidators'
import { examsService } from '../services/examsService'
import type { Exam } from '../types/exams.types'

/**
 * Detalle de un examen propio: edición de su información general, gestión de
 * grupos y cancelación (HU-24 criterios 10-12, HU-25 criterio 7).
 */
export function useExamDetail(examId: number) {
  const { options, loading: loadingOptions } = useExamFormOptions()
  const { formData, updateFormData } = useExamFormState()

  const [exam, setExam] = useState<Exam | null>(null)
  const [loading, setLoading] = useState(true)
  const [notFound, setNotFound] = useState(false)
  const [forbidden, setForbidden] = useState(false)
  const [loadError, setLoadError] = useState<ApiError | null>(null)

  const [savingInfo, setSavingInfo] = useState(false)
  const [infoError, setInfoError] = useState<string | null>(null)
  const [infoWarnings, setInfoWarnings] = useState<string[]>([])

  const [savingGroups, setSavingGroups] = useState(false)
  const [groupsError, setGroupsError] = useState<string | null>(null)

  const [cancelling, setCancelling] = useState(false)
  const [cancelError, setCancelError] = useState<string | null>(null)

  const load = useCallback(async () => {
    try {
      setLoading(true)
      setLoadError(null)
      setNotFound(false)
      setForbidden(false)

      const data = await examsService.getExam(examId)
      setExam(data)
      updateFormData({
        nombre_examen: data.nombre_examen,
        materia: { id_carrera: data.id_carrera, id_materia: data.id_materia },
        categoria: data.tipo_examen?.categoria ?? 'REGULAR',
        fecha: data.fecha,
        hora_inicio: data.hora_inicio.slice(0, 5),
        duracion: data.duracion,
        ambientes: (data.ambientes ?? []).map((room) => room.id_ambiente),
        grupos: (data.grupos ?? []).map((group) => group.id_grupo),
        normas: data.normas ?? '',
      })
    } catch (err: unknown) {
      if (err instanceof ApiError && err.isNotFound) {
        setNotFound(true)
      } else if (err instanceof ApiError && err.isForbidden) {
        setForbidden(true)
      } else {
        setLoadError(err instanceof ApiError ? err : new ApiError(0, (err as Error).message))
      }
    } finally {
      setLoading(false)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [examId])

  useEffect(() => {
    load()
  }, [load])

  const subjectGroups = useMemo(() => {
    if (!formData.materia) return []
    return options.grupos.filter(
      (group) =>
        group.id_carrera === formData.materia?.id_carrera &&
        group.id_materia === formData.materia.id_materia
    )
  }, [formData.materia, options.grupos])

  const selectedSubject = options.materias.find(
    (subject) =>
      subject.id_carrera === formData.materia?.id_carrera &&
      subject.id_materia === formData.materia?.id_materia
  )

  const isProgramado = exam?.estado === 'PROGRAMADO'

  const toggleGroup = useCallback(
    (groupId: number) => {
      setGroupsError(null)
      updateFormData({
        grupos: formData.grupos.includes(groupId)
          ? formData.grupos.filter((id) => id !== groupId)
          : [...formData.grupos, groupId],
      })
    },
    [formData.grupos, updateFormData]
  )

  const saveGeneralInfo = async (confirmWarnings = false) => {
    if (!exam || !isProgramado) return

    const validation = validateExamForm(formData)
    if (!validation.isValid || !formData.materia) {
      setInfoError('Por favor complete todos los campos obligatorios del formulario.')
      return
    }

    setSavingInfo(true)
    setInfoError(null)

    try {
      const updated = await examsService.updateExam(examId, {
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
      })
      setExam(updated)
      setInfoWarnings([])
    } catch (err: unknown) {
      if (err instanceof ApiError && err.status === 409) {
        setInfoWarnings(Object.values(err.errors).flat())
      } else {
        setInfoError(err instanceof Error ? err.message : 'Ocurrió un error al actualizar el examen')
      }
    } finally {
      setSavingInfo(false)
    }
  }

  const saveGroups = async () => {
    if (!exam || !isProgramado) return

    const validation = validateGroupsStep(formData, subjectGroups)
    if (!validation.isValid) {
      setGroupsError(Object.values(validation.errors)[0]);
      return
    }

    setSavingGroups(true)
    setGroupsError(null)

    try {
      const updated = await examsService.assignGroups(examId, { grupos: formData.grupos })
      setExam(updated)
    } catch (err: unknown) {
      setGroupsError(err instanceof Error ? err.message : 'Ocurrió un error al guardar los grupos')
    } finally {
      setSavingGroups(false)
    }
  }

  const cancelExam = async () => {
    if (!exam || !isProgramado) return

    setCancelling(true)
    setCancelError(null)

    try {
      const updated = await examsService.cancelExam(examId)
      setExam(updated)
    } catch (err: unknown) {
      setCancelError(err instanceof Error ? err.message : 'Ocurrió un error al cancelar el examen')
    } finally {
      setCancelling(false)
    }
  }

  return {
    exam,
    loading: loading || loadingOptions,
    notFound,
    forbidden,
    loadError,
    reload: load,
    formData,
    updateFormData,
    options,
    subjectGroups,
    selectedSubject,
    isProgramado,
    toggleGroup,
    savingInfo,
    infoError,
    infoWarnings,
    saveGeneralInfo,
    savingGroups,
    groupsError,
    saveGroups,
    cancelling,
    cancelError,
    cancelExam,
  }
}
