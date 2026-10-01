import { AlertTriangle, CheckCircle2, CircleX, DoorOpen, RotateCcw, ShieldAlert } from 'lucide-react'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import type { Verification } from '../types/entry-control.types'

interface Props {
  result: Verification
  busy: boolean
  onConfirm: () => void
  onReject: () => void
  onNext: () => void
}

function verdictLabel(value: string): string {
  const labels: Record<string, string> = {
    AUTORIZADO: 'AUTORIZADO', NO_ENCONTRADO: 'NO AUTORIZADO',
    NO_PERTENECE: 'NO AUTORIZADO',
    NO_HABILITADO: 'NO HABILITADO', DUPLICADO: 'INGRESO DUPLICADO',
    AULA_INCORRECTA: 'AULA INCORRECTA', AULA_NO_ASIGNADA: 'AULA NO ASIGNADA',
  }
  return labels[value] ?? value.replace(/_/g, ' ')
}

function StudentDetails({ result }: { result: Verification }) {
  const student = result.estudiante
  if (!student) return null
  const initials = student.nombre_completo.split(' ').slice(0, 2).map((part) => part[0]).join('').toUpperCase()

  return <div className="rounded-lg border bg-card p-4">
    <div className="flex items-center gap-3">
      <Avatar className="size-11"><AvatarFallback className="bg-brand-soft font-semibold text-brand-deep">{initials}</AvatarFallback></Avatar>
      <div className="min-w-0"><p className="font-semibold">{student.nombre_completo}</p><p className="text-xs text-muted-foreground">Estudiante inscrito</p></div>
    </div>
    <dl className="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
      <div><dt className="text-xs text-muted-foreground">Código SIS</dt><dd className="sciem-tnum font-medium">{student.cod_sis}</dd></div>
      <div><dt className="text-xs text-muted-foreground">CI</dt><dd className="sciem-tnum font-medium">{student.ci ?? 'No registrado'}</dd></div>
      {result.grupo && <div><dt className="text-xs text-muted-foreground">Grupo</dt><dd className="font-medium">Grupo {result.grupo.num_grupo}</dd></div>}
      {student.carrera && <div><dt className="text-xs text-muted-foreground">Carrera</dt><dd className="font-medium">{student.carrera}</dd></div>}
    </dl>
    {student.ci_pendiente && <p className="mt-3 rounded-md bg-warn-soft p-2.5 text-xs text-warn-fg">El CI no figura en la ficha. Puede continuar con el código SIS.</p>}
  </div>
}

export function VerificationResult({ result, busy, onConfirm, onReject, onNext }: Props) {
  const allowed = result.autorizado
  const Icon = allowed ? CheckCircle2 : CircleX
  return <section aria-live="polite" aria-labelledby="entry-verdict" className={`space-y-4 rounded-xl border p-4 shadow-sm sm:p-6 ${allowed ? 'border-ok/30 bg-ok-soft/35' : 'border-danger/25 bg-danger-soft/35'}`}>
    <div className="flex gap-3">
      <Icon className={`mt-0.5 size-6 shrink-0 ${allowed ? 'text-ok' : 'text-danger'}`} aria-hidden="true" />
      <div><h2 id="entry-verdict" className={`text-lg font-bold ${allowed ? 'text-ok-fg' : 'text-danger-fg'}`}>{verdictLabel(result.veredicto)}</h2><p className="mt-1 text-sm">{result.motivo}</p></div>
    </div>
    {!allowed && result.veredicto !== 'AULA_NO_ASIGNADA' && <p className="rounded-lg border border-border bg-card px-3 py-2 text-xs text-muted-foreground">Intento registrado con hora, ambiente y controlador.</p>}
    <StudentDetails result={result} />
    {result.ambiente_asignado && result.veredicto === 'AULA_INCORRECTA' && <p className="flex items-center gap-2 rounded-lg border border-warn-border bg-warn-soft p-3 text-sm font-medium text-warn-fg"><DoorOpen className="size-4" />Dirija al estudiante a {result.ambiente_asignado.nro_aula}.</p>}
    {result.ingreso_previo && <div className="rounded-lg border border-danger/25 bg-card p-4 text-sm"><p className="font-semibold text-danger-fg">Ingreso ya registrado</p><p className="mt-1">Hora: {result.ingreso_previo.hora_ingreso ?? '—'} · Ambiente: {result.ingreso_previo.nro_aula ?? '—'}</p><p className="text-muted-foreground">Controló: {result.ingreso_previo.controlador || '—'}</p></div>}
    {result.antecedentes.tiene_antecedentes && <div className="rounded-lg border border-warn-border bg-warn-soft p-4 text-sm"><p className="flex items-center gap-2 font-semibold text-warn-fg"><ShieldAlert className="size-4" />Central de riesgo · {result.antecedentes.cantidad} antecedente(s)</p><p className="mt-1">{result.antecedentes.resumen}</p><p className="mt-1 text-xs text-warn-fg">La alerta no impide el ingreso; la decisión es de quien controla.</p></div>}
    {allowed && <p className="flex items-center gap-2 text-sm text-ok-fg"><AlertTriangle className="size-4" />Compare el nombre y el código SIS con la documentación disponible antes de decidir.</p>}
    <div className="flex flex-col gap-2 sm:flex-row">
      {allowed ? <><Button type="button" variant="outline" className="h-11 flex-1 border-danger/40 text-danger-fg hover:bg-danger-soft" disabled={busy} onClick={onReject}>Rechazar</Button><Button type="button" className="h-11 flex-1" disabled={busy} onClick={onConfirm}>{busy ? 'Registrando…' : 'Aceptar ingreso'}</Button></> :
        <Button type="button" variant="outline" className="h-11 w-full" onClick={onNext}><RotateCcw className="size-4" />Siguiente estudiante</Button>}
    </div>
  </section>
}
