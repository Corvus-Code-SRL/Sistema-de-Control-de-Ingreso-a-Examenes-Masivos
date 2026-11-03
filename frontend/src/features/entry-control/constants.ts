/** Intervalo de consulta en tiempo real del control de ingreso (HU-032). */
export const ENTRY_CONTROL_POLL_MS = 2000
/** Intervalo de consulta de las solicitudes de ingreso con retraso (HU-037), con el examen abierto. */
export const LATE_ENTRY_POLL_MS = 5000
/** Mayor demora que acepta setTimeout; más allá se recalcula al volver a cargar. */
export const MAX_TIMEOUT_MS = 2_147_483_647
