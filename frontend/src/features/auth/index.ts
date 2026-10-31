// API pública del feature auth
export { AuthProvider } from './components/AuthProvider'
export { ProtectedRoute } from './components/ProtectedRoute'
export { LoginPage } from './pages/LoginPage'
export { useAuth } from './hooks/useAuth'
export { useCurrentUser } from './hooks/useCurrentUser'
export { LOGIN_REDIRECT_KEY } from './hooks/useLoginForm'
export { CambiarContrasenaPage } from './pages/CambiarContrasenaPage'
export {
  CHANGE_PASSWORD_PATH,
  LOGIN_PATH,
  homeForRole,
  resolveDestination,
  safeDestination,
} from './lib/destination'
export { isRecognisedSis } from './lib/sis'
export { ACCESS_BY_FEATURE, areaForRole, capabilitiesOf, hasCapability } from './capabilities'
export { AREA_LABELS } from './types/auth.types'
export type { AccessFeature, Capability } from './capabilities'
export type {
  Area,
  AuthState,
  AuthStatus,
  CurrentUser,
  LoginFailure,
  LoginFailureKind,
  ChangePasswordInput,
  SessionRole,
  SessionUser,
} from './types/auth.types'
