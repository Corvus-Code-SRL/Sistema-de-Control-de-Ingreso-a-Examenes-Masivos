import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useLocation } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { renderWithRouter } from '@/test/render'
import { ADMIN_NAME, VOIDED_REASON, stubRectificationApi } from '@/test/riskRectificationFixtures'
import { RectifyRiskRecordPage } from './RectifyRiskRecordPage'

function LocationProbe() {
  const { pathname, search } = useLocation()

  return <div data-testid="location">{`${pathname}${search}`}</div>
}

function renderPage(route = '/rectificar-registro') {
  return renderWithRouter(
    <>
      <RectifyRiskRecordPage />
      <LocationProbe />
    </>,
    { route, path: '/rectificar-registro/:id?' }
  )
}

const location = () => screen.getByTestId('location').textContent

type User = ReturnType<typeof userEvent.setup>

async function search(user: User, term: string) {
  await user.type(screen.getByLabelText(/código sis, carnet de identidad o nombre/i), term)
  await user.click(screen.getByRole('button', { name: 'Buscar' }))
}

async function openRecord(user: User, exam: string) {
  await user.click(await screen.findByRole('button', { name: new RegExp(`Rectificar registro del examen ${exam}`) }))
}

/** El valor que la ficha muestra bajo una etiqueta. */
function field(label: string): HTMLElement {
  return screen.getByText(label, { selector: 'dt' }).nextElementSibling as HTMLElement
}

async function writeReason(user: User, text = REASON) {
  await user.type(screen.getByLabelText(/motivo de la rectificación/i), text)
}

async function review(user: User, text = REASON) {
  await writeReason(user, text)
  await user.click(screen.getByRole('button', { name: 'Enviar solicitud al docente' }))

  return screen.findByRole('dialog', { name: '¿Enviar la solicitud de rectificación?' })
}

const REASON = 'Se retira: el reporte no tenía sustento.'

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('RectifyRiskRecordPage — búsqueda de estudiantes', () => {
  it('muestra el título y el cuadro de búsqueda sin consultar nada al abrir', () => {
    const api = stubRectificationApi()

    renderPage()

    expect(screen.getByRole('heading', { name: 'Rectificar registro de la central' })).toBeInTheDocument()
    expect(screen.getByLabelText(/código sis, carnet de identidad o nombre/i)).toBeInTheDocument()
    expect(api.calls).toHaveLength(0)
  })

  it('un código SIS con un solo estudiante abre directo sus registros activos, del más reciente al más antiguo', async () => {
    const user = userEvent.setup()
    stubRectificationApi()
    renderPage()

    await search(user, '202170103')

    const table = await screen.findByRole('table', { name: 'Registros activos del estudiante' })
    const rows = within(table).getAllByRole('row').slice(1)

    expect(location()).toBe('/rectificar-registro/9702')
    expect(screen.getByRole('heading', { name: 'Carla Condori Vega' })).toBeInTheDocument()
    expect(within(table).getAllByRole('columnheader').map((header) => header.textContent)).toEqual([
      'Fecha',
      'Examen',
      'Tipo de falta',
      'Aprobó',
      'Acción',
    ])
    expect(rows).toHaveLength(2)
    expect(within(rows[0]).getByText('01/10/2026')).toBeInTheDocument()
    expect(within(rows[0]).getByText('3er Parcial')).toBeInTheDocument()
    expect(within(rows[0]).getByText('Copia en examen')).toBeInTheDocument()
    expect(within(rows[0]).getByText('Marcelo Quiroga Andrade')).toBeInTheDocument()
    expect(within(rows[1]).getByText('22/09/2026')).toBeInTheDocument()
    expect(within(rows[1]).getByText('Conducta indebida')).toBeInTheDocument()
  })

  it('cada registro activo tiene su botón verde «Rectificar», grande y con icono', async () => {
    const user = userEvent.setup()
    stubRectificationApi()
    renderPage()

    await search(user, '202170103')

    const rows = within(await screen.findByRole('table', { name: 'Registros activos del estudiante' }))
      .getAllByRole('row')
      .slice(1)

    for (const row of rows) {
      const button = within(row).getByRole('button', { name: /^Rectificar registro del examen/ })

      expect(button).toHaveTextContent('Rectificar')
      expect(button).toHaveAttribute('data-variant', 'success')
      expect(button).toHaveAttribute('data-size', 'xl')
      expect(button).toHaveClass('bg-ok', 'text-primary-foreground', 'hover:bg-ok-fg', 'h-11')
      expect(button.querySelector('svg')).not.toBeNull()
    }
  })

  it('no muestra insignias «Original» o «Rectificado» ni contadores de rectificaciones', async () => {
    const user = userEvent.setup()
    stubRectificationApi()
    renderPage()

    await search(user, '202170103')
    await screen.findByRole('table', { name: 'Registros activos del estudiante' })

    expect(screen.queryByText('Original')).not.toBeInTheDocument()
    expect(screen.queryByText('Rectificado')).not.toBeInTheDocument()
    expect(screen.queryByText(/\d+ rectificaci/i)).not.toBeInTheDocument()
  })

  it('el registro ya rectificado sale de la lista activa y se muestra aparte, solo de lectura, con fecha, administrador y motivo', async () => {
    const user = userEvent.setup()
    stubRectificationApi()
    renderPage()

    await search(user, '202170103')

    const active = await screen.findByRole('table', { name: 'Registros activos del estudiante' })
    const rectified = screen.getByRole('table', { name: 'Registros rectificados del estudiante' })
    const rows = within(rectified).getAllByRole('row').slice(1)

    expect(screen.getByRole('heading', { name: 'Registros rectificados' })).toBeInTheDocument()
    expect(within(active).queryByText('Material no permitido')).not.toBeInTheDocument()
    expect(within(rectified).getAllByRole('columnheader').map((header) => header.textContent)).toEqual([
      'Fecha del registro',
      'Examen',
      'Tipo de falta',
      'Rectificado el',
      'Administrador',
      'Motivo',
    ])
    expect(rows).toHaveLength(1)
    expect(within(rows[0]).getByText('Material no permitido')).toBeInTheDocument()
    expect(within(rows[0]).getByText('05/10/2026')).toBeInTheDocument()
    expect(within(rows[0]).getByText(ADMIN_NAME)).toBeInTheDocument()
    expect(within(rows[0]).getByText(VOIDED_REASON)).toBeInTheDocument()
    expect(within(rectified).queryByRole('button')).not.toBeInTheDocument()
  })

  it('busca por carnet de identidad', async () => {
    const user = userEvent.setup()
    stubRectificationApi()
    renderPage()

    await search(user, '7400101')

    expect(await screen.findByRole('heading', { name: 'María Pérez Gómez' })).toBeInTheDocument()
    expect(location()).toBe('/rectificar-registro/9700')
  })

  it('un apellido compartido, con o sin tilde, lista a los estudiantes para elegir uno', async () => {
    const user = userEvent.setup()
    stubRectificationApi()
    renderPage()

    await search(user, 'perez')

    const list = await screen.findByRole('list', { name: 'Estudiantes encontrados' })
    const items = within(list).getAllByRole('listitem')

    expect(location()).toBe('/rectificar-registro')
    expect(items).toHaveLength(2)
    expect(within(items[0]).getByText('María Pérez Gómez')).toBeInTheDocument()
    expect(within(items[0]).getByText('Código SIS 202170101 · Carnet 7400101')).toBeInTheDocument()
    expect(within(items[1]).getByText('Luis Perez Soliz')).toBeInTheDocument()
    expect(screen.queryByRole('note')).not.toBeInTheDocument()

    await user.click(within(items[0]).getByRole('button'))

    expect(await screen.findByRole('heading', { name: 'María Pérez Gómez' })).toBeInTheDocument()
    expect(location()).toBe('/rectificar-registro/9700')
  })

  it('si hay más coincidencias de las mostradas, pide afinar la búsqueda', async () => {
    const user = userEvent.setup()
    stubRectificationApi({ hasMore: true })
    renderPage()

    await search(user, 'perez')

    expect(await screen.findByRole('note')).toHaveTextContent(
      'Se muestran las primeras 10 coincidencias. Afine la búsqueda'
    )
  })

  it('sin coincidencias avisa «No se encontró ningún estudiante con ese dato»', async () => {
    const user = userEvent.setup()
    stubRectificationApi()
    renderPage()

    await search(user, 'zzzzzz')

    expect(await screen.findByText('No se encontró ningún estudiante con ese dato')).toBeInTheDocument()
  })

  it('un estudiante sin registros activos lo avisa y no ofrece rectificar', async () => {
    const user = userEvent.setup()
    stubRectificationApi()
    renderPage()

    await search(user, '202170104')

    expect(
      await screen.findByText('El estudiante no tiene registros activos en la Central de Riesgo')
    ).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Daniel Rojas Mendoza' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /rectificar/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Registros rectificados' })).not.toBeInTheDocument()
  })

  it('un nombre de menos de tres letras no se envía y explica el mínimo', async () => {
    const user = userEvent.setup()
    const api = stubRectificationApi()
    renderPage()

    await search(user, 'pe')

    expect(screen.getByRole('alert')).toHaveTextContent('Para buscar por nombre ingrese al menos 3 caracteres.')
    expect(api.calls).toHaveLength(0)
  })

  it('un dato vacío no se envía', async () => {
    const user = userEvent.setup()
    const api = stubRectificationApi()
    renderPage()

    await user.click(screen.getByRole('button', { name: 'Buscar' }))

    expect(screen.getByRole('alert')).toHaveTextContent('Ingrese el código SIS')
    expect(api.calls).toHaveLength(0)
  })

  it('si la búsqueda falla muestra el error del servidor', async () => {
    const user = userEvent.setup()
    stubRectificationApi({ searchFail: true })
    renderPage()

    await search(user, '202170103')

    expect(await screen.findByText('No se pudo buscar estudiantes.')).toBeInTheDocument()
  })

  it('un id inválido en la URL vuelve a la búsqueda', async () => {
    stubRectificationApi()
    renderPage('/rectificar-registro/abc')

    await waitFor(() => expect(location()).toBe('/rectificar-registro'))
  })

  it('«Buscar otro estudiante» vuelve al cuadro de búsqueda', async () => {
    const user = userEvent.setup()
    stubRectificationApi()
    renderPage('/rectificar-registro/9702')

    await user.click(await screen.findByRole('button', { name: 'Buscar otro estudiante' }))

    expect(location()).toBe('/rectificar-registro')
    expect(screen.getByLabelText(/código sis, carnet de identidad o nombre/i)).toBeInTheDocument()
  })

  it('no ofrece ningún control para eliminar, borrar ni reactivar', async () => {
    stubRectificationApi()
    renderPage('/rectificar-registro/9702')

    await screen.findByRole('table', { name: 'Registros activos del estudiante' })

    expect(screen.queryByRole('button', { name: /eliminar|borrar|quitar|reactivar|restaurar/i })).not.toBeInTheDocument()
  })
})

describe('RectifyRiskRecordPage — ficha del registro', () => {
  it('al pulsar «Rectificar» abre arriba la ficha completa del registro, de solo lectura', async () => {
    const user = userEvent.setup()
    stubRectificationApi()
    renderPage('/rectificar-registro/9702')

    await openRecord(user, '2do Parcial')

    expect(await screen.findByRole('heading', { name: 'Registro de la Central de Riesgo' })).toBeInTheDocument()
    expect(location()).toBe('/rectificar-registro/9702?registro=9962')
    expect(field('Estudiante')).toHaveTextContent('Carla Condori Vega')
    expect(field('Código SIS')).toHaveTextContent('202170103')
    expect(field('Carnet de identidad')).toHaveTextContent('7400103')
    expect(field('Examen')).toHaveTextContent('2do Parcial')
    expect(field('Materia')).toHaveTextContent('Cálculo I')
    expect(field('Fecha del examen')).toHaveTextContent('10/09/2026')
    expect(field('Fecha y hora del hecho')).toHaveTextContent('10/09/2026 · 08:12')
    expect(field('Aula')).toHaveTextContent('Aula 691A')
    expect(field('Tipo de falta')).toHaveTextContent('Conducta indebida')
    expect(field('Descripción')).toHaveTextContent('Descripción del registro 9962.')
    expect(field('Reportó')).toHaveTextContent('Daniela Ferrufino Soliz · 10/9 08:40')
    expect(field('Aprobó')).toHaveTextContent('Marcelo Quiroga Andrade · 22/9 10:00')
    expect(field('Ingresó a la Central')).toHaveTextContent('22/09/2026')
    expect(within(field('Evidencia')).getByRole('button', { name: 'foto-aula.png' })).toBeInTheDocument()
  })

  it('un registro sin aula, descripción ni evidencia lo indica', async () => {
    stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=9964')

    await screen.findByRole('heading', { name: 'Registro de la Central de Riesgo' })

    expect(screen.queryByText('Aula', { selector: 'dt' })).not.toBeInTheDocument()
    expect(field('Descripción')).toHaveTextContent('Sin descripción')
    expect(field('Evidencia')).toHaveTextContent('Sin evidencia')
  })

  it('la ficha va arriba y el motivo debajo; solo el motivo se puede escribir', async () => {
    stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=9962')

    const card = (await screen.findByRole('heading', { name: 'Registro de la Central de Riesgo' })).closest('div')!
    const form = screen.getByRole('form', { name: 'Rectificar registro' })

    expect(card.compareDocumentPosition(form) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
    expect(within(form).getAllByRole('textbox').map((control) => control.id)).toEqual(['rectify-reason'])
    expect(within(form).queryByRole('combobox')).not.toBeInTheDocument()
    expect(within(form).getByRole('button', { name: 'Cancelar' })).toBeInTheDocument()
    expect(within(form).getByRole('button', { name: 'Enviar solicitud al docente' })).toBeInTheDocument()
  })

  it('un registro ya rectificado no se puede abrir: muestra el error del servidor', async () => {
    stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=9961')

    expect(await screen.findByText('No existe el registro de la Central de Riesgo indicado.')).toBeInTheDocument()
    expect(screen.queryByRole('form', { name: 'Rectificar registro' })).not.toBeInTheDocument()
  })

  it('un registro inexistente muestra el error del servidor', async () => {
    stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=1234')

    expect(await screen.findByText('No existe el registro de la Central de Riesgo indicado.')).toBeInTheDocument()
  })
})

describe('RectifyRiskRecordPage — motivo y confirmación en dos pasos', () => {
  it('sin motivo muestra «El motivo es obligatorio» y no abre la confirmación', async () => {
    const user = userEvent.setup()
    const api = stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=9962')

    await screen.findByRole('form', { name: 'Rectificar registro' })
    await user.click(screen.getByRole('button', { name: 'Enviar solicitud al docente' }))

    expect(screen.getByText('El motivo es obligatorio')).toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(api.posts()).toHaveLength(0)
  })

  it('un motivo de espacios cuenta como vacío', async () => {
    const user = userEvent.setup()
    stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=9962')

    await screen.findByRole('form', { name: 'Rectificar registro' })
    await writeReason(user, '            ')
    await user.click(screen.getByRole('button', { name: 'Enviar solicitud al docente' }))

    expect(screen.getByText('El motivo es obligatorio')).toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('un motivo de menos de diez caracteres se rechaza', async () => {
    const user = userEvent.setup()
    stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=9962')

    await screen.findByRole('form', { name: 'Rectificar registro' })
    await writeReason(user, 'corto')
    await user.click(screen.getByRole('button', { name: 'Enviar solicitud al docente' }))

    expect(screen.getByText('El motivo debe tener al menos 10 caracteres.')).toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('«Enviar solicitud al docente» no guarda: abre la confirmación que nombra al estudiante, al registro y al docente', async () => {
    const user = userEvent.setup()
    const api = stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=9962')

    await screen.findByRole('form', { name: 'Rectificar registro' })

    const dialog = await review(user)

    expect(dialog).toHaveTextContent('Carla Condori Vega')
    expect(dialog).toHaveTextContent('«Conducta indebida»')
    expect(dialog).toHaveTextContent('«2do Parcial»')
    expect(dialog).toHaveTextContent('Marcelo Quiroga Andrade')
    expect(dialog).toHaveTextContent('seguirá activo en la Central de Riesgo hasta que el docente la acepte')
    expect(dialog).toHaveTextContent('vence en 7 días')
    expect(within(dialog).getByText(REASON)).toBeInTheDocument()
    expect(within(dialog).getByRole('button', { name: 'Cancelar' })).toBeInTheDocument()
    expect(within(dialog).getByRole('button', { name: 'Sí, enviar solicitud' })).toBeInTheDocument()
    expect(api.posts()).toHaveLength(0)
  })

  it('cancelar en el diálogo no guarda nada y deja el motivo escrito', async () => {
    const user = userEvent.setup()
    const api = stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=9962')

    await screen.findByRole('form', { name: 'Rectificar registro' })
    await user.click(within(await review(user)).getByRole('button', { name: 'Cancelar' }))

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(api.posts()).toHaveLength(0)
    expect(screen.getByLabelText(/motivo de la rectificación/i)).toHaveValue(REASON)
    expect(api.records.get(9962)?.rectificacion).toBeNull()
  })

  it('cancelar en el formulario vuelve a la lista sin guardar nada', async () => {
    const user = userEvent.setup()
    const api = stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=9962')

    await screen.findByRole('form', { name: 'Rectificar registro' })
    await writeReason(user)
    await user.click(screen.getByRole('button', { name: 'Cancelar' }))

    expect(await screen.findByRole('table', { name: 'Registros activos del estudiante' })).toBeInTheDocument()
    expect(location()).toBe('/rectificar-registro/9702')
    expect(api.posts()).toHaveLength(0)
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
    expect(within(screen.getByRole('table', { name: 'Registros activos del estudiante' })).getAllByRole('row')).toHaveLength(3)
  })

  it('«Volver a los registros» también vuelve sin guardar nada', async () => {
    const user = userEvent.setup()
    const api = stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=9962')

    await screen.findByRole('form', { name: 'Rectificar registro' })
    await user.click(screen.getByRole('button', { name: 'Volver a los registros' }))

    expect(await screen.findByRole('table', { name: 'Registros activos del estudiante' })).toBeInTheDocument()
    expect(api.posts()).toHaveLength(0)
  })

  it('al confirmar envía la solicitud con solo el motivo recortado y avisa que el registro sigue activo', async () => {
    const user = userEvent.setup()
    const api = stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=9962')

    await screen.findByRole('form', { name: 'Rectificar registro' })
    await user.click(within(await review(user, `  ${REASON}  `)).getByRole('button', { name: 'Sí, enviar solicitud' }))

    expect(await screen.findByRole('status')).toHaveTextContent(
      'Solicitud enviada a Marcelo Quiroga Andrade: el registro «Conducta indebida» de Carla Condori Vega sigue activo hasta que el docente la confirme.'
    )
    expect(api.posts()).toHaveLength(1)
    expect(new URL(api.posts()[0].url).pathname).toMatch(/\/registros\/9962\/solicitudes$/)
    expect(api.posts()[0].body).toEqual({ motivo: REASON })
    expect(location()).toBe('/rectificar-registro/9702')
  })

  it('tras enviar, el registro sigue en la lista activa «Esperando confirmación del docente» y nada se rectifica', async () => {
    const user = userEvent.setup()
    const api = stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=9962')

    await screen.findByRole('form', { name: 'Rectificar registro' })
    await user.click(within(await review(user)).getByRole('button', { name: 'Sí, enviar solicitud' }))

    const active = await screen.findByRole('table', { name: 'Registros activos del estudiante' })
    const rows = within(active).getAllByRole('row').slice(1)

    expect(rows).toHaveLength(2)
    expect(within(active).getAllByText('Esperando confirmación del docente')).toHaveLength(1)
    expect(api.records.get(9962)?.rectificacion).toBeNull()
    expect(
      within(screen.getByRole('table', { name: 'Registros rectificados del estudiante' })).getAllByRole('row')
    ).toHaveLength(2)
  })

  it('si el registro ya tiene una solicitud pendiente, muestra el conflicto, no avisa de éxito y permite volver', async () => {
    const user = userEvent.setup()
    stubRectificationApi({ conflict: true })
    renderPage('/rectificar-registro/9702?registro=9962')

    await screen.findByRole('form', { name: 'Rectificar registro' })
    await user.click(within(await review(user)).getByRole('button', { name: 'Sí, enviar solicitud' }))

    expect(
      await screen.findByText('Este registro ya tiene una solicitud de rectificación pendiente.')
    ).toBeInTheDocument()
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: 'Volver a los registros' }).length).toBeGreaterThan(0)
  })

  it('un error del servidor al enviar muestra su mensaje y no avisa de éxito', async () => {
    const user = userEvent.setup()
    stubRectificationApi({ requestFail: { status: 500, message: 'No se pudo enviar la solicitud ahora.' } })
    renderPage('/rectificar-registro/9702?registro=9962')

    await screen.findByRole('form', { name: 'Rectificar registro' })
    await user.click(within(await review(user)).getByRole('button', { name: 'Sí, enviar solicitud' }))

    expect(await screen.findByText('No se pudo enviar la solicitud ahora.')).toBeInTheDocument()
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
  })
})

const INACTIVE_SINCE = '2026-01-10T12:00:00+00:00'

function inactiveTeacher(overrides: { desde: string | null; puede: boolean }) {
  return {
    nombre_completo: 'Marcelo Quiroga Andrade',
    activo: false,
    inactivo_desde: overrides.desde === null ? null : INACTIVE_SINCE,
    fuente: overrides.desde === null ? null : ('ROL_CERRADO' as const),
    puede_rectificar_desde: overrides.desde,
    puede_rectificar_sin_confirmacion: overrides.puede,
  }
}

describe('RectifyRiskRecordPage — estado de las solicitudes', () => {
  it('con una solicitud pendiente muestra «Esperando confirmación del docente» y no ofrece enviar otra', async () => {
    stubRectificationApi({
      summaries: {
        9962: {
          solicitud_pendiente: {
            id_solicitud_rect: 9980,
            motivo: 'Motivo ya enviado al docente.',
            fecha_solicitud: '2026-11-20T15:00:00+00:00',
            vence_en: '2026-11-27T15:00:00+00:00',
            docente: { nombre_completo: 'Marcelo Quiroga Andrade' },
          },
        },
      },
    })
    renderPage('/rectificar-registro/9702?registro=9962')

    const panel = await screen.findByLabelText('Estado de la rectificación')

    expect(panel).toHaveTextContent('Esperando confirmación del docente')
    expect(panel).toHaveTextContent('Marcelo Quiroga Andrade')
    expect(panel).toHaveTextContent('Motivo ya enviado al docente.')
    expect(screen.queryByRole('form', { name: 'Rectificar registro' })).not.toBeInTheDocument()
  })

  it('muestra por separado los rechazos y las vencidas, y el último rechazo con su motivo y su imagen', async () => {
    stubRectificationApi({
      summaries: {
        9962: {
          rechazos: 2,
          vencidas: 1,
          ultimo_rechazo: {
            id_solicitud_rect: 9970,
            motivo: 'El registro está sustentado por dos auxiliares.',
            fecha: '2026-11-10T15:00:00+00:00',
            evidencia: { nombre_archivo: 'rechazo.png', tamano_bytes: 1024, disponible: true },
          },
        },
      },
    })
    renderPage('/rectificar-registro/9702?registro=9962')

    const panel = await screen.findByLabelText('Estado de la rectificación')

    expect(panel).toHaveTextContent('2 solicitudes rechazadas')
    expect(panel).toHaveTextContent('1 solicitud vencida')
    expect(panel).toHaveTextContent('El registro está sustentado por dos auxiliares.')
    expect(panel).toHaveTextContent('10/11/2026')
    expect(within(panel).getByRole('button', { name: 'Descargar imagen del rechazo' })).toBeInTheDocument()
    expect(screen.getByRole('form', { name: 'Rectificar registro' })).toBeInTheDocument()
  })

  it('tras un rechazo o un vencimiento se puede enviar una solicitud nueva', async () => {
    const user = userEvent.setup()
    const api = stubRectificationApi({ summaries: { 9962: { rechazos: 3, vencidas: 2 } } })
    renderPage('/rectificar-registro/9702?registro=9962')

    await screen.findByRole('form', { name: 'Rectificar registro' })
    await user.click(within(await review(user)).getByRole('button', { name: 'Sí, enviar solicitud' }))

    await waitFor(() => expect(api.posts()).toHaveLength(1))
  })

  it('docente inactivo con fecha conocida y plazo sin cumplir: informa desde cuándo y no ofrece enviar ni anular', async () => {
    stubRectificationApi({
      summaries: { 9962: { docente: inactiveTeacher({ desde: '2026-07-09T12:00:00+00:00', puede: false }) } },
    })
    renderPage('/rectificar-registro/9702?registro=9962')

    const panel = await screen.findByLabelText('Estado de la rectificación')

    expect(panel).toHaveTextContent('ya no es docente activo')
    expect(panel).toHaveTextContent('No se le puede enviar la solicitud')
    expect(panel).toHaveTextContent('09/07/2026')
    expect(screen.queryByRole('form', { name: 'Rectificar registro' })).not.toBeInTheDocument()
  })

  it('docente inactivo pasado el plazo: anula directo con el mismo motivo y la confirmación en dos pasos', async () => {
    const user = userEvent.setup()
    const api = stubRectificationApi({
      summaries: { 9962: { docente: inactiveTeacher({ desde: '2026-07-09T12:00:00+00:00', puede: true }) } },
    })
    renderPage('/rectificar-registro/9702?registro=9962')

    await screen.findByRole('form', { name: 'Rectificar registro' })
    await writeReason(user)
    await user.click(screen.getByRole('button', { name: 'Anular registro' }))

    const dialog = await screen.findByRole('dialog', { name: '¿Anular este registro?' })

    expect(dialog).toHaveTextContent('más de 180 días')
    expect(dialog).toHaveTextContent('no se puede deshacer')
    expect(api.posts()).toHaveLength(0)

    await user.click(within(dialog).getByRole('button', { name: 'Sí, anular registro' }))

    expect(await screen.findByRole('status')).toHaveTextContent(
      'Registro rectificado: «Conducta indebida» de Carla Condori Vega ya no figura en la Central de Riesgo.'
    )
    expect(new URL(api.posts()[0].url).pathname).toMatch(/\/registros\/9962\/rectificar-sin-confirmacion$/)
    expect(api.posts()[0].body).toEqual({ motivo: REASON })
    expect(api.records.get(9962)?.rectificacion).not.toBeNull()
  })

  it('docente inactivo sin fecha conocida: avisa que se inicia el plazo y muestra el 409 con la fecha', async () => {
    const user = userEvent.setup()
    const api = stubRectificationApi({
      summaries: { 9962: { docente: inactiveTeacher({ desde: null, puede: false }) } },
    })
    renderPage('/rectificar-registro/9702?registro=9962')

    await screen.findByRole('form', { name: 'Rectificar registro' })
    await writeReason(user)
    await user.click(screen.getByRole('button', { name: 'Anular registro' }))

    const dialog = await screen.findByRole('dialog', { name: '¿Anular este registro?' })

    expect(dialog).toHaveTextContent('se iniciará el plazo de 180 días')
    expect(dialog).toHaveTextContent('el registro no se anulará todavía')

    await user.click(within(dialog).getByRole('button', { name: 'Sí, continuar' }))

    expect(await screen.findByText(/podrá anular el registro desde el 20\/05\/2027/)).toBeInTheDocument()
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
    expect(api.records.get(9962)?.rectificacion).toBeNull()
  })
})
