import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { ApiError } from '@/lib/api-client'
import { eligibleListService } from '../services/eligibleListService'
import type { EligibleListData, ExamSelectorItem } from '../types/eligibleList.types'

export const EXAM_QUERY_PARAM = 'examen'

const STORAGE_KEY = 'sciem.eligibleList.selectedExamId'

const NETWORK_FALLBACK = 'No se pudo conectar con el servidor. Revise su conexión.'

function parseExamId(value: string | null): number | null {
  if (value === null) return null
  const parsed = Number(value)
  return Number.isInteger(parsed) && parsed > 0 ? parsed : null
}

function readStoredExamId(): number | null {
  if (typeof window === 'undefined') return null
  return parseExamId(window.sessionStorage.getItem(STORAGE_KEY))
}

function writeStoredExamId(examId: number | null): void {
  if (typeof window === 'undefined') return
  if (examId === null) {
    window.sessionStorage.removeItem(STORAGE_KEY)
  } else {
    window.sessionStorage.setItem(STORAGE_KEY, String(examId))
  }
}

export interface UseEligibleListResult {
  exams: ExamSelectorItem[]
  selectedExamId: number | null
  selectedExam: ExamSelectorItem | null
  listData: EligibleListData | null
  isLoadingExams: boolean
  isLoadingList: boolean
  error: ApiError | null
  selectExam: (examId: number) => void
  reloadList: () => Promise<void>
  reloadExams: () => Promise<void>
}

export function useEligibleList(): UseEligibleListResult {
  const [searchParams, setSearchParams] = useSearchParams()
  const examIdFromUrl = parseExamId(searchParams.get(EXAM_QUERY_PARAM))

  const [exams, setExams] = useState<ExamSelectorItem[]>([])
  const [selectedExamId, setSelectedExamId] = useState<number | null>(
    examIdFromUrl ?? readStoredExamId()
  )
  const [listData, setListData] = useState<EligibleListData | null>(null)
  const [isLoadingExams, setIsLoadingExams] = useState(true)
  const [isLoadingList, setIsLoadingList] = useState(false)
  const [error, setError] = useState<ApiError | null>(null)

  const listAbortRef = useRef<AbortController | null>(null)
  const mountedRef = useRef(true)

  useEffect(() => {
    mountedRef.current = true
    return () => {
      mountedRef.current = false
      listAbortRef.current?.abort()
    }
  }, [])

  // Cargar exámenes al montar
  useEffect(() => {
    const controller = new AbortController()

    eligibleListService
      .getExams(controller.signal)
      .then((data) => {
        if (mountedRef.current && !controller.signal.aborted) {
          setExams(data)
          setError(null)
          setIsLoadingExams(false)
        }
      })
      .catch((cause) => {
        if (mountedRef.current && !controller.signal.aborted) {
          setError(toApiError(cause))
          setIsLoadingExams(false)
        }
      })

    return () => controller.abort()
  }, [])

  // Sincronizar el examen seleccionado con la lista cargada.
  // - Si la URL trae uno válido y está en la lista, se respeta.
  // - Si no hay URL pero sessionStorage tiene uno válido, se usa ese.
  // - Si ninguno es válido, se elige el primero y se escribe en la URL.
  useEffect(() => {
    if (isLoadingExams) return

    if (selectedExamId !== null && exams.some((e) => e.id_examen === selectedExamId)) {
      // El seleccionado es válido: recordarlo para la próxima visita sin query.
      writeStoredExamId(selectedExamId)
      return
    }

    const fallback = exams[0]?.id_examen ?? null
    if (fallback === selectedExamId) return

    setSelectedExamId(fallback)
    writeStoredExamId(fallback)

    setSearchParams(
      (prev) => {
        const next = new URLSearchParams(prev)
        if (fallback === null) {
          next.delete(EXAM_QUERY_PARAM)
        } else {
          next.set(EXAM_QUERY_PARAM, String(fallback))
        }
        return next
      },
      { replace: true }
    )
  }, [isLoadingExams, selectedExamId, exams, setSearchParams])

  const loadList = useCallback(async (examId: number): Promise<void> => {
    listAbortRef.current?.abort()
    const controller = new AbortController()
    listAbortRef.current = controller

    setIsLoadingList(true)
    setError(null)

    try {
      const data = await eligibleListService.getEligibleList(examId, controller.signal)
      if (mountedRef.current && !controller.signal.aborted) {
        // Un 200 con un body inesperado (o el handler de pruebas devolviendo
        // { data: [] }) no debe dejar listData en un shape que rompa el render.
        if (data && Array.isArray((data as { estudiantes?: unknown }).estudiantes)) {
          setListData(data)
        } else {
          setListData(null)
        }
      }
    } catch (cause) {
      if (mountedRef.current && !controller.signal.aborted) {
        setError(toApiError(cause))
        setListData(null)
      }
    } finally {
      if (mountedRef.current && !controller.signal.aborted) {
        setIsLoadingList(false)
      }
    }
  }, [])

  const selectExam = useCallback(
    (examId: number): void => {
      if (examId === selectedExamId) return
      setSelectedExamId(examId)
      writeStoredExamId(examId)
      setSearchParams(
        (prev) => {
          const next = new URLSearchParams(prev)
          next.set(EXAM_QUERY_PARAM, String(examId))
          return next
        },
        { replace: true }
      )
      void loadList(examId)
    },
    [selectedExamId, loadList, setSearchParams]
  )

  // Cargar la lista cuando el examId seleccionado cambia por otras vías (URL, fallback).
  useEffect(() => {
    if (selectedExamId === null) return
    void loadList(selectedExamId)
  }, [selectedExamId, loadList])

  const reloadList = useCallback(async (): Promise<void> => {
    if (selectedExamId !== null) {
      await loadList(selectedExamId)
    }
  }, [selectedExamId, loadList])

  const reloadExams = useCallback(async (): Promise<void> => {
    setIsLoadingExams(true)
    setError(null)
    try {
      const data = await eligibleListService.getExams()
      if (mountedRef.current) {
        setExams(data)
        setIsLoadingExams(false)
      }
    } catch (cause) {
      if (mountedRef.current) {
        setError(toApiError(cause))
        setIsLoadingExams(false)
      }
    }
  }, [])

  const selectedExam = useMemo(
    () => exams.find((e) => e.id_examen === selectedExamId) ?? null,
    [exams, selectedExamId]
  )

  return {
    exams,
    selectedExamId,
    selectedExam,
    listData,
    isLoadingExams,
    isLoadingList,
    error,
    selectExam,
    reloadList,
    reloadExams,
  }
}

function toApiError(cause: unknown): ApiError {
  return cause instanceof ApiError ? cause : new ApiError(0, NETWORK_FALLBACK)
}
