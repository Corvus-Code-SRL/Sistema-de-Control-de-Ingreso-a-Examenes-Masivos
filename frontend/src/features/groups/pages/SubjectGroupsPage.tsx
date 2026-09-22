import { useState } from 'react'
import { Layers, Plus } from 'lucide-react'
import { useParams } from 'react-router-dom'
import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Dialog, DialogContent } from '@/components/ui/dialog'
import { GroupListItem } from '../components/GroupListItem'
import { GroupsTable } from '../components/GroupsTable'
import { RegistrarGrupoForm } from '../components/GroupRegisterForm'
import { SubjectFilterChip } from '../components/SubjectFilterChip'
import { useSubjectGroups } from '../hooks/useSubjectGroups'
import { Toast } from '@/components/ui/toast'
import type { GroupMutationResponse } from '../types/group.types'

/**
 * Grupos de un par materia-carrera (artboards 1.4 y 1.5).
 *
 * Lista todos los grupos del par, propios y ajenos, porque el docente necesita
 * ver la materia completa aunque solo pueda abrir los suyos.
 *
 * "Nuevo grupo" en el header abre el formulario correspondiente en un Dialog;
 * al confirmar, se recarga el listado con el `reload()` que expone el hook.
 */
export function SubjectGroupsPage() {
  const params = useParams()
  const careerId = Number(params.idCarrera)
  const subjectId = Number(params.idMateria)

  const { subject, groups, isLoading, isEmpty, hasNoOwnGroups, error, reload } = useSubjectGroups(
    careerId,
    subjectId
  )

  const [isCreateOpen, setIsCreateOpen] = useState(false)

  // Estado para la notificación Toast de éxito
  const [toastNotification, setToastNotification] = useState<{
    show: boolean
    title: string
    description: string
  }>({
    show: false,
    title: '',
    description: '',
  })

  const title = subject?.nombre ?? 'Grupos de la materia'
  const subjectCareerLabel = subject ? `${subject.nombre} · ${subject.carrera.nombre}` : ''

  const handleGroupRegistered = (result: GroupMutationResponse['data']) => {
    setIsCreateOpen(false)
    reload()

    setToastNotification({
      show: true,
      title: `Grupo ${result.grupo.num_grupo} registrado`,
      description: 'El grupo se registró correctamente en el período seleccionado.',
    })
  }

  return (
    <AppShell
      mobileTitle={title}
      mobileSubtitle={subject?.carrera.nombre}
      breadcrumbs={[{ label: 'Materias', to: '/materias' }, { label: title }]}
    >
      {/* Toast emergente de éxito */}
      {toastNotification.show && (
        <Toast
          title={toastNotification.title}
          description={toastNotification.description}
          onClose={() => setToastNotification((prev) => ({ ...prev, show: false }))}
        />
      )}

      <PageHeader
        title={title}
        backTo="/materias"
        backLabel="Volver a Materias"
        subtitle={
          subject && (
            <span className="flex flex-wrap items-center gap-1.5">
              <span className="font-mono">{subject.codigo}</span>
              <span aria-hidden="true">·</span>
              <span>{subject.carrera.nombre}</span>
            </span>
          )
        }
        actions={
          subject && (
            <Button onClick={() => setIsCreateOpen(true)}>
              <Plus className="size-4" aria-hidden="true" />
              Añadir grupo
            </Button>
          )
        }
      />

      {subject && <SubjectFilterChip subjectName={subject.nombre} />}

      {/*
        El docente puede tener la materia asignada y aún no haber registrado grupos.
        Es un aviso informativo, no un bloqueo: el listado del par sigue visible.
      */}
      {!isLoading && !error && !isEmpty && hasNoOwnGroups && (
        <Alert variant="info" className="mb-4">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M12 16v-4"></path>
            <path d="M12 8h.01"></path>
          </svg>
          <AlertTitle>Usted no tiene grupos en esta materia</AlertTitle>
          <AlertDescription>
            La materia figura entre sus asignaciones, pero todavía no registró grupos. Puede seguir consultando el catálogo o registrar el primer grupo.
          </AlertDescription>
        </Alert>
      )}

      <Card className="overflow-hidden p-0">
        {isLoading && <LoadingState rows={4} label="Cargando los grupos de la materia" />}

        {!isLoading && error && <ErrorState error={error} onRetry={reload} />}

        {!isLoading && !error && isEmpty && (
          <EmptyState
            icon={Layers}
            title="Sin grupos registrados"
            description={
              subject
                ? `${subject.nombre} no tiene grupos registrados en ${subject.carrera.nombre} para el período vigente.`
                : 'Esta materia no tiene grupos registrados para el período vigente.'
            }
            action={
              subject && (
                <Button onClick={() => setIsCreateOpen(true)}>
                  <Plus className="size-4" aria-hidden="true" />
                  Registrar el primer grupo
                </Button>
              )
            }
          />
        )}

        {!isLoading && !error && !isEmpty && (
          <>
            <div className="hidden md:block">
              <GroupsTable groups={groups} />
            </div>

            <ul className="md:hidden">
              {groups.map((group) => (
                <GroupListItem key={group.id_grupo} group={group} />
              ))}
            </ul>
          </>
        )}
      </Card>

      {/* HU-18 */}
      <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
        <DialogContent className="sm:max-w-[640px] p-0 overflow-hidden">
          <RegistrarGrupoForm
            careerId={careerId}
            subjectId={subjectId}
            subjectName={subject?.nombre ?? ''}
            teacherName={groups[0]?.docente.nombre_completo ?? 'Docente'}
            subjectCareerLabel={subjectCareerLabel}
            onCancel={() => setIsCreateOpen(false)}
            onRegistered={handleGroupRegistered}
          />
        </DialogContent>
      </Dialog>
    </AppShell>
  )
}