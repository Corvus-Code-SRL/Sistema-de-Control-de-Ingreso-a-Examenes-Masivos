import { screen, within } from '@testing-library/react'
import type { UserEvent } from '@testing-library/user-event'

/** Abre, una por una, las secciones contraídas del menú lateral (ya no hay un botón que las abra todas). */
export async function openAllSections(
  user: UserEvent,
  nav: HTMLElement = screen.getByRole('navigation', { name: 'Navegación principal' })
) {
  const closed = within(nav)
    .getAllByRole('heading', { level: 3 })
    .map((heading) => within(heading).getByRole('button'))
    .filter((toggle) => toggle.getAttribute('aria-expanded') === 'false')

  for (const toggle of closed) {
    await user.click(toggle)
  }
}
