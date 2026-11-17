import { CollaboratorInvitationStateBadge } from './CollaboratorInvitationStateBadge'
import { formatExamDate, formatSentAt, formatTimeRange } from '../lib/collaboratorFormat'
import type { SentInvitation } from '../types/collaborator.types'

interface SentInvitationsTableProps {
  invitations: SentInvitation[]
}

/** Invitaciones enviadas: docente, examen, fecha de envío y estado de cada una. */
export function SentInvitationsTable({ invitations }: SentInvitationsTableProps) {
  return (
    <div className="overflow-x-auto rounded-xl border bg-card">
      <table className="w-full text-left text-sm">
        <caption className="sr-only">Invitaciones enviadas</caption>
        <thead className="border-b bg-muted/40 text-xs text-muted-foreground">
          <tr>
            <th scope="col" className="px-3 py-2 font-medium">Docente</th>
            <th scope="col" className="px-3 py-2 font-medium">Examen</th>
            <th scope="col" className="px-3 py-2 font-medium">Fecha de envío</th>
            <th scope="col" className="px-3 py-2 font-medium">Agrega sus grupos</th>
            <th scope="col" className="px-3 py-2 font-medium">Estado</th>
          </tr>
        </thead>
        <tbody className="divide-y">
          {invitations.map((invitation) => (
            <tr key={invitation.id_invitacion}>
              <td className="px-3 py-2.5">
                <p className="font-medium">{invitation.docente.nombre_completo}</p>
                {invitation.docente.cod_sis && (
                  <p className="text-xs text-muted-foreground">Código SIS {invitation.docente.cod_sis}</p>
                )}
              </td>
              <td className="px-3 py-2.5">
                <p className="font-medium">{invitation.examen.nombre_examen}</p>
                <p className="text-xs text-muted-foreground">
                  {[
                    invitation.examen.materia,
                    formatExamDate(invitation.examen.fecha),
                    formatTimeRange(invitation.examen.hora_inicio, invitation.examen.hora_fin),
                  ]
                    .filter(Boolean)
                    .join(' · ')}
                </p>
              </td>
              <td className="whitespace-nowrap px-3 py-2.5">{formatSentAt(invitation.fecha_invitacion)}</td>
              <td className="px-3 py-2.5">
                {invitation.permite_agregar_grupos ? (invitation.lista_por_grupo ? 'Sí · lista por grupo' : 'Sí') : 'No'}
              </td>
              <td className="px-3 py-2.5">
                <CollaboratorInvitationStateBadge state={invitation.estado} />
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
