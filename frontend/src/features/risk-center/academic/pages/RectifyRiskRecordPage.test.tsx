import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useLocation } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { renderWithRouter } from '@/test/render'
import { ADMIN_NAME, stubRectificationApi } from '@/test/riskRectificationFixtures'
import { RectifyRiskRecordPage } from './RectifyRiskRecordPage'

function LocationProbe() {
  const { pathname, search } = useLocation()

  return <output data-testid="location">{`${pathname}${search}`}</output>
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

async function search(user: ReturnType<typeof userEvent.setup>, term: string) {
  await user.type(screen.getByLabelText(/código sis, carnet de identidad o nombre/i), term)
  await user.click(screen.getByRole('button', { name: 'Buscar' }))
}

async function openRecord(user: ReturnType<typeof userEvent.setup>, exam: string) {
  await user.click(await screen.findByRole('button', { name: new RegExp(`Rectificar registro del examen ${exam}`) }))
}

async function chooseType(user: ReturnType<typeof userEvent.setup>, name: string) {
  await user.click(screen.getByRole('combobox', { name: 'Tipo de falta' }))
  await user.click(await screen.findByRole('option', { name }))
}

const REASON = 'Se confirmó que fue otra falta.'

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

  it('un código SIS con un solo estudiante abre directo sus registros, del más reciente al más antiguo', async () => {
    const user = userEvent.setup()
    stubRectificationApi()
    renderPage()

    await search(user, '202170103')

    const table = await screen.findByRole('table', { name: 'Registros confirmados del estudiante' })
    const rows = within(table).getAllByRole('row').slice(1)

    expect(location()).toBe('/rectificar-registro/9702')
    expect(screen.getByRole('heading', { name: 'Carla Condori Vega' })).toBeInTheDocument()
    expect(within(table).getAllByRole('columnheader').map((header) => header.textContent)).toEqual([
      'Fecha',
      'Examen',
      'Tipo de falta',
      'Aprobó',
      'Estado',
      'Acción',
    ])
    expect(rows).toHaveLength(2)
    expect(within(rows[0]).getByText('22/09/2026')).toBeInTheDocument()
    expect(within(rows[0]).getByText('2do Parcial')).toBeInTheDocument()
    expect(within(rows[0]).getByText('Conducta indebida')).toBeInTheDocument()
    expect(within(rows[0]).getByText('Marcelo Quiroga Andrade')).toBeInTheDocument()
    expect(within(rows[1]).getByText('11/09/2026')).toBeInTheDocument()
    expect(within(rows[0]).getByRole('button', { name: /rectificar/i })).toBeInTheDocument()
    expect(within(rows[1]).getByRole('button', { name: /rectificar/i })).toBeInTheDocument()
  })

  it('cada registro muestra su estado: «Original» o «Rectificado» con cuántas veces y la fecha de la última', async () => {
    const user = userEvent.setup()
    stubRectificationApi()
    renderPage()

    await search(user, '202170103')

    const rows = within(await screen.findByRole('table')).getAllByRole('row').slice(1)

    expect(within(rows[0]).getByText('Original')).toBeInTheDocument()
    expect(within(rows[0]).queryByText(/rectificaci/i)).not.toBeInTheDocument()
    expect(within(rows[1]).getByText('Rectificado')).toBeInTheDocument()
    expect(within(rows[1]).getByText('1 rectificación · última 05/10/2026')).toBeInTheDocument()
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

  it('un estudiante sin registros avisa «El estudiante no tiene registros en la Central de Riesgo»', async () => {
    const user = userEvent.setup()
    stubRectificationApi()
    renderPage()

    await search(user, '202170104')

    expect(await screen.findByText('El estudiante no tiene registros en la Central de Riesgo')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Daniel Rojas Mendoza' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /rectificar/i })).not.toBeInTheDocument()
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

  it('no ofrece ningún control para eliminar o borrar', async () => {
    stubRectificationApi()
    renderPage('/rectificar-registro/9702')

    await screen.findByRole('table')

    expect(screen.queryByRole('button', { name: /eliminar|borrar|quitar/i })).not.toBeInTheDocument()
  })
})

describe('RectifyRiskRecordPage — formulario y confirmación en dos pasos', () => {
  it('abre el formulario con el estudiante, el examen y las fechas bloqueados y el tipo y la descripción vigentes', async () => {
    const user = userEvent.setup()
    stubRectificationApi()
    renderPage('/rectificar-registro/9702')

    await openRecord(user, '2do Parcial')

    expect(location()).toBe('/rectificar-registro/9702?registro=9962')
    expect(await screen.findByRole('form', { name: 'Rectificar registro' })).toBeInTheDocument()
    expect(screen.getByText('Carla Condori Vega (202170103)')).toBeInTheDocument()
    expect(screen.getByText('2do Parcial · Cálculo I')).toBeInTheDocument()
    expect(screen.getByText('10/09/2026')).toBeInTheDocument()
    expect(screen.getByText('22/09/2026')).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Tipo de falta' })).toHaveTextContent('Conducta indebida')
    expect(screen.getByLabelText('Descripción')).toHaveValue('Descripción original del registro 9962.')
    expect(screen.getByLabelText(/motivo de la rectificación/i)).toHaveValue('')
  })

  it('sin motivo muestra «El motivo es obligatorio» y no abre la confirmación', async () => {
    const user = userEvent.setup()
    const api = stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=9962')

    await screen.findByRole('form', { name: 'Rectificar registro' })
    await chooseType(user, 'Copia en examen')
    await user.click(screen.getByRole('button', { name: 'Rectificar' }))

    expect(screen.getByText('El motivo es obligatorio')).toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(api.posts()).toHaveLength(0)
  })

  it('un motivo de menos de diez caracteres se rechaza', async () => {
    const user = userEvent.setup()
    stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=9962')

    await screen.findByRole('form', { name: 'Rectificar registro' })
    await chooseType(user, 'Copia en examen')
    await user.type(screen.getByLabelText(/motivo de la rectificación/i), 'corto')
    await user.click(screen.getByRole('button', { name: 'Rectificar' }))

    expect(screen.getByText('El motivo debe tener al menos 10 caracteres.')).toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('si no cambia ni el tipo ni la descripción lo rechaza sin consultar al servidor', async () => {
    const user = userEvent.setup()
    const api = stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=9962')

    await screen.findByRole('form', { name: 'Rectificar registro' })
    await user.type(screen.getByLabelText(/motivo de la rectificación/i), REASON)
    await user.click(screen.getByRole('button', { name: 'Rectificar' }))

    expect(screen.getByText(/No hay cambios: modifique el tipo de falta o la descripción/)).toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(api.posts()).toHaveLength(0)
  })

  it('«Rectificar» no guarda: abre la confirmación con lo anterior, lo nuevo y el motivo', async () => {
    const user = userEvent.setup()
    const api = stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=9962')

    await screen.findByRole('form', { name: 'Rectificar registro' })
    await chooseType(user, 'Copia en examen')
    await user.clear(screen.getByLabelText('Descripción'))
    await user.type(screen.getByLabelText('Descripción'), 'Descripción corregida.')
    await user.type(screen.getByLabelText(/motivo de la rectificación/i), REASON)
    await user.click(screen.getByRole('button', { name: 'Rectificar' }))

    const dialog = await screen.findByRole('dialog', { name: '¿Confirmar la rectificación?' })

    expect(within(dialog).getByText('Conducta indebida')).toBeInTheDocument()
    expect(within(dialog).getByText('Copia en examen')).toBeInTheDocument()
    expect(within(dialog).getByText('Descripción original del registro 9962.')).toBeInTheDocument()
    expect(within(dialog).getByText('Descripción corregida.')).toBeInTheDocument()
    expect(within(dialog).getByText(REASON)).toBeInTheDocument()
    expect(api.posts()).toHaveLength(0)
  })

  it('cancelar en el diálogo no guarda nada y deja el formulario como estaba', async () => {
    const user = userEvent.setup()
    const api = stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=9962')

    await screen.findByRole('form', { name: 'Rectificar registro' })
    await chooseType(user, 'Copia en examen')
    await user.type(screen.getByLabelText(/motivo de la rectificación/i), REASON)
    await user.click(screen.getByRole('button', { name: 'Rectificar' }))
    await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Cancelar' }))

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(api.posts()).toHaveLength(0)
    expect(screen.getByRole('combobox', { name: 'Tipo de falta' })).toHaveTextContent('Copia en examen')
    expect(screen.getByLabelText(/motivo de la rectificación/i)).toHaveValue(REASON)
    expect(api.details.get(9962)?.estado).toBe('Original')
  })

  it('cancelar en el formulario vuelve a la lista sin guardar nada', async () => {
    const user = userEvent.setup()
    const api = stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=9962')

    await screen.findByRole('form', { name: 'Rectificar registro' })
    await chooseType(user, 'Copia en examen')
    await user.type(screen.getByLabelText(/motivo de la rectificación/i), REASON)
    await user.click(screen.getByRole('button', { name: 'Cancelar' }))

    expect(await screen.findByRole('table', { name: 'Registros confirmados del estudiante' })).toBeInTheDocument()
    expect(location()).toBe('/rectificar-registro/9702')
    expect(api.posts()).toHaveLength(0)
    expect(within(screen.getAllByRole('row')[1]).getByText('Conducta indebida')).toBeInTheDocument()
  })

  it('al confirmar envía el cuerpo y muestra la versión nueva «Vigente» y la anterior «Anterior» con su fecha, autor y motivo', async () => {
    const user = userEvent.setup()
    const api = stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=9962')

    await screen.findByRole('form', { name: 'Rectificar registro' })
    await chooseType(user, 'Copia en examen')
    await user.clear(screen.getByLabelText('Descripción'))
    await user.type(screen.getByLabelText('Descripción'), '  Descripción corregida.  ')
    await user.type(screen.getByLabelText(/motivo de la rectificación/i), `  ${REASON}  `)
    await user.click(screen.getByRole('button', { name: 'Rectificar' }))
    await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Confirmar rectificación' }))

    expect(await screen.findByText('Rectificación registrada')).toBeInTheDocument()
    expect(api.posts()).toHaveLength(1)
    expect(api.posts()[0].body).toEqual({
      id_tipo_falta: 1,
      descripcion: 'Descripción corregida.',
      motivo: REASON,
      version_base: 1,
    })

    const versions = within(screen.getByRole('region', { name: 'Historial de versiones' })).getAllByRole('listitem')

    expect(versions).toHaveLength(2)
    expect(versions[0]).toHaveAccessibleName('Versión 2, Vigente')
    expect(within(versions[0]).getByText('Vigente')).toBeInTheDocument()
    expect(within(versions[0]).getByText(REASON)).toBeInTheDocument()
    expect(within(versions[0]).getByText(ADMIN_NAME)).toBeInTheDocument()
    expect(versions[1]).toHaveAccessibleName('Versión 1, Anterior')
    expect(within(versions[1]).getByText('Anterior')).toBeInTheDocument()
    expect(within(versions[1]).getByText('Marcelo Quiroga Andrade')).toBeInTheDocument()
    expect(within(versions[1]).getByText('Descripción original del registro 9962.')).toBeInTheDocument()
    expect(within(versions[1]).getByText('Conducta indebida')).toBeInTheDocument()
  })

  it('tras confirmar, al volver a la lista el registro figura como «Rectificado»', async () => {
    const user = userEvent.setup()
    stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=9962')

    await screen.findByRole('form', { name: 'Rectificar registro' })
    await chooseType(user, 'Copia en examen')
    await user.type(screen.getByLabelText(/motivo de la rectificación/i), REASON)
    await user.click(screen.getByRole('button', { name: 'Rectificar' }))
    await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Confirmar rectificación' }))
    await screen.findByText('Rectificación registrada')
    await user.click(screen.getByRole('button', { name: 'Volver a los registros' }))

    const rows = within(await screen.findByRole('table')).getAllByRole('row').slice(1)

    expect(within(rows[0]).getByText('Rectificado')).toBeInTheDocument()
    expect(within(rows[0]).getByText('Copia en examen')).toBeInTheDocument()
    expect(within(rows[0]).getByText('1 rectificación · última 14/11/2026')).toBeInTheDocument()
  })

  it('el historial de un registro ya rectificado va de la versión más nueva a la más antigua', async () => {
    stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=9961')

    const versions = within(await screen.findByRole('region', { name: 'Historial de versiones' })).getAllByRole(
      'listitem'
    )

    expect(versions.map((version) => version.getAttribute('aria-label'))).toEqual([
      'Versión 2, Vigente',
      'Versión 1, Anterior',
    ])
    expect(within(versions[0]).getByText('La evidencia confirma que se trataba de copia.')).toBeInTheDocument()
    expect(within(versions[1]).getByText('Registro original, sin rectificación')).toBeInTheDocument()
    expect(screen.getByText('1 rectificación · última 05/10/2026')).toBeInTheDocument()
  })

  it('un registro nunca rectificado muestra su valor original como versión 1 vigente', async () => {
    stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=9962')

    const versions = within(await screen.findByRole('region', { name: 'Historial de versiones' })).getAllByRole(
      'listitem'
    )

    expect(versions).toHaveLength(1)
    expect(versions[0]).toHaveAccessibleName('Versión 1, Vigente')
  })

  it('si otra persona rectificó antes, muestra el conflicto y permite recargar el registro', async () => {
    const user = userEvent.setup()
    stubRectificationApi({ conflict: true })
    renderPage('/rectificar-registro/9702?registro=9962')

    await screen.findByRole('form', { name: 'Rectificar registro' })
    await chooseType(user, 'Copia en examen')
    await user.type(screen.getByLabelText(/motivo de la rectificación/i), REASON)
    await user.click(screen.getByRole('button', { name: 'Rectificar' }))
    await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Confirmar rectificación' }))

    expect(await screen.findByText(/El registro fue rectificado mientras usted lo editaba/)).toBeInTheDocument()
    expect(screen.queryByText('Rectificación registrada')).not.toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Recargar el registro' })).toBeInTheDocument()
  })

  it('un registro inexistente muestra el error del servidor', async () => {
    stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=1234')

    expect(await screen.findByText('No existe el registro de la Central de Riesgo indicado.')).toBeInTheDocument()
  })

  it('el formulario ofrece solo el tipo de falta y la descripción para editar', async () => {
    stubRectificationApi()
    renderPage('/rectificar-registro/9702?registro=9962')

    const form = await screen.findByRole('form', { name: 'Rectificar registro' })

    expect(within(form).getAllByRole('textbox').map((field) => field.id)).toEqual([
      'rectify-description',
      'rectify-reason',
    ])
    expect(within(form).getAllByRole('combobox')).toHaveLength(1)
  })
})
