import {
  AlertTriangle,
  CheckCircle2,
  Info,
  UserPlus,
} from 'lucide-react'
import { useEffect, useRef } from 'react'

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  CardContent,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'

import type { RosterPreviewData } from '../types/roster.types'
import { countRosterRows } from '../utils/rosterRows'

interface RosterConfirmStepProps {
  subjectName: string
  groupLabel: string
  preview: RosterPreviewData
  onBack: () => void
  onConfirm: () => void
  isConfirming: boolean
}

/**
 * Paso 3: confirmación explícita antes de escribir en la base de datos.
 */
export function RosterConfirmStep({
  subjectName,
  groupLabel,
  preview,
  onBack,
  onConfirm,
  isConfirming,
}: RosterConfirmStepProps) {
  const headingRef = useRef<HTMLHeadingElement | null>(null)
  const counts = countRosterRows(preview)

  useEffect(() => {
    headingRef.current?.focus()
  }, [])

  return (
    <>
      <CardHeader className="border-b">
        <CardTitle
          ref={headingRef}
          tabIndex={-1}
          className="sciem-h2 outline-none"
        >
          Confirmar importación
        </CardTitle>

        <p className="sciem-supporting text-muted-foreground">
          {subjectName} · {groupLabel}
        </p>
      </CardHeader>

      <CardContent className="space-y-5">
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <ConfirmStat
            icon={CheckCircle2}
            label="Se incorporan"
            value={counts.incorporables}
          />

          <ConfirmStat
            icon={UserPlus}
            label="Nuevos en SCIEM"
            value={counts.nuevos}
          />

          <ConfirmStat
            icon={CheckCircle2}
            label="Ya inscritos"
            value={counts.yaInscritos}
          />

          <ConfirmStat
            icon={AlertTriangle}
            label="Inconsistentes"
            value={counts.inconsistentes}
          />
        </div>

        <Alert className="border-info-border bg-info-soft text-info">
          <Info aria-hidden="true" />

          <AlertTitle>La carga es aditiva</AlertTitle>

          <AlertDescription>
            Se agregan registros a la nómina actual sin reemplazarla y sin
            duplicar a quienes ya están inscritos.
          </AlertDescription>
        </Alert>

        <div className="rounded-xl border border-border-soft bg-sunken p-4">
          <p className="sciem-label">Destino</p>

          <p className="sciem-body mt-1">
            {subjectName} · {groupLabel}
          </p>

          <p className="sciem-supporting mt-2 text-muted-foreground">
            Ningún otro grupo se modifica.
          </p>
        </div>

        {counts.incorporables === 0 && (
          <Alert
            className="border-warn-border bg-warn-soft text-warn-fg"
            role="alert"
          >
            <AlertTriangle aria-hidden="true" />

            <AlertTitle>No hay estudiantes para incorporar</AlertTitle>

            <AlertDescription>
              La previsualización no contiene filas que puedan incorporarse a
              este grupo.
            </AlertDescription>
          </Alert>
        )}
      </CardContent>

      <CardFooter className="justify-end gap-2">
        <Button
          type="button"
          variant="outline"
          onClick={onBack}
          disabled={isConfirming}
        >
          Volver
        </Button>

        <Button
          type="button"
          onClick={onConfirm}
          disabled={isConfirming || counts.incorporables === 0}
        >
          {isConfirming ? 'Importando…' : 'Confirmar importación'}
        </Button>
      </CardFooter>
    </>
  )
}

function ConfirmStat({
  icon: Icon,
  label,
  value,
}: {
  icon: typeof CheckCircle2
  label: string
  value: number
}) {
  return (
    <div className="rounded-xl border border-border-soft bg-surface p-4">
      <div className="flex items-center gap-2 text-muted-foreground">
        <Icon aria-hidden="true" className="size-4" />
        <span className="sciem-supporting">{label}</span>
      </div>

      <p className="sciem-tnum mt-2 text-2xl font-semibold">
        {value}
      </p>
    </div>
  )
}