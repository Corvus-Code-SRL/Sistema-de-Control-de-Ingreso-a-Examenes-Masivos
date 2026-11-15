import { Navigate, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { SearchX } from 'lucide-react'
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

const BASE_PATH = '/rectificar-registro'

/** Un id de la URL como entero positivo de la base; null si no hay o no lo es. */
function idFrom(param: string | null | undefined): number | null {
  if (param === null || param === undefined || !/^[1-9]\d{0,8}$/.test(param)) return null

  return Number(param)
}

/**
 * Rectificar un registro de la Central de Riesgo (HU-062), solo para quien tiene `riesgo.rectificar`.
 *
 * Tres pasos que viven en la URL: buscar al estudiante (`/rectificar-registro`), ver sus registros
 * (`/rectificar-registro/:id`) y abrir uno para rectificar (`?registro=`). Si la búsqueda da un solo
 * estudiante se abre directo; si da varios, se elige. Esta vista no usa los endpoints de la consulta (HU-046).
 */
export function RectifyRiskRecordPage() {
  const { id: idParam } = useParams<{ id: string }>()
  const [searchParams, setSearchParams] = useSearchParams()
  const navigate = useNavigate()
  const { status, result, error, search, reset } = useRectifiableStudentSearch()

  const studentId = idFrom(idParam)
  const recordParam = searchParams.get('registro')
  const recordId = idFrom(recordParam)

  if (idParam !== undefined && studentId === null) {
    return <Navigate to={BASE_PATH} replace />
  }

  async function handleSearch(term: string) {
    const found = await search(term)

    if (found !== null && found.students.length === 1) {
      navigate(`${BASE_PATH}/${found.students[0].id_estudiante}`)
    }
  }

  const goToSearch = () => {
    reset()
    navigate(BASE_PATH)
  }

  return (
    <AppShell
      mobileTitle="Rectificar registro"
      breadcrumbs={[{ label: 'Central de riesgo' }, { label: 'Rectificar registro' }]}
    >
      <PageHeader
        title="Rectificar registro de la central"
        subtitle="Corrija el tipo de falta o la descripción de un registro confirmado. El original se conserva en el historial."
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
          <RectifyStudentRecords
            studentId={studentId}
            onRectify={(id) => setSearchParams({ registro: String(id) })}
            onNewSearch={goToSearch}
          />
        )}

        {studentId !== null && recordId !== null && (
          <RectifyRecordPanel recordId={recordId} onBack={() => setSearchParams({})} />
        )}
      </div>
    </AppShell>
  )
}
