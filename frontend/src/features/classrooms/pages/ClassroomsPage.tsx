import { useState } from 'react'
import { DoorOpen } from 'lucide-react'
import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Card } from '@/components/ui/card'
import { ClassroomForm } from '../components/ClassroomForm'
import { useClassrooms } from '../hooks/useClassrooms'
import type { Classroom } from '../types/classroom.types'

export function ClassroomsPage() {
  const { classrooms, status, error, reload } = useClassrooms()
  const [successMessage, setSuccessMessage] = useState<string | null>(null)

  function handleCreated(classroom: Classroom) {
    setSuccessMessage(`El ambiente "${classroom.nro_aula}" fue registrado correctamente.`)

    reload()
  }

  return (
    <AppShell
      mobileTitle="Ambientes"
      breadcrumbs={[{ label: 'Administración' }, { label: 'Ambientes' }]}
    >
      <PageHeader
        title="Registrar ambiente"
        subtitle="Incorpora un nuevo ambiente al catálogo institucional."
      />

      <div className="grid gap-6 lg:grid-cols-[minmax(0,420px)_minmax(0,1fr)]">
        <Card className="p-6">
          <div className="mb-5">
            <h2 className="text-lg font-semibold">Nuevo ambiente</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              Completa los datos requeridos para registrar el ambiente.
            </p>
          </div>

          {successMessage && (
            <div
              role="status"
              className="mb-5 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
            >
              {successMessage}
            </div>
          )}

          <ClassroomForm
            onCreated={(classroom) => {
              handleCreated(classroom)
            }}
          />
        </Card>

        <Card className="overflow-hidden p-0">
          <div className="border-b px-6 py-5">
            <h2 className="text-lg font-semibold">Catálogo de ambientes</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              Ambientes registrados y disponibles en el sistema.
            </p>
          </div>

          {status === 'loading' && <LoadingState rows={5} label="Cargando ambientes" />}

          {status === 'error' && error && <ErrorState error={error} onRetry={reload} />}

          {status === 'success' && classrooms.length === 0 && (
            <EmptyState
              icon={DoorOpen}
              title="Aún no hay ambientes registrados"
              description="El catálogo institucional todavía no contiene ambientes."
            />
          )}

          {status === 'success' && classrooms.length > 0 && (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="border-b bg-muted/40">
                  <tr>
                    <th className="px-6 py-3 text-left font-medium">Ambiente</th>
                    <th className="px-6 py-3 text-left font-medium">Capacidad</th>
                    <th className="px-6 py-3 text-left font-medium">Ubicación</th>
                    <th className="px-6 py-3 text-left font-medium">Estado</th>
                  </tr>
                </thead>

                <tbody>
                  {classrooms.map((classroom) => (
                    <tr key={classroom.id_ambiente} className="border-b last:border-b-0">
                      <td className="px-6 py-4 font-medium">{classroom.nro_aula}</td>

                      <td className="px-6 py-4">{classroom.capacidad}</td>

                      <td className="px-6 py-4">{classroom.ubicacion}</td>

                      <td className="px-6 py-4">
                        <span
                          className={
                            classroom.activo
                              ? 'rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-800'
                              : 'rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground'
                          }
                        >
                          {classroom.activo ? 'Activo' : 'Inactivo'}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      </div>
    </AppShell>
  )
}
