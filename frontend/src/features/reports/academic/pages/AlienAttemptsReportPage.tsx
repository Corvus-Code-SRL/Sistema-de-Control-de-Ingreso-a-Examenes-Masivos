import { useEffect, useState } from 'react'
import { Download, UserX } from 'lucide-react'
import { DataPagination } from '@/components/common/DataPagination'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { cn } from '@/lib/utils'
import { AlienAttemptsCardList } from '../components/AlienAttemptsCardList'
import { AlienAttemptsFilters } from '../components/AlienAttemptsFilters'
import { AlienAttemptsStats } from '../components/AlienAttemptsStats'
import { AlienAttemptsTable } from '../components/AlienAttemptsTable'
import { useAlienAttempts } from '../hooks/useAlienAttempts'
import { useAlienAttemptsFilters } from '../hooks/useAlienAttemptsFilters'
import { useFaculties } from '../hooks/useFaculties'
import { getAlienAttemptsPdf } from '../services/alienAttemptsService'

const RANGE_ERROR = 'La fecha de inicio debe ser anterior a la fecha de fin.'

/**
 * Reporte de estudiantes que intentaron ingresar a un examen ajeno.
 *
 * Solo Administrador. Filtros (rango de fechas y facultad) en la URL. La tabla y las
 * tarjetas muestran el mismo dato; la paginación es del servidor.
 */
export function AlienAttemptsReportPage() {
  const { filters, setRange, setFaculty, setPage } = useAlienAttemptsFilters()
  const { faculties, status: facultiesStatus } = useFaculties()
  const { page, isStale, status, error, reload } = useAlienAttempts(filters)

  // Borrador local del rango: solo se aplica al pulsar «Generar».
  const [desdeDraft, setDesdeDraft] = useState(filters.desde)
  const [hastaDraft, setHastaDraft] = useState(filters.hasta)

  useEffect(() => {
    setDesdeDraft(filters.desde)
    setHastaDraft(filters.hasta)
  }, [filters.desde, filters.hasta])

  const rangeError = desdeDraft > hastaDraft ? RANGE_ERROR : null
  const generating = status === 'loading'

  function handleGenerate() {
    if (rangeError) return
    setRange(desdeDraft, hastaDraft)
  }

  async function handleDownloadPdf() {
    try {
      const blob = await getAlienAttemptsPdf({
        desde: filters.desde,
        hasta: filters.hasta,
        facultad: filters.facultad,
      })

      const url = URL.createObjectURL(blob)
      const anchor = document.createElement('a')
      anchor.href = url
      anchor.download = `reporte-intentos-ajenos-${filters.desde.replace(/-/g, '')}-${filters.hasta.replace(/-/g, '')}.pdf`
      document.body.appendChild(anchor)
      anchor.click()
      anchor.remove()
      URL.revokeObjectURL(url)
    } catch (cause) {
      // La descarga falló (red, servidor o el navegador no la permite).
      // No es un error de la vista: el usuario puede reintentar.
      console.warn('No se pudo descargar el PDF del reporte.', cause)
    }
  }

  const settled = page !== null && !isStale && status !== 'error'
  const hasRows = page !== null && page.items.length > 0 && status !== 'error'

  return (
    <AppShell
      mobileTitle="Intentos a exámenes ajenos"
      breadcrumbs={[{ label: 'Supervisión' }, { label: 'Reportes' }]}
    >
      <PageHeader
        title="Intentos de ingreso a exámenes ajenos"
        subtitle="Estudiantes que intentaron ingresar a un examen que no les corresponde."
        actions={
          <Button variant="outline" onClick={handleDownloadPdf} disabled={!settled}>
            <Download className="size-4" aria-hidden="true" />
            Descargar PDF
          </Button>
        }
      />

      <div className="space-y-4">
        <AlienAttemptsFilters
          desde={desdeDraft}
          hasta={hastaDraft}
          faculty={filters.facultad}
          faculties={faculties}
          facultiesUnavailable={facultiesStatus === 'error'}
          rangeError={rangeError}
          onDesdeChange={setDesdeDraft}
          onHastaChange={setHastaDraft}
          onFacultyChange={setFaculty}
          onGenerate={handleGenerate}
          generating={generating}
        />

        <AlienAttemptsStats stats={page?.stats ?? null} />

        <Card className="overflow-hidden p-0" aria-busy={status === 'loading' || isStale}>
          {page === null && status === 'loading' && <LoadingState rows={6} label="Cargando reporte" />}

          {status === 'error' && error && <ErrorState error={error} onRetry={reload} />}

          {settled && page.items.length === 0 && (
            <EmptyState
              icon={UserX}
              title="Sin intentos en este rango"
              description="No hubo intentos de ingreso a exámenes ajenos en este rango."
            />
          )}

          {hasRows && (
            <div className={cn(isStale && 'opacity-60 transition-opacity')}>
              <div className="hidden md:block">
                <AlienAttemptsTable records={page.items} />
              </div>

              <AlienAttemptsCardList records={page.items} />

              <DataPagination
                page={page.page}
                perPage={page.perPage}
                total={page.total}
                totalPages={page.totalPages}
                onPageChange={(next) => setPage(next)}
                itemLabel="estudiantes"
                summaryPrefix=""
                disabled={isStale}
              />
            </div>
          )}
        </Card>
      </div>
    </AppShell>
  )
}
