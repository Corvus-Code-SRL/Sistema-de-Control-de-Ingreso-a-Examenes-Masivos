import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { mockApiWith } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import type { EntryContext, Verification } from '../types/entry-control.types'
import { ROOM_WARNING_VERDICT } from '../utils/entryRoomWarning'
import { EntryControlPage } from './EntryControlPage'

const WARNING = { tipo: 'FUERA_DE_AMBIENTE', mensaje: 'Este estudiante tiene asignado el ambiente 692B' } as const
const context: EntryContext = {
  id_examen: 7, nombre_examen: 'Primer parcial', fecha: '2026-09-29', hora_inicio: '08:00', hora_fin: '10:00',
  estado: 'EN_CURSO', ingreso_ordinario_cerrado: false, materia: 'Bases de Datos I', carrera: 'Sistemas',
  grupos: ['1'], rol_controlador: 'AUXILIAR', es_creador: false, id_ambiente_asignado: 5,
  ambientes: [{ id_ambiente: 5, nro_aula: '691A' }, { id_ambiente: 6, nro_aula: '692B' }],
}
const verdict: Verification = {
  veredicto: 'AUTORIZADO', motivo: 'Inscrito en un grupo del examen.', autorizado: true,
  estudiante: { id_estudiante: 19, cod_sis: '202309201', ci: null, ci_pendiente: true,
    nombre_completo: 'Olga Rojas', carrera: 'Sistemas', foto_url: null },
  grupo: { id_grupo: 1, num_grupo: '1' }, ambiente_asignado: { id_ambiente: 6, nro_aula: '692B' },
  avisos_ambiente: [WARNING], antecedentes: { total: 0, latest: null }, ingreso_previo: null,
}
const status = { ingresados: 0, pendientes: 1, total: 1, ultimos_ingresos: [], version: '1', conectados: [] }

describe('EntryControlPage con aviso de ambiente', () => {
  it('«Aceptar ingreso» pide confirmar antes de registrar', async () => {
    const { calls } = mockApiWith(({ url, body }) => {
      if (url.endsWith('/contexto')) return { body: { data: context } }
      if (url.includes('/estado')) return { body: { data: status } }
      if (url.endsWith('/verificar')) return { body: { data: verdict } }
      if (url.includes('/intentos?')) return { body: { data: [] } }
      if (url.endsWith('/confirmar-ingreso')) {
        return (body as { confirmar_aviso_ambiente?: true }).confirmar_aviso_ambiente
          ? { status: 201, body: { data: { creado: true } } }
          : { status: 409, body: { message: 'Confirme', veredicto: ROOM_WARNING_VERDICT, avisos_ambiente: [WARNING] } }
      }
      return undefined
    })
    const confirmations = () => calls.filter(({ url }) => url.endsWith('/confirmar-ingreso'))
    const user = userEvent.setup()
    renderWithRouter(<EntryControlPage />, { route: '/examenes/7/control-ingreso', path: '/examenes/:examId/control-ingreso' })

    await user.type(await screen.findByLabelText('Código SIS'), '202309201')
    await user.click(screen.getByRole('button', { name: 'Verificar estudiante' }))
    await user.click(await screen.findByRole('button', { name: 'Aceptar ingreso' }))

    const dialog = await screen.findByRole('dialog', { name: '¿Registrar de todos modos?' })
    expect(within(dialog).getByText(WARNING.mensaje)).toBeInTheDocument()
    expect(confirmations()).toHaveLength(1)
    expect(confirmations()[0].body).toEqual({ id_estudiante: 19, id_ambiente: 5 })
    expect(screen.queryByText(/Ingreso registrado:/)).not.toBeInTheDocument()

    await user.click(within(dialog).getByRole('button', { name: 'Registrar de todos modos' }))

    expect(await screen.findByText(/Ingreso registrado:/)).toBeInTheDocument()
    await waitFor(() => expect(confirmations()).toHaveLength(2))
    expect(confirmations()[1].body).toEqual({ id_estudiante: 19, id_ambiente: 5, confirmar_aviso_ambiente: true })
  })
})
