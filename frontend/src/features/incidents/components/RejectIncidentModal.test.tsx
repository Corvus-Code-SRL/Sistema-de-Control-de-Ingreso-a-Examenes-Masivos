import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { RejectIncidentModal } from './RejectIncidentModal'
import type { IncidentReview } from '../types/incident.types'

const incident: IncidentReview = {
  id: 'estudiante-9926',
  tipo: 'estudiante',
  estado: 'PENDIENTE',
  persona: { nombre_completo: 'José Luis Pérez Núñez', codigo: '202312345', grupo: null, carrera: null, foto_url: null },
  examen: { id_examen: 9807, nombre_examen: 'Cálculo finalizado', fecha: '2026-10-18', materia: 'Cálculo I' },
  tipo_falta: { id: 3, nombre: 'Material no permitido' },
  descripcion: 'Material impreso entre sus útiles.',
  evidencia_url: null,
  evidencia: null,
  reportante: { nombre_completo: 'Daniela Ferrufino', rol: 'Auxiliar' },
  fecha_reporte: '2026-10-18T09:10:00+00:00',
  hora_reporte: '09:10',
  ambiente: { id_ambiente: 9601, nro_aula: '691A' },
  intento: null,
  revisor: null,
  en_central_riesgo: false,
}

function setup() {
  const onConfirm = vi.fn().mockResolvedValue(undefined)
  const onOpenChange = vi.fn()
  render(
    <RejectIncidentModal open onOpenChange={onOpenChange} incident={incident} onConfirm={onConfirm} />
  )
  return { onConfirm, onOpenChange, user: userEvent.setup() }
}

describe('RejectIncidentModal', () => {
  it('exige la observación: sin ella, error inline y no llama a onConfirm', async () => {
    const { onConfirm, user } = setup()

    await user.click(screen.getByRole('button', { name: 'Rechazar incidencia' }))

    expect(screen.getByText('La observación es obligatoria.')).toBeInTheDocument()
    expect(onConfirm).not.toHaveBeenCalled()
  })

  it('rechaza con observación recortada', async () => {
    const { onConfirm, user } = setup()

    await user.type(screen.getByLabelText(/Observación/), '  Carnet renovado.  ')
    await user.click(screen.getByRole('button', { name: 'Rechazar incidencia' }))

    await waitFor(() => expect(onConfirm).toHaveBeenCalledWith('Carnet renovado.'))
  })

  it('muestra el mensaje del servidor si el rechazo falla', async () => {
    const onConfirm = vi.fn().mockRejectedValue(new Error('No se pudo rechazar.'))
    render(
      <RejectIncidentModal open onOpenChange={vi.fn()} incident={incident} onConfirm={onConfirm} />
    )
    const user = userEvent.setup()

    await user.type(screen.getByLabelText(/Observación/), 'Carnet renovado.')
    await user.click(screen.getByRole('button', { name: 'Rechazar incidencia' }))

    expect(await screen.findByText('No se pudo rechazar la incidencia. Intente de nuevo.')).toBeInTheDocument()
  })

  it('cancelar cierra sin rechazar', async () => {
    const { onConfirm, onOpenChange, user } = setup()

    await user.click(screen.getByRole('button', { name: 'Cancelar' }))

    expect(onOpenChange).toHaveBeenCalledWith(false)
    expect(onConfirm).not.toHaveBeenCalled()
  })
})
