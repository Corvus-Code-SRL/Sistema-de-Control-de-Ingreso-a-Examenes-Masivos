import { useState } from 'react'
import { Info, Layers, Plus } from 'lucide-react'
import { useParams } from 'react-router-dom'
import { AppShell } from '@/components/layout/AppShell'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { ActualizarGrupoForm } from '../components/GroupUpdateForm'
import { GroupListItem } from '../components/GroupListItem'
import { GroupsTable } from '../components/GroupsTable'
import { RegistrarGrupoForm } from '../components/GroupRegisterForm'
import { useSubjectGroups } from '../hooks/useSubjectGroups'
import type { Group } from '../types/group.types'

/**
 * Grupos de un par materia-carrera (artboards 1.4 y 1.5).
 *
 * Lista todos los grupos del par, propios y ajenos, porque el docente necesita
 * ver la materia completa aunque solo pueda abrir los suyos.
 *
 * HU-18/HU-19: "Nuevo grupo" en el header y "Editar" por fila (solo en los
 * grupos propios) abren el formulario correspondiente en un Dialog; al
 * confirmar, se recarga el listado con el `reload()` que ya expone el hook.
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
  const [editingGroup, setEditingGroup] = useState<Group | null>(null)

  const title = subject?.nombre ?? 'Grupos de la materia'
  const subjectCareerLabel = subject ? `${subject.nombre} · ${subject.carrera.nombre}` : ''

  return (
    <AppShell
      mobileTitle={title}
      mobileSubtitle={subject?.carrera.nombre}
      breadcrumbs={[{ label: 'Materias', to: '/materias' }, { label: title }]}
    >
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
              Nuevo grupo
            </Button>
          )
        }
      />

      {/*
        El docente puede tener la materia asignada y aún no haber registrado grupos.
        Es un aviso informativo, no un bloqueo: el listado del par sigue visible.
      */}
      {!isLoading && !error && !isEmpty && hasNoOwnGroups && (
        <Alert>
          <Info className="size-4" aria-hidden="true" />
          <AlertTitle>Usted no tiene grupos en esta materia</AlertTitle>
          <AlertDescription>
            Los grupos que se listan abajo pertenecen a otros docentes y son de solo lectura.
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
              <GroupsTable groups={groups} onEdit={setEditingGroup} />
            </div>

            <ul className="md:hidden">
              {groups.map((group) => (
                <GroupListItem key={group.id_grupo} group={group} onEdit={setEditingGroup} />
              ))}
            </ul>
          </>
        )}
      </Card>

      {/* HU-18 */}
      <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Nuevo grupo</DialogTitle>
          </DialogHeader>
          <RegistrarGrupoForm
            careerId={careerId}
            subjectId={subjectId}
            subjectCareerLabel={subjectCareerLabel}
            onCancel={() => setIsCreateOpen(false)}
            onRegistered={() => {
              setIsCreateOpen(false)
              reload()
            }}
          />
        </DialogContent>
      </Dialog>

      {/* HU-19 */}
      <Dialog open={editingGroup !== null} onOpenChange={(open) => !open && setEditingGroup(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Actualizar grupo</DialogTitle>
          </DialogHeader>
          {editingGroup && (
            <ActualizarGrupoForm
              group={editingGroup}
              subjectCareerLabel={subjectCareerLabel}
              onCancel={() => setEditingGroup(null)}
              onUpdated={() => {
                setEditingGroup(null)
                reload()
              }}
            />
          )}
        </DialogContent>
      </Dialog>
    </AppShell>
  )
}
