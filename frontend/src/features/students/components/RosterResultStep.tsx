import {
  AlertTriangle,
  CheckCircle2,
  Info,
  UserPlus,
} from 'lucide-react'
import { useEffect, useRef } from 'react'

import {
  Alert,
  AlertDescription,
  AlertTitle,
} from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  CardContent,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'

import type { RosterConfirmationData } from '../types/roster.types'

interface RosterResultStepProps {
  result: RosterConfirmationData
  onBack: () => void
}

/**
 * Resultado final usando exclusivamente las cifras devueltas por confirm.
 */
export function RosterResultStep({
  result,
  onBack,
}: RosterResultStepProps) {
  const headingRef = useRef<HTMLHeadingElement | null>(null)
  const hasStudents = result.estudiantes_inscritos > 0

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
          Nómina actualizada
        </CardTitle>

        <p className="sciem-supporting text-muted-foreground">
          Resultado de la confirmación de la carga.
        </p>
      </CardHeader>

      <CardContent className="space-y-5">
        <Alert
          className={
            hasStudents
              ? 'border-ok/20 bg-ok-soft text-ok-fg'
              : 'border-warn-border bg-warn-soft text-warn-fg'
          }
        >
          {hasStudents ? (
            <CheckCircle2 aria-hidden="true" />
          ) : (
            <Info aria-hidden="true" />
          )}

          <AlertTitle>
            {hasStudents
              ? 'La nómina fue actualizada'
              : 'No hubo estudiantes para incorporar'}
          </AlertTitle>

          <AlertDescription>
            {hasStudents
              ? `${result.estudiantes_inscritos} estudiantes quedaron inscritos en el grupo.`
              : 'La confirmación terminó sin incorporar nuevos estudiantes.'}
          </AlertDescription>
        </Alert>

        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <ResultStat
            icon={UserPlus}
            label="Nuevos en SCIEM"
            value={result.estudiantes_creados}
          />

          <ResultStat
            icon={CheckCircle2}
            label="Estudiantes inscritos"
            value={result.estudiantes_inscritos}
          />

          <ResultStat
            icon={CheckCircle2}
            label="Ya inscritos"
            value={result.ya_inscritos}
          />

          <ResultStat
            icon={AlertTriangle}
            label="Inscripciones inactivas"
            value={result.inscripciones_inactivas}
          />

          <ResultStat
            icon={AlertTriangle}
            label="Filas inconsistentes"
            value={result.filas_inconsistentes}
          />

          <ResultStat
            icon={Info}
            label="Filas procesadas"
            value={result.total_filas}
          />
        </div>

        <div className="space-y-2 rounded-xl border border-border-soft bg-sunken p-4">
          {result.ya_inscritos > 0 && (
            <p className="sciem-supporting">
              <strong>{result.ya_inscritos}</strong> ya estaban inscritos.
            </p>
          )}

          {result.inscripciones_inactivas > 0 && (
            <p className="sciem-supporting">
              <strong>{result.inscripciones_inactivas}</strong> tienen una
              inscripción inactiva y no se reactivaron.
            </p>
          )}

          {result.filas_inconsistentes > 0 && (
            <p className="sciem-supporting">
              <strong>{result.filas_inconsistentes}</strong> filas quedaron
              fuera por inconsistencias.
            </p>
          )}

          {result.ya_inscritos === 0 &&
            result.inscripciones_inactivas === 0 &&
            result.filas_inconsistentes === 0 && (
              <p className="sciem-supporting text-muted-foreground">
                No hubo observaciones adicionales en la confirmación.
              </p>
            )}
        </div>
      </CardContent>

      <CardFooter className="justify-end">
        <Button
          type="button"
          onClick={onBack}
        >
          Volver a la nómina
        </Button>
      </CardFooter>
    </>
  )
}

function ResultStat({
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