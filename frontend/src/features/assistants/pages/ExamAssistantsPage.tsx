import { useCallback, useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { Search, UserPlus, Users } from 'lucide-react'
import { Toast } from '@/components/ui/toast'
import { AppShell } from '@/components/layout/AppShell'
import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { assistantsService } from '../services/assistantsService'
import type {
  Assistant,
  ExamAssistantsData,
  SuggestedAssistant,
} from '../types/assistant.types'

type Candidate = Pick<
  Assistant,
  'id_usuario' | 'nombre_completo' | 'cod_sis'
>

function errorMessage(error: unknown): string {
  return error instanceof Error
    ? error.message
    : 'No se pudo completar la operación.'
}

export function ExamAssistantsPage() {
  const { examId } = useParams<{ examId: string }>()
  const id = Number(examId)
  const validId =
    !!examId &&
    /^[0-9]{1,9}$/.test(examId) &&
    Number.isSafeInteger(id) &&
    id > 0

  const [data, setData] = useState<ExamAssistantsData | null>(null)
  const [suggestions, setSuggestions] = useState<SuggestedAssistant[]>([])
  const [searchResults, setSearchResults] = useState<Assistant[]>([])
  const [query, setQuery] = useState('')
  const [loading, setLoading] = useState(true)
  const [searching, setSearching] = useState(false)
  const [busyId, setBusyId] = useState<string | null>(null)
  const [loadError, setLoadError] = useState<string | null>(null)
  const [searchError, setSearchError] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const dismissNotice = useCallback(() => setNotice(null), [])
  const [removeTarget, setRemoveTarget] = useState<Candidate | null>(null)

  const reload = useCallback(
    async (signal?: AbortSignal) => {
      const [examAssistants, suggested] = await Promise.all([
        assistantsService.listExamAssistants(id, signal),
        assistantsService.suggestedForExam(id, signal),
      ])

      if (signal?.aborted) return
      setData(examAssistants)
      setSuggestions(suggested)
    },
    [id]
  )

  useEffect(() => {
    if (!validId) {
      setLoading(false)
      return
    }

    const controller = new AbortController()
    setLoading(true)
    setLoadError(null)

    void reload(controller.signal)
      .catch((error: unknown) => {
        if (!controller.signal.aborted) {
          setLoadError(errorMessage(error))
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false)
      })

    return () => controller.abort()
  }, [reload, validId])

  useEffect(() => {
    const criteria = query.trim()
    setSearchResults([])

    if (criteria.length < 2 || !data?.editable) {
      setSearchError(null)
      setSearching(false)
      return
    }

    const controller = new AbortController()
    setSearching(true)
    setSearchError(null)

    const timer = setTimeout(() => {
      void assistantsService
        .search(criteria, controller.signal)
        .then((results) => {
          if (!controller.signal.aborted) setSearchResults(results)
        })
        .catch((error: unknown) => {
          if (!controller.signal.aborted) setSearchError(errorMessage(error))
        })
        .finally(() => {
          if (!controller.signal.aborted) setSearching(false)
        })
    }, 300)

    return () => {
      clearTimeout(timer)
      controller.abort()
    }
  }, [query, data?.editable])

  const enabledIds = new Set(
    data?.auxiliares.map((assistant) => assistant.id_usuario) ?? []
  )

  async function enable(userId: string) {
    if (!data?.editable || busyId !== null) return

    setBusyId(userId)
    setActionError(null)
    setNotice(null)

    try {
      await assistantsService.enableForExam(id, userId)
      await reload()
      setNotice('Auxiliar habilitado correctamente.')
    } catch (error: unknown) {
      setActionError(errorMessage(error))
    } finally {
      setBusyId(null)
    }
  }

  async function remove() {
    if (!data?.editable || !removeTarget || busyId !== null) return

    const userId = removeTarget.id_usuario
    setBusyId(userId)
    setActionError(null)
    setNotice(null)

    try {
      await assistantsService.removeFromExam(id, userId)
      await reload()
      setRemoveTarget(null)
      setNotice('Auxiliar quitado correctamente.')
    } catch (error: unknown) {
      setActionError(errorMessage(error))
    } finally {
      setBusyId(null)
    }
  }

  function candidateRow(person: Candidate) {
    const enabled = enabledIds.has(person.id_usuario)

    return (
      <div
        key={person.id_usuario}
        className="flex flex-wrap items-center justify-between gap-3 border-b border-border-soft py-3 last:border-0"
      >
        <div className="min-w-0">
          <p className="font-medium text-foreground">
            {person.nombre_completo}
          </p>
          <p className="text-sm text-muted-foreground">
            SIS: {person.cod_sis}
          </p>
        </div>

        {data?.editable && (
            <Button
                type="button"
                size="sm"
                variant={enabled ? 'outline' : 'default'}
                disabled={enabled || busyId !== null}
                onClick={() => void enable(person.id_usuario)}
            >
                {enabled
                ? 'Habilitado'
                : busyId === person.id_usuario
                    ? 'Habilitando...'
                    : 'Habilitar'}
            </Button>
        )}
      </div>
    )
  }

  return (
    <AppShell
      mobileTitle="Auxiliares del examen"
      breadcrumbs={[
        { label: 'Exámenes', to: '/examenes/programados' },
        { label: 'Detalle', to: `/examenes/${id}` },
        { label: 'Auxiliares' },
      ]}
    >
      <PageHeader
        title="Auxiliares del examen"
        subtitle={`Examen ${examId ?? ''}`}
        backTo={`/examenes/${id}`}
        backLabel="Volver al examen"
      />

      {!validId && (
        <p role="alert" className="text-sm text-danger">
          El identificador del examen no es válido.
        </p>
      )}

      {validId && loading && (
        <p className="text-sm text-muted-foreground">
          Cargando auxiliares...
        </p>
      )}

      {loadError && (
        <div role="alert" className="space-y-3">
          <p className="text-sm text-danger">{loadError}</p>
          <Button
            variant="outline"
            onClick={() => {
              setLoadError(null)
              setLoading(true)
              void reload()
                .catch((error: unknown) => setLoadError(errorMessage(error)))
                .finally(() => setLoading(false))
            }}
          >
            Reintentar
          </Button>
        </div>
      )}

      {data && !loadError && (
        <div className="mx-auto max-w-5xl space-y-6">
          {!data.editable && (
            <div
              role="status"
              className="rounded-xl border border-border-soft bg-surface p-4 text-sm"
            >
              El control de ingreso ya empezó o el examen no permite modificaciones.
              Esta vista es de solo lectura.
            </div>
          )}

          {actionError && (
            <p role="alert" className="text-sm text-danger">
              {actionError}
            </p>
          )}

          {notice && (
            <Toast
                title={notice}
                description="La lista de auxiliares del examen se actualizó."
                onClose={dismissNotice}
            />
          )}

          <section className="rounded-xl border border-border-soft bg-surface p-5">
            <div className="mb-4 flex items-center gap-2">
              <Users className="size-5" aria-hidden="true" />
              <div>
                <h2 className="sciem-h3">Auxiliares sugeridos</h2>
                <p className="text-sm text-muted-foreground">
                  Personas vinculadas a los grupos de este examen.
                </p>
              </div>
            </div>

            {suggestions.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                No hay auxiliares sugeridos. Puede buscar otros auxiliares registrados
                en el sistema.
              </p>
            ) : (
              suggestions.map(candidateRow)
            )}
          </section>

          {data.editable && (
            <section className="rounded-xl border border-border-soft bg-surface p-5">
              <div className="mb-4 flex items-center gap-2">
                <Search className="size-5" aria-hidden="true" />
                <div>
                  <h2 className="sciem-h3">Buscar otros auxiliares</h2>
                  <p className="text-sm text-muted-foreground">
                    Busque por nombre o código SIS, aunque no pertenezcan a los
                    grupos del examen.
                  </p>
                </div>
              </div>

              <Input
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder="Nombre o código SIS"
                aria-label="Buscar auxiliar por nombre o código SIS"
              />

              {searching && (
                <p className="mt-3 text-sm text-muted-foreground">
                  Buscando...
                </p>
              )}

              {searchError && (
                <p role="alert" className="mt-3 text-sm text-danger">
                  {searchError}
                </p>
              )}

              {!searching && query.trim().length >= 2 &&
                searchResults.length === 0 && !searchError && (
                  <p className="mt-3 text-sm text-muted-foreground">
                    No se encontraron auxiliares.
                  </p>
                )}

              <div className="mt-3">{searchResults.map(candidateRow)}</div>
            </section>
          )}

          <section className="rounded-xl border border-border-soft bg-surface p-5">
            <div className="mb-4 flex items-center gap-2">
              <UserPlus className="size-5" aria-hidden="true" />
              <div>
                <h2 className="sciem-h3">Auxiliares habilitados</h2>
                <p className="text-sm text-muted-foreground">
                  {data.auxiliares.length} auxiliares habilitados para este examen.
                </p>
              </div>
            </div>

            {data.auxiliares.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                Todavía no hay auxiliares habilitados.
              </p>
            ) : (
              data.auxiliares.map((assistant) => (
                <div
                  key={assistant.id_usuario}
                  className="flex flex-wrap items-center justify-between gap-3 border-b border-border-soft py-3 last:border-0"
                >
                  <div>
                    <p className="font-medium">
                      {assistant.nombre_completo}
                    </p>
                    <p className="text-sm text-muted-foreground">
                      SIS: {assistant.cod_sis}
                    </p>
                  </div>

                  {data.editable && (
                    <Button
                      variant="outline"
                      size="sm"
                      disabled={busyId !== null}
                      onClick={() => setRemoveTarget({
                        id_usuario: assistant.id_usuario,
                        nombre_completo: assistant.nombre_completo,
                        cod_sis: assistant.cod_sis,
                      })}
                    >
                      Quitar
                    </Button>
                  )}
                </div>
              ))
            )}
          </section>
        </div>
      )}

      <Dialog
        open={removeTarget !== null}
        onOpenChange={(open) => {
          if (!open && busyId === null) setRemoveTarget(null)
        }}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Quitar auxiliar del examen</DialogTitle>
            <DialogDescription>
              ¿Desea quitar a {removeTarget?.nombre_completo}?
              Esta acción no elimina su cuenta ni sus grupos académicos.
            </DialogDescription>
          </DialogHeader>

          {actionError && (
            <p role="alert" className="text-sm text-danger">
              {actionError}
            </p>
          )}

          <DialogFooter>
            <Button
              variant="outline"
              disabled={busyId !== null}
              onClick={() => setRemoveTarget(null)}
            >
              Cancelar
            </Button>
            <Button
              variant="destructive"
              disabled={busyId !== null}
              onClick={() => void remove()}
            >
              {busyId !== null ? 'Quitando...' : 'Sí, quitar'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </AppShell>
  )
}
