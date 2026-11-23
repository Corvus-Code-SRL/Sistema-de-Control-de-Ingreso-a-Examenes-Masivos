import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { renderWithRouter } from '@/test/render'
import { initialRequests, stubRequestsApi } from '@/test/rectificationRequestsFixtures'
import { RECTIFICATION_REQUESTS_CHANGED_EVENT } from '../hooks/usePendingRectificationRequestsCount'
import { RectificationRequestsPage } from './RectificationRequestsPage'

function renderPage() {
  return renderWithRouter(<RectificationRequestsPage />, { route: '/solicitudes-rectificacion' })
}

const CARD_LABEL = 'Solicitud de rectificación de Carla Condori Vega'
const REASON = 'El registro se mantiene: hay dos testigos del hecho.'

/** Las tarjetas en el orden de la pantalla: pendientes primero y luego las respondidas y vencidas. */
async function cards() {
  return screen.findAllByLabelText(CARD_LABEL)
}

function setup() {
  return userEvent.setup({ applyAccept: false })
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('RectificationRequestsPage — bandeja', () => {
  it('muestra las pendientes primero y, aparte, las respondidas y vencidas de solo lectura', async () => {
    stubRequestsApi()
    renderPage()

    const list = await cards()

    expect(screen.getByRole('heading', { name: 'Solicitudes de rectificación' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Pendientes (2)' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Respondidas y vencidas' })).toBeInTheDocument()
    expect(list).toHaveLength(5)

    for (const pending of list.slice(0, 2)) {
      expect(within(pending).getByRole('button', { name: 'Aceptar' })).toBeInTheDocument()
      expect(within(pending).getByRole('button', { name: 'Rechazar' })).toBeInTheDocument()
      expect(within(pending).getByText(/^Vence en /)).toBeInTheDocument()
    }

    for (const closed of list.slice(2)) {
      expect(within(closed).queryByRole('button', { name: 'Aceptar' })).not.toBeInTheDocument()
      expect(within(closed).queryByRole('button', { name: 'Rechazar' })).not.toBeInTheDocument()
    }

    expect(within(list[2]).getByText('Aceptada')).toBeInTheDocument()
    expect(within(list[3]).getByText('Rechazada')).toBeInTheDocument()
    expect(within(list[4]).getByText('Vencida')).toBeInTheDocument()
  })

  it('cada tarjeta muestra el registro: estudiante, examen, falta, motivo de la solicitud y quién la envía', async () => {
    stubRequestsApi()
    renderPage()

    const [first] = await cards()

    expect(first).toHaveTextContent('Carla Condori Vega')
    expect(first).toHaveTextContent('202170103')
    expect(first).toHaveTextContent('2do Parcial')
    expect(first).toHaveTextContent('Conducta indebida')
    expect(first).toHaveTextContent('Descripción del reporte 9980.')
    expect(first).toHaveTextContent('Se solicita retirar el registro 9980.')
    expect(first).toHaveTextContent('Administrador del Sistema')
  })

  it('avisa cuántas solicitudes rechazadas tuvo el registro, solo si las tuvo', async () => {
    stubRequestsApi()
    renderPage()

    const [withoutHistory, withHistory] = await cards()

    expect(withHistory).toHaveTextContent('Este registro ya tuvo 2 solicitudes de rectificación rechazadas')
    expect(withoutHistory).not.toHaveTextContent('ya tuvo')
  })

  it('ofrece descargar la evidencia del reporte y la imagen de un rechazo propio', async () => {
    stubRequestsApi()
    renderPage()

    const list = await cards()

    expect(within(list[1]).getByRole('button', { name: 'Descargar evidencia del reporte' })).toBeInTheDocument()
    expect(within(list[0]).queryByRole('button', { name: 'Descargar evidencia del reporte' })).not.toBeInTheDocument()
    expect(within(list[3]).getByRole('button', { name: 'Descargar imagen del rechazo' })).toBeInTheDocument()
    expect(list[3]).toHaveTextContent('El registro está sustentado por dos auxiliares.')
  })

  it('sin solicitudes muestra el estado vacío', async () => {
    stubRequestsApi({ requests: [] })
    renderPage()

    expect(await screen.findByText('No tiene solicitudes de rectificación')).toBeInTheDocument()
  })

  it('sin pendientes pero con historial lo avisa y deja el historial a la vista', async () => {
    const [, , accepted] = initialRequests()

    stubRequestsApi({ requests: [accepted] })
    renderPage()

    expect(await screen.findByText('No tiene solicitudes pendientes')).toBeInTheDocument()
    expect(screen.getAllByLabelText(CARD_LABEL)).toHaveLength(1)
  })

  it('si la carga falla muestra el error del servidor', async () => {
    stubRequestsApi({ listFail: true })
    renderPage()

    expect(await screen.findByText('No se pudieron cargar las solicitudes.')).toBeInTheDocument()
  })
})

describe('RectificationRequestsPage — aceptar', () => {
  it('«Aceptar» no guarda: abre la confirmación; cancelar no envía nada', async () => {
    const user = setup()
    const api = stubRequestsApi()
    renderPage()

    const [first] = await cards()

    await user.click(within(first).getByRole('button', { name: 'Aceptar' }))

    const dialog = await screen.findByRole('dialog', { name: '¿Aceptar la rectificación?' })

    expect(dialog).toHaveTextContent('Carla Condori Vega')
    expect(dialog).toHaveTextContent('dejará de figurar en la Central')
    expect(dialog).toHaveTextContent('no se puede deshacer')

    await user.click(within(dialog).getByRole('button', { name: 'Cancelar' }))

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(api.posts()).toHaveLength(0)
  })

  it('al confirmar acepta, avisa y la solicitud pasa a respondidas; el contador del menú se actualiza', async () => {
    const user = setup()
    const api = stubRequestsApi()
    const changed = vi.fn()
    window.addEventListener(RECTIFICATION_REQUESTS_CHANGED_EVENT, changed)
    renderPage()

    const [first] = await cards()

    await user.click(within(first).getByRole('button', { name: 'Aceptar' }))
    await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Sí, aceptar' }))

    expect(await screen.findByRole('status')).toHaveTextContent(
      'Aceptó la solicitud: el registro de Carla Condori Vega ya no figura en la Central de Riesgo.'
    )
    expect(api.posts()).toHaveLength(1)
    expect(new URL(api.posts()[0].url).pathname).toMatch(/\/solicitudes-rectificacion\/9980\/aceptar$/)
    expect(await screen.findByRole('heading', { name: 'Pendientes (1)' })).toBeInTheDocument()
    expect(api.requests.get(9980)?.estado).toBe('ACEPTADA')
    expect(changed).toHaveBeenCalled()

    window.removeEventListener(RECTIFICATION_REQUESTS_CHANGED_EVENT, changed)
  })

  it('si la solicitud ya no se puede responder muestra el conflicto y no avisa de éxito', async () => {
    const user = setup()
    stubRequestsApi({ respondFail: { status: 409, message: 'Esta solicitud ya fue respondida o venció.' } })
    renderPage()

    const [first] = await cards()

    await user.click(within(first).getByRole('button', { name: 'Aceptar' }))
    await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Sí, aceptar' }))

    expect(await screen.findByText('Esta solicitud ya fue respondida o venció.')).toBeInTheDocument()
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
  })
})

describe('RectificationRequestsPage — rechazar', () => {
  async function openRejectForm(user: ReturnType<typeof setup>) {
    const [first] = await cards()

    await user.click(within(first).getByRole('button', { name: 'Rechazar' }))

    return screen.findByRole('form', { name: 'Rechazar solicitud' })
  }

  it('«Rechazar» abre el formulario: motivo obligatorio e imagen opcional que admite galería y cámara', async () => {
    const user = setup()
    stubRequestsApi()
    renderPage()

    const form = await openRejectForm(user)
    const input = within(form).getByLabelText(/imagen de respaldo/i)

    expect(within(form).getByLabelText(/motivo del rechazo/i)).toBeInTheDocument()
    expect(input).toHaveAttribute('accept', 'image/*')
    expect(input).not.toHaveAttribute('capture')
    expect(within(form).getByText('JPG, PNG o WEBP, hasta 5 MB.')).toBeInTheDocument()
  })

  it('sin motivo muestra «El motivo es obligatorio» y no abre la confirmación', async () => {
    const user = setup()
    const api = stubRequestsApi()
    renderPage()

    const form = await openRejectForm(user)

    await user.click(within(form).getByRole('button', { name: 'Rechazar solicitud' }))

    expect(within(form).getByText('El motivo es obligatorio')).toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(api.posts()).toHaveLength(0)
  })

  it('un motivo de menos de diez caracteres se rechaza', async () => {
    const user = setup()
    stubRequestsApi()
    renderPage()

    const form = await openRejectForm(user)

    await user.type(within(form).getByLabelText(/motivo del rechazo/i), 'corto')
    await user.click(within(form).getByRole('button', { name: 'Rechazar solicitud' }))

    expect(within(form).getByText('El motivo debe tener al menos 10 caracteres.')).toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('una imagen de otro formato o de más de 5 MB muestra el mensaje y no se adjunta', async () => {
    const user = setup()
    stubRequestsApi()
    renderPage()

    const form = await openRejectForm(user)
    const input = within(form).getByLabelText(/imagen de respaldo/i)
    const message = 'Formato o tamaño no permitido. Use una imagen jpg, png o webp de hasta 5 MB.'

    await user.upload(input, new File(['texto'], 'nota.txt', { type: 'text/plain' }))
    expect(within(form).getByText(message)).toBeInTheDocument()
    expect(within(form).queryByText('nota.txt')).not.toBeInTheDocument()

    const heavy = new File(['x'], 'grande.png', { type: 'image/png' })

    Object.defineProperty(heavy, 'size', { value: 5 * 1024 * 1024 + 1 })
    await user.upload(input, heavy)

    expect(within(form).getByText(message)).toBeInTheDocument()
    expect(within(form).queryByText('grande.png')).not.toBeInTheDocument()
  })

  it('con motivo e imagen válidos abre la confirmación; cancelar no envía y conserva lo escrito', async () => {
    const user = setup()
    const api = stubRequestsApi()
    renderPage()

    const form = await openRejectForm(user)

    await user.type(within(form).getByLabelText(/motivo del rechazo/i), REASON)
    await user.upload(
      within(form).getByLabelText(/imagen de respaldo/i),
      new File(['x'], 'prueba.png', { type: 'image/png' })
    )
    await user.click(within(form).getByRole('button', { name: 'Rechazar solicitud' }))

    const dialog = await screen.findByRole('dialog', { name: '¿Rechazar la solicitud?' })

    expect(dialog).toHaveTextContent(REASON)
    expect(dialog).toHaveTextContent('prueba.png')
    expect(dialog).toHaveTextContent('seguirá en la Central de Riesgo')

    await user.click(within(dialog).getByRole('button', { name: 'Cancelar' }))

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(api.posts()).toHaveLength(0)
    expect(within(form).getByLabelText(/motivo del rechazo/i)).toHaveValue(REASON)
  })

  it('al confirmar envía el motivo recortado y la imagen, y avisa que el registro sigue en la Central', async () => {
    const user = setup()
    const api = stubRequestsApi()
    renderPage()

    const form = await openRejectForm(user)

    await user.type(within(form).getByLabelText(/motivo del rechazo/i), `  ${REASON}  `)
    await user.upload(
      within(form).getByLabelText(/imagen de respaldo/i),
      new File(['x'], 'prueba.png', { type: 'image/png' })
    )
    await user.click(within(form).getByRole('button', { name: 'Rechazar solicitud' }))
    await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Sí, rechazar' }))

    expect(await screen.findByRole('status')).toHaveTextContent(
      'Rechazó la solicitud: el registro de Carla Condori Vega sigue en la Central de Riesgo.'
    )

    const body = api.posts()[0].body as FormData

    expect(new URL(api.posts()[0].url).pathname).toMatch(/\/solicitudes-rectificacion\/9980\/rechazar$/)
    expect(body.get('motivo')).toBe(REASON)
    expect((body.get('evidencia') as File).name).toBe('prueba.png')
    expect(api.requests.get(9980)?.estado).toBe('RECHAZADA')
  })

  it('la imagen es opcional: sin ella no viaja el campo', async () => {
    const user = setup()
    const api = stubRequestsApi()
    renderPage()

    const form = await openRejectForm(user)

    await user.type(within(form).getByLabelText(/motivo del rechazo/i), REASON)
    await user.click(within(form).getByRole('button', { name: 'Rechazar solicitud' }))
    await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Sí, rechazar' }))

    await screen.findByRole('status')

    expect((api.posts()[0].body as FormData).has('evidencia')).toBe(false)
  })

  it('«Cancelar» en el formulario lo cierra sin enviar nada y devuelve «Aceptar» y «Rechazar»', async () => {
    const user = setup()
    const api = stubRequestsApi()
    renderPage()

    const form = await openRejectForm(user)

    await user.click(within(form).getByRole('button', { name: 'Cancelar' }))

    expect(screen.queryByRole('form', { name: 'Rechazar solicitud' })).not.toBeInTheDocument()
    expect(api.posts()).toHaveLength(0)

    const [first] = await cards()

    expect(within(first).getByRole('button', { name: 'Aceptar' })).toBeInTheDocument()
  })
})
