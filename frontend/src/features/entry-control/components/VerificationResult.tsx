import { useState, type ReactNode } from 'react'
import { AlertTriangle, CheckCircle2, CircleX, DoorOpen, Flag, RotateCcw, ShieldAlert, UserRound } from 'lucide-react'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import type { EntryStudent, Verification, VerificationActions } from '../types/entry-control.types'

/** Acciones del control de ingreso normal (HU-032). */
export const ENTRY_VERIFICATION_ACTIONS: VerificationActions = {
  confirmLabel: 'Aceptar ingreso',
  busyLabel: 'Registrando…',
  rejectLabel: 'Rechazar',
  recordsAttempt: true,
}

interface Props {
  result: Verification
  busy: boolean
  onConfirm: () => void
  onReject: () => void
  onNext: () => void
  onReport?: () => void
  reported?: boolean
  presentedCi?: string
  subjectName?: string | null
  actions?: VerificationActions
  /** Contenido propio del flujo antes de los botones, como el selector de ambiente. */
  extra?: ReactNode
  /** Reemplaza la fila de botones cuando el flujo decide otras acciones. */
  footer?: ReactNode
  /**
   * Título de precaución en lugar de «AUTORIZADO»: el resultado autoriza pero hay algo que
   * verificar (HU-037, ingreso en otro examen). Sin él se ve igual que en el control de ingreso.
   */
  caution?: string
  /**
   * 'card': la fila de botones se apila según el ancho de la tarjeta (container query), para
   * etiquetas largas en columnas angostas. 'viewport' (por defecto): según la pantalla, como en HU-032.
   */
  actionsLayout?: 'viewport' | 'card'
}

const ACTION_LAYOUT = {
  viewport: { row: 'flex flex-col gap-2 sm:flex-row', button: 'h-11 w-full sm:w-auto sm:flex-1' },
  card: { row: 'flex flex-col gap-2 @xl:flex-row', button: 'h-11 w-full @xl:w-auto @xl:flex-1' },
} as const

function verdictLabel(value: string): string {
  const labels: Record<string, string> = {
    RECHAZADO_MANUAL: 'INGRESO RECHAZADO', AUTORIZADO: 'AUTORIZADO', AUXILIAR_EXAMEN: 'AUXILIAR DEL EXAMEN', NO_ENCONTRADO: 'NO AUTORIZADO',
    NO_PERTENECE: 'NO AUTORIZADO',
    NO_HABILITADO: 'NO HABILITADO', CI_NO_COINCIDE: 'CI NO COINCIDE', DUPLICADO: 'INGRESO DUPLICADO',
    AULA_INCORRECTA: 'AULA INCORRECTA', AULA_NO_ASIGNADA: 'AULA NO ASIGNADA',
  }
  return labels[value] ?? value.replace(/_/g, ' ')
}

function StudentPhoto({ student }: { student: EntryStudent }) {
  const [imageFailed, setImageFailed] = useState(false)
  const initials = student.nombre_completo.split(' ').slice(0, 2).map((part) => part[0]).join('').toUpperCase()

  return <div className="flex shrink-0 self-center flex-col items-center gap-1">
    <Avatar className="rounded-lg after:rounded-lg" style={{ width: 96, height: 120 }}>
      {student.foto_url && <AvatarImage
        src={student.foto_url}
        alt={`Fotografía de ${student.nombre_completo}`}
        className="rounded-lg"
        referrerPolicy="no-referrer"
        onLoadingStatusChange={(status) => setImageFailed(status === 'error')}
      />}
      <AvatarFallback className="rounded-lg bg-brand-soft text-lg font-semibold text-brand-deep">{initials}</AvatarFallback>
    </Avatar>
    {(!student.foto_url || imageFailed) && <span className="text-xs text-muted-foreground">Sin fotografía</span>}
  </div>
}

function StudentDetails({ result, presentedCi, subjectName }: { result: Verification; presentedCi?: string; subjectName?: string | null }) {
  const student = result.estudiante
  if (!student) return null
  const status = result.grupo === null ? 'No inscrito en este examen'
    : result.veredicto === 'NO_HABILITADO' ? 'No habilitado para este examen' : null

  // La fila foto + datos depende del ancho de la tarjeta, no del viewport: en la columna derecha
  // de un escritorio con el menú abierto la tarjeta es angosta aunque la pantalla no lo sea.
  return <div className="@container rounded-lg border bg-card p-4">
    <div className="flex flex-col gap-4 sm:@sm:flex-row">
      <StudentPhoto key={`${student.id_estudiante}:${student.foto_url ?? ''}`} student={student} />
      <div className="min-w-0 flex-1">
        <p className="break-words text-lg font-semibold">{student.nombre_completo}</p>
        {status && <p className="text-xs text-muted-foreground">{status}</p>}
        <dl className="mt-3 grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-2 text-sm [&>dd]:break-words">
          <dt className="text-muted-foreground">Código SIS</dt><dd className="sciem-tnum font-medium">{student.cod_sis}</dd>
          <dt className="text-muted-foreground">CI</dt><dd className="sciem-tnum font-medium">{presentedCi ? <>{presentedCi}{student.ci && (presentedCi === student.ci ? <span className="text-ok-fg"> · coincide</span> : <span className="text-danger-fg"> · registrado: {student.ci}</span>)}</> : 'No ingresado'}</dd>
          <dt className="text-muted-foreground">Carrera</dt><dd className="font-medium">{student.carrera ?? 'No disponible'}</dd>
          <dt className="text-muted-foreground">Grupo</dt><dd className="font-medium">{result.grupo ? `Grupo ${result.grupo.num_grupo}${subjectName ? ` · ${subjectName}` : ''}` : '—'}</dd>
          <dt className="text-muted-foreground">Ambiente</dt><dd className="font-medium">{result.ambiente_asignado ? `Aula ${result.ambiente_asignado.nro_aula}` : '—'}</dd>
        </dl>
      </div>
    </div>
    {student.ci_pendiente && <p className="mt-3 rounded-md bg-warn-soft p-2.5 text-xs text-warn-fg">El CI no figura en la ficha. Puede continuar con el código SIS.</p>}
  </div>
}

function AssistantDetails({ result }: { result: Verification }) {
  const assistant = result.auxiliar
  if (!assistant) return null

  return <div className="rounded-lg border bg-card p-4">
    <p className="font-semibold">{assistant.nombre_completo}</p>
    <p className="text-xs text-muted-foreground">Auxiliar habilitado para este examen</p>
    <dl className="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
      <div><dt className="text-xs text-muted-foreground">Código SIS</dt><dd className="sciem-tnum font-medium">{assistant.cod_sis}</dd></div>
      <div><dt className="text-xs text-muted-foreground">Ambiente de control</dt><dd className="font-medium">{assistant.ambiente_asignado?.nro_aula ?? 'Sin ambiente asignado'}</dd></div>
    </dl>
  </div>
}

export function VerificationResult({
  result, busy, onConfirm, onReject, onNext, onReport, reported = false, presentedCi, subjectName,
  actions = ENTRY_VERIFICATION_ACTIONS, extra, footer, caution, actionsLayout = 'viewport',
}: Props) {
  const allowed = result.autorizado
  const cautious = allowed && caution !== undefined
  const layout = ACTION_LAYOUT[actionsLayout]
  const isAssistant = result.veredicto === 'AUXILIAR_EXAMEN' && result.auxiliar != null
  const canReport = !allowed && result.estudiante != null && result.veredicto !== 'AULA_NO_ASIGNADA'
  const Icon = cautious ? ShieldAlert : allowed ? CheckCircle2 : isAssistant ? UserRound : CircleX
  const tone = cautious ? 'border-warn-border bg-warn-soft/35' : allowed ? 'border-ok/30 bg-ok-soft/35' : isAssistant ? 'border-border bg-card' : 'border-danger/25 bg-danger-soft/35'
  return <section aria-live="polite" aria-labelledby="entry-verdict" className={`${actionsLayout === 'card' ? '@container ' : ''}space-y-4 rounded-xl border p-4 shadow-sm sm:p-6 ${tone}`}>
    <div className="flex gap-3">
      <Icon className={`mt-0.5 size-6 shrink-0 ${cautious ? 'text-warn-fg' : allowed ? 'text-ok' : isAssistant ? 'text-brand-deep' : 'text-danger'}`} aria-hidden="true" />
      <div><h2 id="entry-verdict" className={`text-lg font-bold ${cautious ? 'text-warn-fg' : allowed ? 'text-ok-fg' : isAssistant ? 'text-brand-deep' : 'text-danger-fg'}`}>{cautious ? caution : verdictLabel(result.veredicto)}</h2><p className="mt-1 text-sm">{result.motivo}</p></div>
    </div>
    {actions.recordsAttempt && !allowed && !isAssistant && result.veredicto !== 'AULA_NO_ASIGNADA' && <p className="rounded-lg border border-border bg-card px-3 py-2 text-xs text-muted-foreground">Intento registrado con hora, ambiente y controlador.</p>}
    <StudentDetails result={result} presentedCi={presentedCi} subjectName={subjectName} />
    <AssistantDetails result={result} />
    {result.ambiente_asignado && result.veredicto === 'AULA_INCORRECTA' && <p className="flex items-center gap-2 rounded-lg border border-warn-border bg-warn-soft p-3 text-sm font-medium text-warn-fg"><DoorOpen className="size-4" />Dirija al estudiante a {result.ambiente_asignado.nro_aula}.</p>}
    {result.ingreso_previo && <div className="rounded-lg border border-danger/25 bg-card p-4 text-sm"><p className="font-semibold text-danger-fg">Ingreso ya registrado</p><p className="mt-1">Hora: {result.ingreso_previo.hora_ingreso ?? '—'} · Ambiente: {result.ingreso_previo.nro_aula ?? '—'}</p><p className="text-muted-foreground">Controló: {result.ingreso_previo.controlador || '—'}</p></div>}
    {result.antecedentes.total > 0 && <div className="rounded-lg border border-warn-border bg-warn-soft p-4 text-sm">
      <p className="flex items-center gap-2 font-semibold text-warn-fg"><ShieldAlert className="size-4" />Este estudiante tiene {result.antecedentes.total === 1 ? 'un registro' : `${result.antecedentes.total} registros`} en la Central de Riesgo</p>
      {result.antecedentes.latest && <p className="mt-1">{result.antecedentes.latest.tipo_falta} · {result.antecedentes.latest.fecha_registro}</p>}
    </div>}
    {allowed && <p className={`flex items-start gap-2 text-sm ${cautious ? 'font-medium text-danger-fg' : 'text-ok-fg'}`}><AlertTriangle className="mt-0.5 size-4 shrink-0" />Compare el nombre y el código SIS con la documentación disponible antes de decidir.</p>}
    {extra}
    {footer ?? <div className={layout.row}>
      {allowed ? <><Button type="button" variant="outline" className={`${layout.button} border-danger/40 text-danger-fg hover:bg-danger-soft`} disabled={busy} onClick={onReject}>{actions.rejectLabel}</Button><Button type="button" className={layout.button} disabled={busy} onClick={onConfirm}>{busy ? actions.busyLabel : actions.confirmLabel}</Button></> : <>
        {canReport && onReport && <Button type="button" variant="outline" className={`${layout.button} border-danger/40 text-danger-fg hover:bg-danger-soft`} disabled={busy || reported} onClick={onReport}><Flag className="size-4" />{reported ? 'Ya reportado' : 'Reportar incidencia'}</Button>}
        <Button type="button" variant="outline" className={layout.button} onClick={onNext}><RotateCcw className="size-4" />{isAssistant ? 'Nueva verificación' : 'Siguiente estudiante'}</Button>
      </>}
    </div>}
  </section>
}
