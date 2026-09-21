export interface NavStatus {
  incidentCount: number
  entryControlOpen: boolean
  examLive: boolean
}

// TODO: obtener estos valores del backend (incidencias pendientes y fase del examen).
export const defaultNavStatus: NavStatus = {
  incidentCount: 0,
  entryControlOpen: false,
  examLive: false,
}
