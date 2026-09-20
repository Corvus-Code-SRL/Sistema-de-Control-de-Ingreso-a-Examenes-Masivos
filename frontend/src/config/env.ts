/**
 * Configuración de entorno del cliente.
 *
 * Se lee una sola vez y desde un único sitio: ningún módulo accede a
 * `import.meta.env` por su cuenta.
 */
export const env = {
  apiUrl: import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api',
} as const
