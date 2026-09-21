import { Navigate, type RouteObject } from 'react-router'
import AppShell from '@/components/layout/AppShell'
import PlaceholderPage from '@/components/layout/PlaceholderPage'
import { navByRole, type NavItem } from '@/config/navigation'

function toRoute(item: NavItem): RouteObject {
  const Page = item.page
  const element = Page ? <Page /> : <PlaceholderPage title={item.label} />
  return item.path === '/' ? { index: true, element } : { path: item.path, element }
}

// Las rutas se generan desde la config; si dos roles comparten path se registra una sola vez.
function buildChildren(): RouteObject[] {
  const byPath = new Map<string, NavItem>()
  for (const item of Object.values(navByRole).flat()) {
    if (!byPath.has(item.path)) byPath.set(item.path, item)
  }
  return [...byPath.values()].map(toRoute)
}

export const routes: RouteObject[] = [
  {
    path: '/',
    element: <AppShell />,
    children: [...buildChildren(), { path: '*', element: <Navigate to="/" replace /> }],
  },
]
