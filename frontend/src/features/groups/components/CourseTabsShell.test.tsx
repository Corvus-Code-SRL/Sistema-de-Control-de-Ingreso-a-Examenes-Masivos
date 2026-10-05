import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'

import {
  makeForeignGroup,
  makeGroup,
  periodoActivo,
} from '@/test/fixtures'
import { mockApiOnce } from '@/test/http'
import { renderWithRouter } from '@/test/render'

import { CourseTabsShell } from './CourseTabsShell'

const subjectName = 'Bases de Datos I'

describe('CourseTabsShell', () => {
  it('muestra la carga de nómina cuando el grupo es propio, activo y del período activo', () => {
    renderWithRouter(
      <CourseTabsShell
        group={makeGroup({ es_mio: true, activo: true })}
        subjectName={subjectName}
        meta={{ id_periodo_activo: periodoActivo.id_periodo, es_periodo_activo: true }}
        onReload={vi.fn()}
      />
    )

    expect(screen.getByText('Cargar nómina')).toBeInTheDocument()
  })

  it('oculta la carga de nómina cuando el grupo es de otro docente', () => {
    renderWithRouter(
      <CourseTabsShell
        group={makeForeignGroup()}
        subjectName={subjectName}
        meta={{ id_periodo_activo: periodoActivo.id_periodo, es_periodo_activo: true }}
        onReload={vi.fn()}
      />
    )

    expect(screen.queryByText('Cargar nómina')).not.toBeInTheDocument()
  })

  it('oculta la carga de nómina cuando el grupo está inactivo', () => {
    renderWithRouter(
      <CourseTabsShell
        group={makeGroup({ activo: false })}
        subjectName={subjectName}
        meta={{ id_periodo_activo: periodoActivo.id_periodo, es_periodo_activo: true }}
        onReload={vi.fn()}
      />
    )

    expect(screen.queryByText('Cargar nómina')).not.toBeInTheDocument()
  })

  it('oculta la carga de nómina cuando el período no está activo', () => {
    renderWithRouter(
      <CourseTabsShell
        group={makeGroup({ es_mio: true, activo: true })}
        subjectName={subjectName}
        meta={{ id_periodo_activo: periodoActivo.id_periodo, es_periodo_activo: false }}
        onReload={vi.fn()}
      />
    )

    expect(screen.queryByText('Cargar nómina')).not.toBeInTheDocument()
  })

  it('muestra las tres pestañas siempre', () => {
    renderWithRouter(
      <CourseTabsShell
        group={makeGroup()}
        subjectName={subjectName}
        meta={{ id_periodo_activo: periodoActivo.id_periodo, es_periodo_activo: true }}
        onReload={vi.fn()}
      />
    )

    expect(screen.getByText('Nómina')).toBeInTheDocument()
    expect(screen.getByText('Auxiliares')).toBeInTheDocument()
    expect(screen.getByText('Exámenes')).toBeInTheDocument()
  })

  it('muestra un grupo sin nómina como caso normal, no como error', () => {
    renderWithRouter(
      <CourseTabsShell
        group={makeGroup({ cantidad_estudiantes: 0 })}
        subjectName={subjectName}
        meta={{ id_periodo_activo: periodoActivo.id_periodo, es_periodo_activo: true }}
        onReload={vi.fn()}
      />
    )

    // El aviso "Sin nómina cargada" vive dentro del panel o del empty state
    expect(screen.getByText('Cargar nómina')).toBeInTheDocument()
  })

  it('la pestaña Exámenes carga los exámenes del grupo', async () => {
    mockApiOnce({
      body: {
        data: [
          {
            id_examen: 41,
            nombre_examen: 'Primer parcial',
            fecha: '2026-10-14',
            hora_inicio: '08:00',
            hora_fin: '09:30',
            estado: 'PROGRAMADO',
          },
        ],
      },
    })

    renderWithRouter(
      <CourseTabsShell
        group={makeGroup({ id_grupo: 100 })}
        subjectName={subjectName}
        meta={{ id_periodo_activo: periodoActivo.id_periodo, es_periodo_activo: true }}
        onReload={vi.fn()}
      />
    )
    await userEvent.click(screen.getByRole('tab', { name: /exámenes/i }))

    expect(await screen.findByRole('link', { name: /primer parcial/i })).toHaveAttribute(
      'href',
      '/examenes/41'
    )
  })

  it('la pestaña Auxiliares carga los auxiliares del grupo', async () => {
    mockApiOnce({ body: { data: [] } })

    renderWithRouter(
      <CourseTabsShell
        group={makeGroup({ id_grupo: 100 })}
        subjectName={subjectName}
        meta={{ id_periodo_activo: periodoActivo.id_periodo, es_periodo_activo: true }}
        onReload={vi.fn()}
      />
    )
    await userEvent.click(screen.getByRole('tab', { name: /auxiliares/i }))

    expect(await screen.findByText('Este grupo aún no tiene auxiliares')).toBeInTheDocument()
  })
})