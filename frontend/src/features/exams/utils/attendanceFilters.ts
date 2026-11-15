import { ATTENDANCE_STATUSES, type AttendanceStatus } from '../types/examAttendance.types'

export const ATTENDANCE_FILTER_KEYS = ['q', 'carrera', 'estado', 'ambiente'] as const
export const ATTENDANCE_SEARCH_MAX_LENGTH = 100

export function clearAttendanceFilters(params: URLSearchParams) {
  ATTENDANCE_FILTER_KEYS.forEach((key) => params.delete(key))
  params.delete('page')
}

export function isAttendanceStatusForView(status: AttendanceStatus, withoutRecord: boolean) {
  return status === 'EXPULSADO' || (status === 'A_TIEMPO' || status === 'CON_RETRASO') !== withoutRecord
}

function boundedId(value: string | null, max: number) {
  if (value && /^[1-9]\d*$/.test(value) && Number(value) <= max) return value
  return undefined
}

export function attendanceQuery(params: URLSearchParams, canFilterRoom: boolean) {
  const search = new URLSearchParams()
  const q = params.get('q')?.trim().slice(0, ATTENDANCE_SEARCH_MAX_LENGTH)
  const career = boundedId(params.get('carrera'), 2147483647)
  const room = boundedId(params.get('ambiente'), 2147483647)
  const state = ATTENDANCE_STATUSES.find((value) => value === params.get('estado'))
  const page = boundedId(params.get('page'), 100000)
  const withoutRecord = params.get('vista') === 'sin_registro'

  if (q) search.set('q', q)
  if (career) search.set('carrera', career)
  if (canFilterRoom && room) search.set('ambiente', room)
  if (state && isAttendanceStatusForView(state, withoutRecord)) search.set('estado', state)
  if (page && page !== '1') search.set('page', page)
  if (withoutRecord) search.set('vista', 'sin_registro')

  return search.toString()
}
