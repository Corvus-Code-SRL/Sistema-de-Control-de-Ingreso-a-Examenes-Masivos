// API pública del feature auth
export { AuthProvider } from './components/AuthProvider'
export { ProtectedRoute } from './components/ProtectedRoute'
export { SessionExpiredOverlay, LOGIN_PATH } from './components/SessionExpiredOverlay'
export { LoginPage } from './pages/LoginPage'
export { useAuth } from './hooks/useAuth'
export { useCurrentUser } from './hooks/useCurrentUser'
export { LOGIN_REDIRECT_KEY, safeDestination } from './hooks/useLoginForm'
export { areaForRole, capabilitiesOf, hasCapability } from './capabilities'
export { AREA_LABELS } from './types/auth.types'
export type { Capability } from './capabilities'
export type {
  Area,
  AuthState,
  AuthStatus,
  CurrentUser,
  LoginFailure,
  LoginFailureKind,
  SessionRole,
  SessionUser,
} from './types/auth.types'
