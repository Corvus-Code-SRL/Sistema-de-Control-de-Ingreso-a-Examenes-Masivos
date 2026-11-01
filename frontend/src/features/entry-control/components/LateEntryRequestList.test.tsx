import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/lib/api-client'
import { makeLateEntryRequest, makeOtherExamWarning } from '@/test/lateEntryFixtures'
import { LateEntryRequestList } from './LateEntryRequestList'

const pending = makeLateEntryRequest()
const approved = makeLateEntryRequest({
  id_solicitud: 32,
  estado: 'AUTORIZADA',
  estudiante: { id_estudiante: 502, cod_sis: '202301904', nombre_completo: 'Pedro Gómez' },
})

function mobileList() {
  return screen.getByRole('list', { name: 'Solicitudes' })
}

describe('LateEntryRequestList', () => {
  it('muestra la tabla y la lista de tarjetas con los mismos datos', () => {
    render(<LateEntryRequestList title="Solicitudes" requests={[pending, approved]} error={null} showRequester />)

    const table = screen.getByRole('table')
    // Tabla o tarjetas según el ancho de la tarjeta, no de la pantalla.
    expect(table.closest('.\\@container')).not.toBeNull()
    expect(table.parentElement?.parentElement).toHaveClass('hidden', '@4xl:block')
    expect(within(table).getByText('Lucía Fernández Rojas')).toBeInTheDocument()
    expect(within(table).getAllByText('Mario Quispe')).toHaveLength(2)

    const list = mobileList()
    expect(list).toHaveClass('@4xl:hidden')
    expect(within(list).getAllByRole('listitem')).toHaveLength(2)
    expect(within(list).getAllByText('10:12', { selector: 'dd' })).toHaveLength(2)
    expect(within(list).getByText('Autorizada')).toBeInTheDocument()
  })

  it('el auxiliar no ve la columna de quién pidió ni acciones', () => {
    render(<LateEntryRequestList title="Solicitudes" requests={[pending]} error={null} showRequester={false} />)

    expect(screen.queryByText('Solicitado por')).not.toBeInTheDocument()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('el docente resuelve solo las pendientes', async () => {
    const onApprove = vi.fn()
    render(
      <LateEntryRequestList
        title="Solicitudes"
        requests={[pending, approved]}
        error={null}
        showRequester
        actions={{ busyId: null, onApprove, onReject: vi.fn() }}
      />
    )

    const list = mobileList()
    expect(within(list).getAllByRole('button', { name: /Autorizar/ })).toHaveLength(1)
    await userEvent.click(within(list).getByRole('button', { name: 'Autorizar: Lucía Fernández Rojas' }))
    expect(onApprove).toHaveBeenCalledWith(pending)
  })

  it('distingue carga, vacío, error y datos sin actualizar', () => {
    const error = new ApiError(0, 'No se pudo conectar con el servidor. Revise su conexión.', {}, undefined, { kind: 'network' })
    const { rerender } = render(<LateEntryRequestList title="Solicitudes" requests={null} error={null} showRequester />)
    expect(screen.getByText('Cargando solicitudes')).toBeInTheDocument()

    rerender(<LateEntryRequestList title="Solicitudes" requests={[]} error={null} showRequester />)
    expect(screen.getByText('Todavía no hay solicitudes.')).toBeInTheDocument()

    rerender(<LateEntryRequestList title="Solicitudes" requests={null} error={error} showRequester />)
    expect(screen.queryByText('Cargando solicitudes')).not.toBeInTheDocument()

    rerender(<LateEntryRequestList title="Solicitudes" requests={[pending]} error={error} showRequester />)
    expect(screen.getByRole('status')).toHaveTextContent('Solicitudes sin actualizar')
  })

  it('advierte el otro examen solo en las pendientes', () => {
    const warning = makeOtherExamWarning()
    render(
      <LateEntryRequestList
        title="Solicitudes"
        requests={[{ ...pending, otro_examen: warning }, { ...approved, otro_examen: warning }]}
        error={null}
        showRequester
      />
    )

    const list = mobileList()
    const [first, second] = within(list).getAllByRole('listitem')
    expect(within(first).getByText('Ingreso en otro examen')).toBeInTheDocument()
    expect(within(second).queryByText('Ingreso en otro examen')).not.toBeInTheDocument()
  })
})
