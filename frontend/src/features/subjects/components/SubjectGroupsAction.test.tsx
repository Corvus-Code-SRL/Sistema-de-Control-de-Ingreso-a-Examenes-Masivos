import { screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { SubjectGroupsAction } from './SubjectGroupsAction'
import { informatica, makeSubject } from '@/test/fixtures'
import { renderWithRouter } from '@/test/render'

describe('SubjectGroupsAction', () => {
  it('lleva a los grupos del par, nunca de la materia sola', () => {
    renderWithRouter(<SubjectGroupsAction subject={makeSubject()} />)

    expect(screen.getByRole('link', { name: /ver grupos/i })).toHaveAttribute(
      'href',
      '/carreras/1/materias/10/grupos'
    )
  })

  it('distingue la misma materia en dos carreras', () => {
    const enInformatica = makeSubject({
      id_carrera: informatica.id_carrera,
      carrera: informatica,
    })

    renderWithRouter(<SubjectGroupsAction subject={enInformatica} />)

    const link = screen.getByRole('link')

    expect(link).toHaveAttribute('href', '/carreras/2/materias/10/grupos')
    expect(link).toHaveAccessibleName(/ingenieria informatica/i)
  })

  it('no ofrece acción sobre una materia sin grupos del docente', () => {
    renderWithRouter(
      <SubjectGroupsAction subject={makeSubject({ es_mia: false, cantidad_grupos: 0 })} />
    )

    expect(screen.queryByRole('link')).not.toBeInTheDocument()
    expect(screen.getByText('Sin grupos a su cargo')).toBeInTheDocument()
  })

  it('no ofrece acción sobre una materia inactiva', () => {
    renderWithRouter(<SubjectGroupsAction subject={makeSubject({ activa: false })} />)

    expect(screen.queryByRole('link')).not.toBeInTheDocument()
    expect(screen.getByText('No seleccionable')).toBeInTheDocument()
  })

  it('prioriza el estado inactivo sobre la propiedad de la materia', () => {
    renderWithRouter(
      <SubjectGroupsAction subject={makeSubject({ activa: false, es_mia: true })} />
    )

    expect(screen.getByText('No seleccionable')).toBeInTheDocument()
    expect(screen.queryByText('Sin grupos a su cargo')).not.toBeInTheDocument()
  })
})
