import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { ApproveIncidentModal } from './ApproveIncidentModal'
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
    <ApproveIncidentModal open onOpenChange={onOpenChange} incident={incident} onConfirm={onConfirm} />
  )
  return { onConfirm, onOpenChange, user: userEvent.setup() }
}

describe('ApproveIncidentModal', () => {
  it('muestra el título y la consecuencia de aprobar', () => {
    setup()

    expect(screen.getByText('¿Aprobar y enviar a la Central de Riesgo?')).toBeInTheDocument()
    expect(screen.getByText(/José Luis Pérez Núñez/)).toBeInTheDocument()
    expect(screen.getByText(/Material no permitido/)).toBeInTheDocument()
  })

  it('aprueba sin observación', async () => {
    const { onConfirm, user } = setup()

    await user.click(screen.getByRole('button', { name: 'Aprobar y registrar' }))

    await waitFor(() => expect(onConfirm).toHaveBeenCalledWith(null))
  })

  it('aprueba con observación recortada', async () => {
    const { onConfirm, user } = setup()

    await user.type(screen.getByLabelText(/Observación/), '  Confirmado en la puerta.  ')
    await user.click(screen.getByRole('button', { name: 'Aprobar y registrar' }))

    await waitFor(() => expect(onConfirm).toHaveBeenCalledWith('Confirmado en la puerta.'))
  })

  it('muestra el error del servidor si falla la aprobación', async () => {
    const onConfirm = vi.fn().mockRejectedValue(new Error('No se pudo aprobar.'))
    render(
      <ApproveIncidentModal open onOpenChange={vi.fn()} incident={incident} onConfirm={onConfirm} />
    )
    const user = userEvent.setup()

    await user.click(screen.getByRole('button', { name: 'Aprobar y registrar' }))

    expect(await screen.findByText('No se pudo aprobar la incidencia.')).toBeInTheDocument()
  })

  it('cancelar cierra el modal sin aprobar', async () => {
    const { onConfirm, onOpenChange, user } = setup()

    await user.click(screen.getByRole('button', { name: 'Cancelar' }))

    expect(onOpenChange).toHaveBeenCalledWith(false)
    expect(onConfirm).not.toHaveBeenCalled()
  })
})
