import { Search, SearchX, Users } from 'lucide-react'
import { DataPagination } from '@/components/common/DataPagination'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type { AsyncStatus } from '@/hooks/useAsyncResource'
import type { ApiError } from '@/lib/api-client'
import { cn } from '@/lib/utils'
import type { GroupCareer, GroupStudent, GroupStudentsPage } from '../types/group.types'
import { MAX_SEARCH_LENGTH } from '../utils/studentFilters'

interface GroupStudentsTableProps {
  searchInput: string
  onSearchChange: (value: string) => void
  /** Búsqueda aplicada (la de la URL), no lo que se está escribiendo. */
  query: string
  onClearSearch: () => void
  page: GroupStudentsPage | null
  isStale: boolean
  status: AsyncStatus
  error: ApiError | null
  onRetry: () => void
  onPageChange: (page: number) => void
  /** La página pedida no existe (por ejemplo, tras una búsqueda): se está volviendo a la última. */
  correctingPage: boolean
  /** El grupo admite cargar la nómina (activo, del período vigente y del docente). */
  canUploadRoster?: boolean
  /** Carreras del grupo (HU-021). Con alguna se ofrece el filtro; con más de una, también la columna. */
  careers?: GroupCareer[]
  /** Carrera filtrada; sin ella, todas. */
  careerId?: number
  onCareerChange?: (careerId: number | null) => void
}

/** «Ing. de Sistemas (12)»: la cifra solo si se conoce. */
function careerOptionLabel(name: string, total: number | undefined): string {
  return total === undefined ? name : `${name} (${total})`
}

function surnames(student: GroupStudent): string {
  return [student.apellido_paterno, student.apellido_materno].filter(Boolean).join(' ')
}

/**
 * Estudiantes inscritos en el grupo (HU-020): búsqueda, tabla en escritorio, lista en móvil y
 * paginación de 50 en 50. Solo lee; cargar la nómina es otra vista.
 */
export function GroupStudentsTable({
  searchInput,
  onSearchChange,
  query,
  onClearSearch,
  page,
  isStale,
  status,
  error,
  onRetry,
  onPageChange,
  correctingPage,
  canUploadRoster = false,
  careers = [],
  careerId,
  onCareerChange,
}: GroupStudentsTableProps) {
  const settled = page !== null && !isStale && status !== 'error'
  const hasRows = page !== null && page.items.length > 0 && status !== 'error'
  const isEmptyGroup = settled && page.enrolledTotal === 0 && query === ''
  const noMatches = settled && page.items.length === 0 && !correctingPage && !isEmptyGroup
  // El filtro está siempre que el grupo tenga carreras (una sola: «Todas» y esa); la columna, solo
  // con varias, porque con una sola repetiría lo mismo en cada fila.
  const hasCareers = careers.length > 0
  const isMultiCareer = careers.length > 1
  const selectedCareer = careers.find((career) => career.id_carrera === careerId)
  const totalsByCareer = new Map((page?.careerTotals ?? []).map((row) => [row.id_carrera, row.total]))

  return (
    <section aria-labelledby="group-students-title" className="space-y-3">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 id="group-students-title" className="sciem-h2">
          Estudiantes inscritos
        </h2>

        {!isEmptyGroup && (
          <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
            {hasCareers && onCareerChange && (
              <select
                value={careerId ?? ''}
                onChange={(event) =>
                  onCareerChange(event.target.value === '' ? null : Number(event.target.value))
                }
                aria-label="Filtrar por carrera"
                className="h-11 rounded-md border border-input bg-card px-3 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:w-64"
              >
                <option value="">Todas las carreras</option>
                {careers.map((career) => (
                  <option key={career.id_carrera} value={career.id_carrera}>
                    {careerOptionLabel(career.nombre, totalsByCareer.get(career.id_carrera))}
                  </option>
                ))}
              </select>
            )}

            <div className="relative sm:w-96">
              <Search
                aria-hidden="true"
                className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
              />
              <Input
                type="search"
                value={searchInput}
                maxLength={MAX_SEARCH_LENGTH}
                onChange={(event) => onSearchChange(event.target.value)}
                placeholder="Buscar por nombre o código SIS"
                aria-label="Buscar por apellido, nombre o código SIS"
                className="h-11 pl-8"
              />
            </div>
          </div>
        )}
      </div>

      <Card className="overflow-hidden p-0" aria-busy={status === 'loading' || isStale}>
        {page === null && status === 'loading' && (
          <LoadingState rows={6} label="Cargando los estudiantes del grupo" />
        )}

        {status === 'error' && error && <ErrorState error={error} onRetry={onRetry} />}

        {correctingPage && <LoadingState rows={6} label="Cargando los estudiantes del grupo" />}

        {isEmptyGroup && (
          <EmptyState
            icon={Users}
            title="Este grupo aún no tiene estudiantes"
            description={
              canUploadRoster
                ? 'Cárguela desde «Cargar nómina de estudiantes», en el menú, para verlos aquí.'
                : 'Este grupo ya no admite cargar la nómina.'
            }
          />
        )}

        {noMatches && (
          <EmptyState
            icon={SearchX}
            title={
              query === '' && selectedCareer
                ? `No hay inscritos de ${selectedCareer.nombre}`
                : 'Ningún estudiante coincide con la búsqueda'
            }
            description={
              query === ''
                ? 'Elija otra carrera o vea todas.'
                : `No hay inscritos cuyo nombre o código SIS contenga «${query}»${
                    selectedCareer ? ` en ${selectedCareer.nombre}` : ''
                  }.`
            }
            action={
              query === '' && onCareerChange ? (
                <Button variant="outline" size="sm" onClick={() => onCareerChange(null)}>
                  Ver todas las carreras
                </Button>
              ) : (
                <Button variant="outline" size="sm" onClick={onClearSearch}>
                  Limpiar búsqueda
                </Button>
              )
            }
          />
        )}

        {hasRows && (
          <div className={cn(isStale && 'opacity-60 transition-opacity')}>
            <div className="hidden md:block">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="w-40">Código SIS</TableHead>
                    <TableHead>Apellidos</TableHead>
                    <TableHead>Nombres</TableHead>
                    {isMultiCareer && <TableHead>Carrera</TableHead>}
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {page.items.map((student) => (
                    <TableRow key={student.id_estudiante}>
                      <TableCell className="sciem-tnum">{student.cod_sis}</TableCell>
                      <TableCell className="font-medium">{surnames(student)}</TableCell>
                      <TableCell>{student.nombre}</TableCell>
                      {isMultiCareer && (
                        <TableCell className="text-muted-foreground">
                          {student.carrera?.nombre ?? '—'}
                        </TableCell>
                      )}
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>

            <ul aria-label="Estudiantes" className="md:hidden">
              {page.items.map((student) => (
                <li
                  key={student.id_estudiante}
                  className="space-y-0.5 border-b border-border px-4 py-3 last:border-b-0"
                >
                  <p className="text-sm font-medium text-foreground">
                    {surnames(student)}, {student.nombre}
                  </p>
                  <p className="sciem-tnum text-xs text-muted-foreground">
                    SIS {student.cod_sis}
                    {isMultiCareer && student.carrera?.nombre ? ` · ${student.carrera.nombre}` : ''}
                  </p>
                </li>
              ))}
            </ul>

            <DataPagination
              page={page.page}
              perPage={page.perPage}
              total={page.total}
              totalPages={page.totalPages}
              onPageChange={onPageChange}
              itemLabel="estudiantes"
              disabled={isStale}
            />
          </div>
        )}
      </Card>
    </section>
  )
}
