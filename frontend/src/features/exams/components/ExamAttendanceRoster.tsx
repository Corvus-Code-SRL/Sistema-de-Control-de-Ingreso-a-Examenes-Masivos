import { SearchX, Users } from 'lucide-react'
import { useSearchParams } from 'react-router-dom'
import { DataPagination } from '@/components/common/DataPagination'
import { EmptyState } from '@/components/common/EmptyState'
import { FormSelect } from '@/components/common/FormSelect'
import { LoadingState } from '@/components/common/LoadingState'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import type { AttendanceStatus, ExamAttendanceDetail } from '../types/examAttendance.types'
import {
  ATTENDANCE_FILTER_KEYS, ATTENDANCE_SEARCH_MAX_LENGTH, clearAttendanceFilters, isAttendanceStatusForView,
} from '../utils/attendanceFilters'

const STATUS: Record<AttendanceStatus, { label: string; total: keyof ExamAttendanceDetail['totales']; tone: string }> = {
  SIN_REGISTRO: { label: 'Sin registro', total: 'sin_registro', tone: 'bg-neutral-soft text-neutral-fg' },
  A_TIEMPO: { label: 'A tiempo', total: 'a_tiempo', tone: 'bg-ok-soft text-ok-fg' },
  CON_RETRASO: { label: 'Con retraso', total: 'con_retraso', tone: 'bg-warn-soft text-warn-fg' },
  AUSENTE: { label: 'Ausente provisional', total: 'ausentes', tone: 'bg-neutral-soft text-neutral-fg' },
  EXPULSADO: { label: 'Expulsado', total: 'expulsados', tone: 'bg-danger-soft text-danger-fg' },
  NO_HABILITADO: { label: 'No habilitado', total: 'no_habilitados', tone: 'bg-danger-soft text-danger-fg' },
  SIN_CLASIFICAR: { label: 'Sin clasificar', total: 'sin_clasificar', tone: 'bg-neutral-soft text-neutral-fg' },
}
const STATUS_OPTIONS = Object.keys(STATUS) as AttendanceStatus[]

const ALL = 'todos'
const FILTER_CONTENT_CLASS = 'w-(--radix-select-trigger-width) min-w-0 max-w-(--radix-select-content-available-width) [&_[data-slot=select-item]]:wrap-anywhere'

export function ExamAttendanceRoster({ detail, isUpdating }: { detail: ExamAttendanceDetail; isUpdating: boolean }) {
  const [params, setParams] = useSearchParams()
  const { ambientes, carreras, estudiantes, paginacion, totales } = detail
  const canFilterRoom = detail.id_ambiente_asignado === null && ambientes.length > 1
  const withoutRecord = params.get('vista') === 'sin_registro'
  const statusOptions = STATUS_OPTIONS.filter((state) => isAttendanceStatusForView(state, withoutRecord))

  const search = params.get('q') ?? ''
  const career = carreras.find(({ id_carrera }) => String(id_carrera) === params.get('carrera'))?.id_carrera
  const state = statusOptions.find((key) => key === params.get('estado'))
  const room = canFilterRoom
    ? ambientes.find(({ id_ambiente }) => String(id_ambiente) === params.get('ambiente'))?.id_ambiente
    : undefined
  function updateFilter(key: typeof ATTENDANCE_FILTER_KEYS[number] | 'vista' | 'page', value: string) {
    setParams((current) => {
      const next = new URLSearchParams(current)
      if (!value || (value === ALL && key !== 'q')) next.delete(key)
      else next.set(key, value)
      if (key !== 'page') next.delete('page')
      if (key === 'vista') next.delete('estado')
      return next
    }, { replace: key === 'q' })
  }

  function clearFilters() {
    setParams((current) => {
      const next = new URLSearchParams(current)
      clearAttendanceFilters(next)
      return next
    })
  }

  return (
    <>
      <Card>
        <CardContent>
          <dl aria-label="Totales de asistencia" className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div className="rounded-lg bg-muted px-3 py-2">
              <dt className="text-sm text-muted-foreground">Total de estudiantes</dt>
              <dd className="sciem-h3 sciem-tnum">{totales.total}</dd>
            </div>
            {STATUS_OPTIONS.map((key) => (
              <div key={key} className="rounded-lg border px-3 py-2">
                <dt className="text-sm text-muted-foreground">{STATUS[key].label}</dt>
                <dd className="sciem-h3 sciem-tnum">{totales[STATUS[key].total]}</dd>
              </div>
            ))}
          </dl>
        </CardContent>
      </Card>
      <Card>
          <CardContent className="min-w-0 space-y-4">
            <div role="group" aria-label="Vista de estudiantes" className="flex flex-wrap gap-2">
              <Button variant={withoutRecord ? 'outline' : 'default'} aria-pressed={!withoutRecord}
                onClick={() => { if (withoutRecord) updateFilter('vista', '') }}>
                Registrados ({totales.registrados})
              </Button>
              <Button variant={withoutRecord ? 'default' : 'outline'} aria-pressed={withoutRecord}
                onClick={() => { if (!withoutRecord) updateFilter('vista', 'sin_registro') }}>
                Sin registro / Ausentes ({totales.sin_confirmar})
              </Button>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="min-w-0 space-y-2">
                <Label htmlFor="attendance-search">Nombre, SIS o CI</Label>
                <Input id="attendance-search" type="search" value={search} maxLength={ATTENDANCE_SEARCH_MAX_LENGTH}
                  onChange={(event) => updateFilter('q', event.target.value)} />
              </div>
              <div className="min-w-0 space-y-2">
                <Label htmlFor="attendance-career">Carrera</Label>
                <FormSelect id="attendance-career" value={career === undefined ? ALL : String(career)}
                  onValueChange={(value) => updateFilter('carrera', value)} placeholder="Todas las carreras"
                  className="min-w-0 [&_[data-slot=select-value]]:truncate"
                  contentClassName={FILTER_CONTENT_CLASS}
                  options={[{ value: ALL, label: 'Todas las carreras' }, ...carreras.map(({ id_carrera, nombre }) => ({
                    value: String(id_carrera), label: nombre,
                  }))]} />
              </div>
              <div className="min-w-0 space-y-2">
                <Label htmlFor="attendance-status">Estado</Label>
                <FormSelect id="attendance-status" value={state ?? ALL} placeholder="Todos los estados"
                  onValueChange={(value) => updateFilter('estado', value)}
                  contentClassName={FILTER_CONTENT_CLASS}
                  options={[{ value: ALL, label: 'Todos los estados' }, ...statusOptions.map((value) => ({
                    value, label: STATUS[value].label,
                  }))]} />
              </div>
              {canFilterRoom && (
                <div className="min-w-0 space-y-2">
                  <Label htmlFor="attendance-room">Ambiente</Label>
                  <FormSelect id="attendance-room" value={room === undefined ? ALL : String(room)}
                    placeholder="Todos los ambientes" onValueChange={(value) => updateFilter('ambiente', value)}
                    className="min-w-0 [&_[data-slot=select-value]]:truncate"
                    contentClassName={FILTER_CONTENT_CLASS}
                    options={[{ value: ALL, label: 'Todos los ambientes' }, ...ambientes.map((item) => ({
                      value: String(item.id_ambiente), label: item.nro_aula,
                    }))]} />
                </div>
              )}
            </div>
            {ATTENDANCE_FILTER_KEYS.some((key) => params.has(key)) && (
              <Button variant="ghost" onClick={clearFilters}>Limpiar filtros</Button>
            )}
            {isUpdating ? (
              <LoadingState rows={3} label="Cargando resultados" />
            ) : !withoutRecord && totales.registrados === 0 ? (
              <EmptyState icon={Users} title="Todavía no se registraron estudiantes en este examen"
                description="Los ingresos confirmados aparecerán aquí." />
            ) : withoutRecord && totales.sin_confirmar === 0 ? (
              <EmptyState icon={Users} title="No hay estudiantes sin registro"
                description="Consulte la vista de registrados para ver los ingresos confirmados." />
            ) : estudiantes.length === 0 ? (
              <EmptyState icon={SearchX} title="No hay estudiantes que coincidan con los filtros"
                description="Cambie la búsqueda o limpie los filtros para ver más estudiantes." />
            ) : (
              <div role="region" aria-label="Nómina de asistencia" tabIndex={0}
                className="overflow-x-auto rounded-md [&_[data-slot=table-container]]:overflow-visible">
                <Table aria-label="Estudiantes del examen">
                  <TableHeader>
                    <TableRow>
                      <TableHead scope="col">Nombre completo</TableHead>
                      <TableHead scope="col">Código SIS</TableHead>
                      <TableHead scope="col">CI</TableHead>
                      <TableHead scope="col">Carrera</TableHead>
                      <TableHead scope="col">Ambiente</TableHead>
                      <TableHead scope="col">Estado</TableHead>
                      <TableHead scope="col">Hora de registro</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {estudiantes.map((student) => {
                      const info = STATUS[student.estado]
                      return (
                        <TableRow key={student.id_estudiante}>
                          <TableCell className="min-w-36 whitespace-normal wrap-anywhere font-medium">
                            {student.nombre_completo}
                          </TableCell>
                          <TableCell className="sciem-tnum">{student.cod_sis}</TableCell>
                          <TableCell className="sciem-tnum">{student.ci ?? '—'}</TableCell>
                          <TableCell className="min-w-28 whitespace-normal wrap-anywhere">
                            {student.carrera?.nombre ?? '—'}
                          </TableCell>
                          <TableCell>{student.ambiente ?? '—'}</TableCell>
                          <TableCell><Badge variant="secondary" className={info.tone}>{info.label}</Badge></TableCell>
                          <TableCell className="sciem-tnum">{student.hora_ingreso ?? '—'}</TableCell>
                        </TableRow>
                      )
                    })}
                  </TableBody>
                </Table>
              </div>
            )}
          </CardContent>
          {!isUpdating && (
            <DataPagination page={paginacion.current_page} perPage={paginacion.per_page}
              total={paginacion.total} totalPages={paginacion.last_page} itemLabel="estudiantes"
              onPageChange={(page) => updateFilter('page', page === 1 ? '' : String(page))} />
          )}
      </Card>
    </>
  )
}
