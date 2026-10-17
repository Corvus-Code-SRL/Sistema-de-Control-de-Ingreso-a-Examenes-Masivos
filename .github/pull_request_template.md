## Título

Debe seguir Conventional Commits, por ejemplo: `feat(subjects): asigna materias por período (HU-006)`.

> Quien hace el merge debe conservar ese formato en el título del squash.

## HU y resumen

- **HU / RNF:** HU-XXX
- **Qué cambia y por qué:**

## Checklist

- [ ] Commits en Conventional Commits, en español imperativo, minúsculas, ≤50 caracteres y cuerpo `Refs: HU-XXX`.
- [ ] Rama actualizada sobre `develop`.
- [ ] Suite completa del backend (`sciem_test`) y vitest + `npm run build` pasando. Conteos: backend ___ tests, vitest ___ tests.
- [ ] Criterios de aceptación de la HU cubiertos (criterio → test):
  - criterio 1 → `test_...`
- [ ] ¿Migraciones nuevas? Listarlas aquí (después del merge, una sola persona las aplica también en Supabase):
  - ninguna
- [ ] ¿Dependencias nuevas? Indicar si hace falta `npm ci` o `composer install`:
  - ninguna
- [ ] Componentes de UI solo en `components/ui` (shadcn), con tokens de diseño y sin colores hex.
- [ ] Sin `.env`, credenciales, `node_modules` ni `vendor`.
- [ ] Ids de datos de prueba agregados en `TestDataIds` sin colisiones.
