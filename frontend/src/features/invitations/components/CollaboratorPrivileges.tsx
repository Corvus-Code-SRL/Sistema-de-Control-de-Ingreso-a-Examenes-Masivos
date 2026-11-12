import { Check, Lock } from 'lucide-react'

/**
 * Lo que podrá hacer como colaborador y lo que queda para el docente responsable (CA-6). Solo lectura.
 * [PENDIENTE] Lista propuesta en el mockup; falta validarla con la ingeniera.
 */
const CAN = [
  'Ver el examen y la lista de sus estudiantes',
  'Controlar el ingreso en la puerta',
  'Registrar retrasos, situaciones e incidencias de sus estudiantes',
  'Ver el reporte final de sus grupos',
]

const OWNER_ONLY = ['Editar o cancelar el examen', 'Invitar a otros docentes', 'Iniciar y terminar el examen']

export function CollaboratorPrivileges() {
  return (
    <section aria-labelledby="collaborator-privileges" className="flex flex-col gap-2.5">
      <h3 id="collaborator-privileges" className="text-sm font-semibold text-foreground">
        Como colaborador
      </h3>
      <div className="grid gap-4 sm:grid-cols-2">
        <div className="flex flex-col gap-1.5">
          <p className="text-xs font-semibold text-ok-fg">PODRÁ</p>
          <ul className="flex flex-col gap-1.5" aria-label="Podrá">
            {CAN.map((item) => (
              <li key={item} className="flex items-start gap-2 text-sm">
                <Check className="mt-0.5 size-3.5 shrink-0 text-ok-fg" aria-hidden="true" />
                {item}
              </li>
            ))}
          </ul>
        </div>
        <div className="flex flex-col gap-1.5">
          <p className="text-xs font-semibold text-muted-foreground">SOLO EL DOCENTE RESPONSABLE</p>
          <ul className="flex flex-col gap-1.5" aria-label="Solo el docente responsable">
            {OWNER_ONLY.map((item) => (
              <li key={item} className="flex items-start gap-2 text-sm text-muted-foreground">
                <Lock className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                {item}
              </li>
            ))}
          </ul>
        </div>
      </div>
    </section>
  )
}
