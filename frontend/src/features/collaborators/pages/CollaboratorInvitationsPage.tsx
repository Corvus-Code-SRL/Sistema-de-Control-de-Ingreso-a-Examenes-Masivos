import { useCallback, useState } from 'react'
import { CalendarX, Mail } from 'lucide-react'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { PermissionDenied } from '@/components/common/PermissionDenied'
import { AppShell } from '@/components/layout/AppShell'
import { Toast } from '@/components/ui/toast'
import { CollaboratorResults } from '../components/CollaboratorResults'
import { CollaboratorSearchForm } from '../components/CollaboratorSearchForm'
import { InvitableExamSelect } from '../components/InvitableExamSelect'
import { InviteConfirmDialog } from '../components/InviteConfirmDialog'
import { SentInvitationsTable } from '../components/SentInvitationsTable'
import { useCollaboratorSearch } from '../hooks/useCollaboratorSearch'
import { useInvitableExams } from '../hooks/useInvitableExams'
import { useSendInvitation } from '../hooks/useSendInvitation'
import { useSentInvitations } from '../hooks/useSentInvitations'
import type { CollaboratorCandidate } from '../types/collaborator.types'

const TITLE = 'Invitar docentes colaboradores'

/**
 * HU-026: el docente elige uno de sus exámenes programados, busca a un docente de la misma materia y le
 * envía la invitación; abajo ve las que ya envió. Responderlas es de HU-027, así que aquí no hay enlaces a ella.
 */
export function CollaboratorInvitationsPage() {
  const exams = useInvitableExams()
  const sent = useSentInvitations()
  const [examId, setExamId] = useState('')
  const selectedExam = exams.data?.find((exam) => String(exam.id_examen) === examId) ?? null
  const search = useCollaboratorSearch(selectedExam?.id_examen ?? null)
  const sending = useSendInvitation()
  const [candidate, setCandidate] = useState<CollaboratorCandidate | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const dismissNotice = useCallback(() => setNotice(null), [])

  function closeDialog() {
    setCandidate(null)
    sending.clearError()
  }

  async function confirm(allowGroups: boolean, listByGroup: boolean) {
    if (!candidate || !selectedExam || sending.sending) return

    const created = await sending.send(selectedExam.id_examen, {
      id_docente: candidate.id_usuario,
      permite_agregar_grupos: allowGroups,
      lista_por_grupo: allowGroups && listByGroup,
    })

    if (created === null) return

    search.markInvited(candidate.id_usuario)
    closeDialog()
    setNotice(`Invitación enviada a ${candidate.nombre_completo}.`)
    sent.reload()
  }

  const forbidden = exams.error?.isForbidden || sent.error?.isForbidden

  return (
    <AppShell mobileTitle={TITLE} breadcrumbs={[{ label: TITLE }]}>
      <PageHeader
        title={TITLE}
        subtitle="Invite a docentes que dictan la misma materia para dar un examen en común. La invitación queda pendiente hasta que la respondan."
      />

      {forbidden ? (
        <PermissionDenied message="Solo el docente que creó un examen puede invitar colaboradores." />
      ) : (
        <>
          <section aria-labelledby="collaborator-invite-title" className="space-y-4">
            <h2 id="collaborator-invite-title" className="sciem-h2">
              Nueva invitación
            </h2>

            {exams.status === 'loading' && <LoadingState rows={2} label="Cargando sus exámenes programados" />}
            {exams.status === 'error' && exams.error && <ErrorState error={exams.error} onRetry={exams.reload} />}

            {exams.status === 'success' && exams.data?.length === 0 && (
              <EmptyState
                icon={CalendarX}
                title="No tiene exámenes programados"
                description="Cuando programe un examen podrá invitar a otros docentes de su materia."
              />
            )}

            {exams.status === 'success' && exams.data && exams.data.length > 0 && (
              <div className="space-y-4">
                <InvitableExamSelect exams={exams.data} value={examId} onChange={setExamId} />
                <CollaboratorSearchForm
                  key={examId}
                  onSearch={search.search}
                  disabled={selectedExam === null}
                  searching={search.status === 'loading'}
                />

                {search.status === 'loading' && <LoadingState rows={2} label="Buscando docentes" />}
                {search.status === 'error' && search.error && (
                  <ErrorState error={search.error} onRetry={() => search.search(search.criteria)} />
                )}
                {search.status === 'success' && (
                  <CollaboratorResults results={search.results} onInvite={setCandidate} />
                )}
              </div>
            )}
          </section>

          <section aria-labelledby="collaborator-sent-title" className="space-y-4">
            <h2 id="collaborator-sent-title" className="sciem-h2">
              Invitaciones enviadas
            </h2>

            {sent.status === 'loading' && <LoadingState rows={3} label="Cargando invitaciones enviadas" />}
            {sent.status === 'error' && sent.error && <ErrorState error={sent.error} onRetry={sent.reload} />}

            {sent.status === 'success' && sent.data?.length === 0 && (
              <EmptyState
                icon={Mail}
                title="Aún no ha enviado invitaciones"
                description="Las invitaciones que envíe aparecerán aquí con su estado."
              />
            )}

            {sent.status === 'success' && sent.data && sent.data.length > 0 && (
              <SentInvitationsTable invitations={sent.data} />
            )}
          </section>
        </>
      )}

      <InviteConfirmDialog
        teacher={candidate}
        exam={selectedExam}
        sending={sending.sending}
        error={sending.error}
        onConfirm={(allowGroups, listByGroup) => void confirm(allowGroups, listByGroup)}
        onCancel={closeDialog}
      />

      {notice && <Toast title="Invitación enviada" description={notice} onClose={dismissNotice} />}
    </AppShell>
  )
}
