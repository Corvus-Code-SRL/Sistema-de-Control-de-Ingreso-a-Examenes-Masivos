import { useEffect, useRef, useState } from 'react'
import { ApiError } from '@/lib/api-client'
import { incidentService } from '../services/incidentService'
import type {
  IncidentFormErrors, IncidentReportInput, IncidentType, IncidentDialogContext,
} from '../types/incident.types'

const MAX_EVIDENCE_BYTES = 5 * 1024 * 1024
const EVIDENCE_TYPES = ['image/jpeg', 'image/png', 'application/pdf']

export function useReportIncident(context: IncidentDialogContext, onReported: (alreadyReported: boolean) => void) {
  const duringExam = 'origin' in context && context.origin === 'EN_CURSO'
  const [blocked, setBlocked] = useState(false)
  const [types, setTypes] = useState<IncidentType[]>([])
  const [loadingTypes, setLoadingTypes] = useState(true)
  const [catalogError, setCatalogError] = useState<string | null>(null)
  const [catalogVersion, setCatalogVersion] = useState(0)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [errors, setErrors] = useState<IncidentFormErrors>({})
  const sending = useRef(false)
  const mounted = useRef(true)

  useEffect(() => {
    mounted.current = true
    return () => { mounted.current = false }
  }, [])

  useEffect(() => {
    const controller = new AbortController()
    setLoadingTypes(true)
    setCatalogError(null)
    void incidentService.types(controller.signal)
      .then((items) => { if (!controller.signal.aborted) setTypes(items) })
      .catch(() => {
        if (!controller.signal.aborted) setCatalogError('No se pudieron cargar los tipos de falta.')
      })
      .finally(() => { if (!controller.signal.aborted) setLoadingTypes(false) })
    return () => controller.abort()
  }, [catalogVersion])

  async function submit(input: IncidentReportInput) {
    if (blocked || sending.current || loadingTypes || catalogError || types.length === 0) return
    const nextErrors: IncidentFormErrors = {}
    const description = input.description.trim()
    if (!types.some((type) => String(type.id_falta) === input.incidentTypeId)) {
      nextErrors.id_tipo_falta = 'Seleccione un tipo de falta.'
    }
    if (description.length < 10 || description.length > 500) {
      nextErrors.descripcion = 'La descripción debe tener entre 10 y 500 caracteres.'
    }
    if (input.evidence && (
      !EVIDENCE_TYPES.includes(input.evidence.type)
      || input.evidence.size > MAX_EVIDENCE_BYTES
      || input.evidence.size === 0
    )) {
      nextErrors.evidencia = duringExam
        ? 'Formato o tamaño no permitido. Use jpg, png o pdf de hasta 5 MB.'
        : 'Formato o tamaño no permitido'
    }
    setErrors(nextErrors)
    setError(null)
    if (Object.keys(nextErrors).length) return

    sending.current = true
    setBusy(true)
    try {
      if ('origin' in context) {
        await incidentService.reportDuringExam(context.examId, context.student.id_estudiante, context.roomId, input)
      } else {
        await incidentService.report(context.examId, context.attemptId, input)
      }
      if (mounted.current) onReported(false)
    } catch (cause) {
      if (!mounted.current) return
      if (cause instanceof ApiError && !duringExam && cause.status === 422 && cause.errors.id_intento?.length) {
        onReported(true)
        return
      }
      if (cause instanceof ApiError && cause.status === 422) {
        setErrors({
          id_tipo_falta: cause.errors.id_tipo_falta?.[0],
          descripcion: cause.errors.descripcion?.[0],
          evidencia: cause.errors.evidencia?.[0],
        })
        setError('Revise los datos del reporte.')
      } else if (cause instanceof ApiError && cause.isNetworkFailure) {
        setBlocked(duringExam)
        setError(duringExam
          ? 'No se pudo confirmar el envío. Cierre y consulte las incidencias antes de volver a reportar.'
          : 'No se pudo confirmar el envío. Revise los últimos rechazos antes de reintentar.')
      } else if (cause instanceof ApiError && cause.status === 403) {
        setBlocked(duringExam)
        setError(duringExam ? 'No tiene permiso para reportar a este estudiante.' : 'No tiene permiso para reportar este intento.')
      } else if (cause instanceof ApiError && cause.status === 409) {
        setBlocked(duringExam)
        setError(duringExam ? cause.message : 'El control de ingreso ya no está disponible. Actualice la pantalla.')
      } else {
        setBlocked(duringExam)
        setError(duringExam
          ? 'No se pudo confirmar el envío. Cierre y consulte las incidencias antes de volver a reportar.'
          : 'No se pudo enviar el reporte. Intente nuevamente.')
      }
    } finally {
      sending.current = false
      if (mounted.current) setBusy(false)
    }
  }

  return {
    types, loadingTypes, catalogError, busy, blocked, error, errors, submit,
    retryTypes: () => setCatalogVersion((value) => value + 1),
  }
}