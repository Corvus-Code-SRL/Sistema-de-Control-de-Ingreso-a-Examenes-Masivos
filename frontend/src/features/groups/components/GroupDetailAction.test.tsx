import { screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { GroupDetailAction } from './GroupDetailAction'
import { makeForeignGroup, makeGroup, makeGroupWithoutRoster } from '@/test/fixtures'
import { renderWithRouter } from '@/test/render'

describe('GroupDetailAction', () => {
  it('ofrece el detalle de un grupo propio', () => {
    renderWithRouter(<GroupDetailAction group={makeGroup()} />)

    const link = screen.getByRole('link', { name: /ver detalles del grupo 1/i })

    expect(link).toHaveAttribute('href', '/cursos/100')
  })

  it('no ofrece el detalle de un grupo de otro docente', () => {
    renderWithRouter(<GroupDetailAction group={makeForeignGroup()} />)

    expect(screen.queryByRole('link')).not.toBeInTheDocument()
    expect(screen.getByText('Solo lectura')).toBeInTheDocument()
  })

  it('ofrece el detalle de un grupo propio aunque no tenga nómina', () => {
    renderWithRouter(<GroupDetailAction group={makeGroupWithoutRoster()} />)

    expect(screen.getByRole('link', { name: /ver detalles del grupo 3/i })).toHaveAttribute(
      'href',
      '/cursos/300'
    )
  })
})
