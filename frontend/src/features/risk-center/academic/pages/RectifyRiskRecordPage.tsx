import { useState } from 'react'
import { Navigate, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { CheckCircle2, SearchX } from 'lucide-react'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { AppShell } from '@/components/layout/AppShell'
import { Card } from '@/components/ui/card'
import { RectifyRecordPanel } from '../components/RectifyRecordPanel'
import { RectifyStudentMatches } from '../components/RectifyStudentMatches'
import { RectifyStudentRecords } from '../components/RectifyStudentRecords'
import { RectifyStudentSearch } from '../components/RectifyStudentSearch'
import { useRectifiableStudentSearch } from '../hooks/useRectifiableStudentSearch'
import type { RectifiableRecordDetail, SentRectificationRequest } from '../types/riskRectification.types'

const BASE_PATH = '/rectificar-registro'

/** Un id de la URL como entero positivo de la base; null si no hay o no lo es. */
function idFrom(param: string | null | undefined): number | null {
  if (param === null || param === undefined || !/^[1-9]\d{0,8}$/.test(param)) return null

  return Number(param)
}

/**
 * Rectificar un registro de la Central de Riesgo (HU-062), solo para quien tiene `riesgo.rectificar`.
 *
 * Rectificar es pedir que se retire de la Central un registro aprobado por error: el Administrador envía una
 * solicitud al docente que lo aprobó y el registro sigue activo hasta que este la acepte (si ese docente ya no
 * es docente activo y pasó el plazo, se anula directamente). No se edita nada del registro. Tres
 * pasos que viven en la URL: buscar al estudiante (`/rectificar-registro`), ver sus registros activos
 * (`/rectificar-registro/:id`) y abrir uno para rectificar (`?registro=`). Si la búsqueda da un solo
 * estudiante se abre directo; si da varios, se elige. Al enviar la solicitud se vuelve a la lista con un aviso y
 * el registro queda esperando al docente; al anular sin confirmación pasa a «Registros rectificados».
 */
export function RectifyRiskRecordPage() {
  const { id: idParam } = useParams<{ id: string }>()
  const [searchParams, setSearchParams] = useSearchParams()
  const navigate = useNavigate()
  const { status, result, error, search, reset } = useRectifiableStudentSearch()
  const [notice, setNotice] = useState<string | null>(null)

  const studentId = idFrom(idParam)
  const recordParam = searchParams.get('registro')
  const recordId = idFrom(recordParam)

  if (idParam !== undefined && studentId === null) {
    return <Navigate to={BASE_PATH} replace />
  }

  async function handleSearch(term: string) {
    setNotice(null)

    const found = await search(term)

    if (found !== null && found.students.length === 1) {
      navigate(`${BASE_PATH}/${found.students[0].id_estudiante}`)
    }
  }

  const goToSearch = () => {
    reset()
    setNotice(null)
    navigate(BASE_PATH)
  }

  function handleRectified(record: RectifiableRecordDetail) {
    setNotice(
      `Registro rectificado: «${record.tipo_falta.nombre}» de ${record.estudiante.nombre_completo} ya no figura en la Central de Riesgo.`
    )
    setSearchParams({})
  }

  function handleRequested(record: RectifiableRecordDetail, request: SentRectificationRequest) {
    setNotice(
      `Solicitud enviada a ${request.docente.nombre_completo ?? 'el docente que aprobó el reporte'}: el registro «${record.tipo_falta.nombre}» de ${record.estudiante.nombre_completo} sigue activo hasta que el docente la confirme.`
    )
    setSearchParams({})
  }

  return (
    <AppShell
      mobileTitle="Rectificar registro"
      breadcrumbs={[{ label: 'Central de riesgo' }, { label: 'Rectificar registro' }]}
    >
      <PageHeader
        title="Rectificar registro de la central"
        subtitle="Solicite al docente que aprobó el reporte retirar de la Central un registro aprobado por error. El registro sigue activo hasta que el docente lo confirme; todo queda en la bitácora."
      />

      <div className="space-y-4">
        {studentId === null && (
          <>
            <Card className="p-5">
              <RectifyStudentSearch onSearch={handleSearch} isSearching={status === 'loading'} />
            </Card>

            {status === 'loading' && (
              <Card className="p-0">
                <LoadingState rows={3} label="Buscando estudiantes" />
              </Card>
            )}

            {status === 'error' && error !== null && (
              <Card className="p-0">
                <ErrorState error={error} />
              </Card>
            )}

            {status === 'success' && result !== null && result.students.length === 0 && (
              <Card className="p-0">
                <EmptyState
                  icon={SearchX}
                  title="No se encontró ningún estudiante con ese dato"
                  description="Revise el código SIS, el carnet o el nombre e intente otra vez."
                />
              </Card>
            )}

            {status === 'success' && result !== null && result.students.length > 1 && (
              <Card className="p-5">
                <RectifyStudentMatches
                  students={result.students}
                  limit={result.limit}
                  hasMore={result.hasMore}
                  onSelect={(student) => navigate(`${BASE_PATH}/${student.id_estudiante}`)}
                />
              </Card>
            )}
          </>
        )}

        {studentId !== null && recordId === null && (
          <>
            {notice !== null && (
              <div
                role="status"
                className="flex items-start gap-2 rounded-lg border border-ok-border bg-ok-soft px-3 py-2.5 text-sm font-medium text-ok-fg"
              >
                <CheckCircle2 className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <p>{notice}</p>
              </div>
            )}

            <RectifyStudentRecords
              studentId={studentId}
              onRectify={(id) => {
                setNotice(null)
                setSearchParams({ registro: String(id) })
              }}
              onNewSearch={goToSearch}
            />
          </>
        )}

        {studentId !== null && recordId !== null && (
          <RectifyRecordPanel
            recordId={recordId}
            onBack={() => setSearchParams({})}
            onRectified={handleRectified}
            onRequested={handleRequested}
          />
        )}
      </div>
    </AppShell>
  )
}
