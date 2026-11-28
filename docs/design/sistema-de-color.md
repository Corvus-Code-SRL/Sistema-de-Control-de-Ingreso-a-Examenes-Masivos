# Sistema de color de SCIEM

Versión 1.1.0 · Requerimiento RNF-19 · Fuente de verdad: `frontend/src/index.css`

## 1. Propósito y alcance

Este documento describe el sistema de color de la interfaz web de SCIEM: cómo están organizados los tokens de diseño, qué reglas rigen su uso, qué valor tiene cada token en el tema claro y en el oscuro, qué contrastes se han verificado y cómo funciona el modo oscuro. Sirve como referencia para el desarrollo del frontend y como insumo del documento de estándares y de los manuales del proyecto.

El alcance comprende la aplicación React (`frontend/`). Quedan fuera los informes PDF y XLSX, que el backend genera con plantillas propias y no dependen de este sistema, y la impresión, que siempre usa el tema claro.

Los valores aquí publicados se comprueban de forma automática contra `frontend/src/index.css` (sección 7.1). Si un valor cambia en el código y no en este documento, las pruebas fallan.

## 2. Arquitectura de tokens

Los colores se organizan en tres capas. Cada capa solo referencia a la anterior; ningún componente usa valores de color escritos a mano.

### 2.1. Capa 1: paleta cruda

Variables `--sciem-*` declaradas en `:root`. Es el único lugar del código donde existen valores de color literales (hex, `rgba`). Tiene dos bloques: el del tema claro y el del tema oscuro, que solo redefine estas mismas variables. Muchos tokens del tema claro son alias de otros (por ejemplo `--sciem-brand-fg: var(--sciem-brand)`), de modo que cambiar un valor actualiza todo lo que depende de él.

### 2.2. Capa 2: semántica de shadcn

Variables con los nombres que esperan los componentes de shadcn/ui (`--background`, `--primary`, `--border`, `--sidebar`…). Cada una referencia un token de la capa 1 y ninguna contiene valores literales. Esta capa no se redefine en el tema oscuro: cambia por cascada.

### 2.3. Capa 3: utilidades de Tailwind

El bloque `@theme inline` expone los tokens como utilidades de Tailwind CSS 4: `--color-brand` genera `bg-brand`, `text-brand`, `border-brand`, etc.; `--shadow-*`, `--radius-*` y `--spacing-topbar` generan `shadow-*`, `rounded-*` y `h-topbar`. Los componentes solo usan estas utilidades.

### 2.4. Diagrama

```
Capa 1 (paleta cruda, únicos literales)
  :root                         --sciem-brand: #005E68
  @media screen and (prefers-color-scheme: dark)
    :root                       --sciem-brand: #2E7F82
        |
        v
Capa 2 (semántica shadcn, solo referencias)
  :root                         --primary: var(--sciem-brand)
        |
        v
Capa 3 (@theme inline de Tailwind)
  --color-primary: var(--primary)   ->  bg-primary, text-primary, border-primary
        |
        v
Componentes (solo utilidades)   <Button className="bg-primary text-on-solid">
```

## 3. Reglas de uso

### 3.1. Prohibido escribir colores a mano

Ningún componente, página ni clase CSS usa valores hex, `rgb()`, `hsl()` u `oklch()`, utilidades `white` o `black`, ni la paleta por defecto de Tailwind (`gray-500`, `slate-800`…). Todo color sale de una utilidad generada por la capa 3. La única excepción son los dos `theme-color` de `index.html`, que replican `--sciem-bg-app` porque HTML no puede leer variables CSS (sección 6.3); una prueba comprueba que coincidan.

### 3.2. Teal para acciones y selección; ámbar para advertencias y contadores

El teal institucional (`brand`) identifica acciones y selección: botón principal, ítem activo del sidebar, opción resaltada de menús y listas, enlaces, foco. El ámbar (`accent`, `warn`) se reserva para advertencias, estados pendientes y contadores. No se usa ámbar para destacar, seleccionar ni decorar.

El color fuerte ocupa áreas pequeñas (botones, badges, iconos, el ítem activo). Las superficies grandes con significado de estado usan los fondos suaves (`*-soft`) o los tintes (`*-tint`).

### 3.3. Relleno frente a texto

Un mismo color no sirve a la vez como relleno con texto blanco y como texto sobre una superficie, sobre todo en el tema oscuro. Por eso cada familia separa ambos usos:

| Uso | Tokens | Ejemplo |
|---|---|---|
| Relleno sólido con texto blanco | `brand`, `brand-strong`, `danger-solid`, `ok-solid`, `neutral-solid` | `bg-danger-solid text-on-solid` |
| Texto o icono sobre una superficie | `brand-fg`, `brand-deep`, `danger`, `danger-fg`, `ok-fg`, `warn-fg`, `info`, `neutral-fg` | `text-brand-fg` |
| Icono, borde o indicador | `brand`, `ok`, `danger`, `warn` | `border-danger` |

`text-brand` no existe en el código: la marca como texto es `text-brand-fg`.

### 3.4. Conjuntos de estado

Cada estado (éxito, error, advertencia, información, neutro) ofrece un conjunto coherente:

| Sufijo | Función |
|---|---|
| `-soft` | Fondo de avisos, badges y filas con ese estado. |
| `-fg` | Texto sobre `-soft` y sobre superficies. |
| `-border` | Borde de avisos con ese estado. |
| `-mid` | Fondo intermedio, como la caja de un icono de estado. |
| `-tint` | Tinte de un panel grande (por ejemplo, el veredicto del control de ingreso). En claro equivale a `-soft` al 35 %; en oscuro es `-soft`. |
| `-solid` | Relleno sólido con texto blanco. |

Un aviso se compone siempre con el conjunto completo, por ejemplo `border-warn-border bg-warn-soft text-warn-fg`. Los badges de estado neutro (`bg-neutral-soft text-neutral-fg`) se usan cuando no hay nada que advertir, como un contador de pendientes en cero.

### 3.5. Texto sobre rellenos

`on-solid` (blanco) es el texto de todos los rellenos sólidos y `on-accent` (gris casi negro) el texto sobre ámbar, en ambos temas. Las variables de shadcn `--primary-foreground`, `--destructive-foreground`, `--sidebar-primary-foreground` y `--sidebar-accent-foreground` apuntan a `on-solid`, y `--accent-foreground` a `on-accent`.

### 3.6. Superficies y elevación

La elevación se expresa por luminosidad y borde, no por sombra: `bg-app` (fondo) < `surface` (tarjetas) <= `raised` (popovers, menús, diálogos). En el tema claro `surface` y `raised` coinciden; en el oscuro, `raised` es más claro. Toda tarjeta, panel, popover y diálogo conserva un borde de 1 px con `border-border` o `ring-border`. `sunken` marca zonas hundidas dentro de una tarjeta. `muted` (`bg-muted`) es la superficie sutil de hover, skeleton y pestañas, y en el tema oscuro es más clara que `surface` y que `raised` para que el hover se vea en cualquier capa.

El nombre `muted` tiene dos sentidos: en shadcn es esa superficie y en la paleta es el gris de texto. Por eso el gris de texto se usa siempre como `text-muted-foreground`; `text-muted` está prohibido.

### 3.7. Campos de formulario

Todos los campos (input, select, textarea, grupo de input, selectores de fecha y hora, selects nativos y el buscador de selección) comparten fondo `bg-field` y borde `border-input`. Los deshabilitados usan `bg-dis-bg`. Ningún campo cambia de fondo al pasar el cursor.

### 3.8. Foco

El foco de los controles combina un borde sólido `border-ring` (marca como texto) y un halo `ring-focus`, ambos con al menos 3:1 contra el fondo adyacente. Dentro del sidebar el anillo es `ring-sidebar-ring` (`sb-focus`). Nunca se quita el contorno sin reemplazarlo.

### 3.9. Scrim y sombras

`bg-scrim` es el velo de diálogos y paneles laterales. Las sombras `shadow-xs` a `shadow-lg` reproducen las de Tailwind en el tema claro y se oscurecen en el oscuro; `shadow-overlay` eleva avisos flotantes y `shadow-sticky` marca la barra fija inferior del login en móvil.

### 3.10. Sidebar

El sidebar es oscuro en ambos temas. En el tema oscuro pertenece a la misma familia neutra que el fondo de la aplicación (mismo tono y saturación, más oscuro), su texto usa los mismos tokens que el contenido y su único acento fuerte es el ítem activo, con relleno de marca y texto blanco. El logotipo provisional usa `bg-sidebar-primary text-sidebar-primary-foreground`, que equivale a marca con texto blanco, igual que en la pantalla de ingreso.

### 3.11. Barras de desplazamiento

Todas las barras, incluida la de la página, son finas en ambos temas: pulgar `--sciem-scrollbar-thumb`, pulgar en hover `--sciem-scrollbar-thumb-hover` y carril transparente. Chromium y Safari usan los pseudoelementos `::-webkit-scrollbar` (8 px, pulgar redondeado); Firefox usa `scrollbar-width: thin` y `scrollbar-color`, aplicados solo donde no existen los pseudoelementos para no anular el hover en Chromium.

### 3.12. Dimensiones

`--sciem-topbar-height` fija la altura común de la barra superior del contenido y de la cabecera del sidebar (`h-topbar`), de modo que ambas forman una sola línea continua.

### 3.13. Cómo agregar o cambiar un token

1. Decidir si el color ya existe. Si un token cubre el uso, se reutiliza; no se crean variantes por pantalla.
2. Declarar el token en la capa 1 del tema claro, preferentemente como alias de uno existente (`var(--sciem-…)`), con un comentario breve de su propósito.
3. Si el valor debe cambiar en el tema oscuro, declararlo en el bloque oscuro con su propio valor; si no, no se repite.
4. Si los componentes de shadcn lo necesitan, mapearlo en la capa 2. Si lo usan los componentes propios, exponerlo en la capa 3 como `--color-…` (o `--shadow-…`, `--radius-…`).
5. Copiar el contenido de `index.css` posterior a los `@import` en `docs/design/sciem-tokens.css`.
6. Agregar la fila correspondiente a las tablas de la sección 4 y, si el token se combina como texto o fondo, el par a `CONTRAST_PAIRS` (`frontend/src/test/designTokens.ts`) y a la tabla de la sección 5.
7. Ejecutar `npm run test`: las pruebas de la sección 7.1 indican cualquier valor desincronizado o contraste insuficiente. Para cambiar un valor existente se siguen los pasos 2, 3, 5, 6 y 7.

## 4. Tabla completa de tokens

Los valores se muestran tal como están escritos en `index.css`; un valor `var(--…)` es un alias. «Igual que el claro» indica que el tema oscuro no redefine el token.

### 4.1. Capa 1: paleta cruda


#### 4.1.1. Marca

| Token | Claro | Oscuro | Propósito | Utilidad |
|---|---|---|---|---|
| `--sciem-brand` | `#005E68` | `#2E7F82` | Relleno de marca: botón principal, ítem activo del sidebar, logotipo, barras de progreso. También borde de marca. | `bg-brand`, `border-brand`, `bg-primary` |
| `--sciem-brand-hover` | `#00555E` | `#2A7376` | Relleno de marca al pasar el cursor. | `bg-brand-hover` |
| `--sciem-brand-active` | `#004B53` | `#25686B` | Relleno de marca al presionar. | `bg-brand-active` |
| `--sciem-brand-soft` | `#D8ECEE` | `#223635` | Fondo suave de marca: elemento seleccionado, chip, botón secundario. | `bg-brand-soft`, `bg-secondary` |
| `--sciem-brand-deep` | `#05383E` | `#89BFBE` | Texto de marca sobre `brand-soft` y cifras destacadas. | `text-brand-deep`, `text-secondary-foreground` |
| `--sciem-brand-fg` | `var(--sciem-brand)` | `#6BB8B9` | Marca usada como texto o icono sobre superficies: enlaces, acciones de texto, borde de foco (`--ring`). | `text-brand-fg`, `border-ring` |
| `--sciem-brand-strong` | `var(--sciem-brand-deep)` | `var(--sciem-brand-active)` | Relleno de marca intenso para un elemento seleccionado con texto blanco. | `bg-brand-strong`, `border-brand-strong` |

#### 4.1.2. Acento y texto sobre rellenos

| Token | Claro | Oscuro | Propósito | Utilidad |
|---|---|---|---|---|
| `--sciem-accent` | `#FFCB32` | `#FFCB32` | Ámbar: solo advertencias y contadores (botón de advertencia, contador del menú, barra de déficit). | `bg-accent-brand`, `bg-accent` |
| `--sciem-accent-hover` | `#E8B720` | `#E8B720` | Ámbar al pasar el cursor. | `bg-accent-brand-hover` |
| `--sciem-on-solid` | `#FFFFFF` | `#FFFFFF` | Texto e iconos sobre rellenos sólidos (marca, peligro, éxito, neutro). | `text-on-solid`, `text-primary-foreground` |
| `--sciem-on-accent` | `#2C2C2C` | `#2C2C2C` | Texto e iconos sobre ámbar. | `text-on-accent`, `text-accent-foreground` |

#### 4.1.3. Superficies, bordes y campos

| Token | Claro | Oscuro | Propósito | Utilidad |
|---|---|---|---|---|
| `--sciem-bg-app` | `#F3F8F8` | `#121817` | Fondo de la aplicación y de la barra superior. Lo replica `theme-color` en `index.html`. | `bg-bg-app`, `bg-background` |
| `--sciem-surface` | `#FFFFFF` | `#1A2322` | Tarjetas y paneles. | `bg-surface`, `bg-card` |
| `--sciem-sunken` | `#F7FAFA` | `#161F1E` | Zonas hundidas dentro de una tarjeta (bloques de cifras, listas). | `bg-sunken` |
| `--sciem-raised` | `var(--sciem-surface)` | `#212C2B` | Capas flotantes: popover, menú, diálogo, panel lateral. | `bg-raised`, `bg-popover` |
| `--sciem-muted-bg` | `var(--sciem-sunken)` | `#2A3938` | Fondo sutil de shadcn: hover de filas, botones fantasma, skeleton, lista de pestañas. | `bg-muted` |
| `--sciem-border` | `#DDDDDD` | `#2F4240` | Borde de 1 px de tarjetas, diálogos, popovers y separadores. | `border-border`, `border-border-soft`, `ring-border` |
| `--sciem-border-strong` | `#C7D2D3` | `#3D5653` | Borde decorativo más marcado; pulgar de las barras de desplazamiento. | `border-border-strong` |
| `--sciem-input-border` | `#7A9497` | `#5B7874` | Borde de controles de formulario (3:1 como mínimo). | `border-input` |
| `--sciem-field-bg` | `var(--sciem-surface)` | `#151C1B` | Fondo común de todos los campos: input, select, textarea, fecha y hora. | `bg-field` |

#### 4.1.4. Texto

| Token | Claro | Oscuro | Propósito | Utilidad |
|---|---|---|---|---|
| `--sciem-text` | `#2C2C2C` | `#E7EFED` | Texto principal. | `text-foreground`, `text-text` |
| `--sciem-muted` | `#616971` | `#A4B8B5` | Texto secundario. | `text-muted-foreground` |
| `--sciem-subtle` | `#677479` | `#849A97` | Texto terciario e iconos discretos. | `text-subtle` |

#### 4.1.5. Estado: éxito

| Token | Claro | Oscuro | Propósito | Utilidad |
|---|---|---|---|---|
| `--sciem-ok` | `#15803D` | `#64A883` | Icono, borde o indicador de éxito (no texto de cuerpo). | `text-ok`, `border-ok`, `bg-ok` |
| `--sciem-ok-soft` | `#DFF1E7` | `#233A2D` | Fondo de avisos y badges de éxito. | `bg-ok-soft` |
| `--sciem-ok-fg` | `#0F5C2C` | `#89BFA1` | Texto de éxito. | `text-ok-fg` |
| `--sciem-ok-border` | `#B6DEC6` | `#365944` | Borde de avisos de éxito. | `border-ok-border` |
| `--sciem-ok-mid` | `#C5E6D2` | `#2B4535` | Fondo intermedio (caja de icono de estado). | `bg-ok-mid` |
| `--sciem-ok-solid` | `var(--sciem-ok)` | `#2F7D57` | Relleno sólido de éxito con texto blanco (botón `success`). | `bg-ok-solid` |
| `--sciem-ok-solid-hover` | `var(--sciem-ok-fg)` | `#286C4A` | Relleno sólido de éxito al pasar el cursor o presionar. | `bg-ok-solid-hover` |
| `--sciem-ok-tint` | `color-mix(in oklab, var(--sciem-ok-soft) 35%, transparent)` | `var(--sciem-ok-soft)` | Tinte de fondo de un panel grande de éxito (veredicto de ingreso). | `bg-ok-tint` |

#### 4.1.6. Estado: error

| Token | Claro | Oscuro | Propósito | Utilidad |
|---|---|---|---|---|
| `--sciem-danger` | `#D92D20` | `#E07A7A` | Rojo como texto, icono o borde; base de `--destructive`. | `text-danger`, `border-danger`, `text-destructive` |
| `--sciem-danger-soft` | `#FDE2E1` | `#352222` | Fondo de avisos y badges de error. | `bg-danger-soft` |
| `--sciem-danger-fg` | `#A21B12` | `#E08A8A` | Texto de error, también en botones fantasma de peligro. | `text-danger-fg` |
| `--sciem-danger-border` | `#F4B7B1` | `#563232` | Borde de avisos de error. | `border-danger-border` |
| `--sciem-danger-solid` | `var(--sciem-danger)` | `#B54848` | Relleno sólido de peligro con texto blanco (botón `danger`). | `bg-danger-solid` |
| `--sciem-danger-tint` | `color-mix(in oklab, var(--sciem-danger-soft) 35%, transparent)` | `var(--sciem-danger-soft)` | Tinte de fondo de un panel grande de error. | `bg-danger-tint` |

#### 4.1.7. Estado: advertencia

| Token | Claro | Oscuro | Propósito | Utilidad |
|---|---|---|---|---|
| `--sciem-warn` | `#FFCB32` | `#FFCB32` | Ámbar sólido de advertencia (contadores, barra de déficit). | `bg-warn` |
| `--sciem-warn-soft` | `#FFF4D6` | `#322916` | Fondo de avisos y badges de advertencia o pendiente. | `bg-warn-soft` |
| `--sciem-warn-border` | `#F5DC8C` | `#4D3F24` | Borde de avisos de advertencia. | `border-warn-border` |
| `--sciem-warn-fg` | `#7A5B00` | `#D9AE5C` | Texto de advertencia. | `text-warn-fg` |
| `--sciem-warn-tint` | `color-mix(in oklab, var(--sciem-warn-soft) 35%, transparent)` | `var(--sciem-warn-soft)` | Tinte de fondo de un panel grande de advertencia. | `bg-warn-tint` |

#### 4.1.8. Estado: información y neutro

| Token | Claro | Oscuro | Propósito | Utilidad |
|---|---|---|---|---|
| `--sciem-info` | `#1D4E89` | `#89A2BF` | Texto e icono informativo. | `text-info` |
| `--sciem-info-soft` | `#E4EDF9` | `#232E3B` | Fondo de avisos informativos. | `bg-info-soft` |
| `--sciem-info-border` | `#BDD3EE` | `#364659` | Borde de avisos informativos. | `border-info-border` |
| `--sciem-info-mid` | `#CFE0F4` | `#2B3747` | Fondo intermedio informativo (caja de icono). | `bg-info-mid` |
| `--sciem-neutral-soft` | `#F3F5F5` | `#212E2C` | Fondo de estados que no dependen de la persona (inactivo, cero pendientes). | `bg-neutral-soft` |
| `--sciem-neutral-mid` | `#E3E7E8` | `#2F4240` | Fondo intermedio neutro (caja de icono). | `bg-neutral-mid` |
| `--sciem-neutral-fg` | `#4F5B62` | `#A4B8B5` | Texto de estado neutro. | `text-neutral-fg` |
| `--sciem-neutral-solid` | `var(--sciem-neutral-fg)` | `#5A6E6B` | Relleno sólido neutro con texto blanco. | `bg-neutral-solid` |

#### 4.1.9. Deshabilitado

| Token | Claro | Oscuro | Propósito | Utilidad |
|---|---|---|---|---|
| `--sciem-dis-text` | `#AAB8BA` | `#4F6461` | Texto deshabilitado (exento del mínimo WCAG). | `text-dis-text` |
| `--sciem-dis-bg` | `#F3F8F8` | `#161F1E` | Fondo de campos deshabilitados. | `bg-dis-bg` |
| `--sciem-dis-border` | `#DDDDDD` | `#2F4240` | Borde de elementos deshabilitados. | `border-dis-border` |

#### 4.1.10. Sidebar

| Token | Claro | Oscuro | Propósito | Utilidad |
|---|---|---|---|---|
| `--sciem-sb-bg` | `#05383E` | `#0C100F` | Fondo del sidebar; en oscuro, la superficie más oscura. | `bg-sidebar` |
| `--sciem-sb-border` | `#0F4A51` | `#192220` | Borde del sidebar y de su cabecera. | `border-sidebar-border` |
| `--sciem-sb-text` | `#D8ECEE` | `var(--sciem-text)` | Texto del sidebar. | `text-sidebar-foreground` |
| `--sciem-sb-sub` | `#8FC7CC` | `var(--sciem-muted)` | Texto secundario e iconos del sidebar. | `text-sb-sub` |
| `--sciem-sb-group` | `#7FB0B5` | `var(--sciem-subtle)` | Encabezados de grupo (overline). | `text-sb-group` |
| `--sciem-sb-divider` | `#24626A` | `#212C2A` | Línea guía de los ítems de una sección. | `border-sb-divider` |
| `--sciem-sb-hover` | `#0B474E` | `#171F1D` | Hover de ítems y sección abierta. | `bg-sb-hover` |
| `--sciem-sb-active` | `#1E5A61` | `#1F2927` | Fondo del avatar de la cuenta. | `bg-sidebar-accent` |
| `--sciem-sb-focus` | `var(--sciem-sb-sub)` | `var(--sciem-brand-fg)` | Anillo de foco dentro del sidebar. | `ring-sidebar-ring` |

#### 4.1.11. Barras de desplazamiento

| Token | Claro | Oscuro | Propósito | Utilidad |
|---|---|---|---|---|
| `--sciem-scrollbar-thumb` | `var(--sciem-border-strong)` | Igual que el claro | Pulgar en reposo. | — (CSS base) |
| `--sciem-scrollbar-thumb-hover` | `var(--sciem-input-border)` | Igual que el claro | Pulgar al pasar el cursor (Chromium y Safari). | — (CSS base) |
| `--sciem-scrollbar-track` | `transparent` | Igual que el claro | Carril (transparente). | — (CSS base) |

#### 4.1.12. Foco, scrim y sombras

| Token | Claro | Oscuro | Propósito | Utilidad |
|---|---|---|---|---|
| `--sciem-focus` | `#5E989F` | `#4B7B7B` | Halo de foco de los controles (3:1 contra el fondo adyacente). | `ring-focus`, `outline-focus` |
| `--focus-ring` | `0 0 0 2px var(--sciem-surface), 0 0 0 4px var(--sciem-focus)` | Igual que el claro | Sombra de foco compuesta, para elementos que no usan `ring`. | — (`box-shadow`) |
| `--sciem-scrim` | `rgba(0, 0, 0, .10)` | `rgba(0, 0, 0, .65)` | Velo detrás de diálogos y paneles laterales. | `bg-scrim` |
| `--sciem-shadow-xs` | `0 1px 2px 0 rgba(0, 0, 0, .05)` | `0 1px 2px 0 rgba(0, 0, 0, .25)` | Sombra mínima. | `shadow-xs` |
| `--sciem-shadow-sm` | `0 1px 3px 0 rgba(0, 0, 0, .10), 0 1px 2px -1px rgba(0, 0, 0, .10)` | `0 1px 3px 0 rgba(0, 0, 0, .30), 0 1px 2px -1px rgba(0, 0, 0, .30)` | Sombra pequeña. | `shadow-sm` |
| `--sciem-shadow-md` | `0 4px 6px -1px rgba(0, 0, 0, .10), 0 2px 4px -2px rgba(0, 0, 0, .10)` | `0 4px 6px -1px rgba(0, 0, 0, .40), 0 2px 4px -2px rgba(0, 0, 0, .40)` | Sombra media (menús). | `shadow-md` |
| `--sciem-shadow-lg` | `0 10px 15px -3px rgba(0, 0, 0, .10), 0 4px 6px -4px rgba(0, 0, 0, .10)` | `0 10px 15px -3px rgba(0, 0, 0, .50), 0 4px 6px -4px rgba(0, 0, 0, .50)` | Sombra grande (paneles flotantes). | `shadow-lg` |
| `--sciem-shadow-overlay` | `0 12px 32px rgba(5, 56, 62, .16)` | `0 12px 32px rgba(0, 0, 0, .50)` | Elevación de avisos flotantes. | `shadow-overlay` |
| `--sciem-shadow-sticky` | `0 -4px 12px color-mix(in srgb, var(--sciem-brand-deep) 6%, transparent)` | `0 -4px 12px rgba(0, 0, 0, .40)` | Sombra hacia arriba de la barra fija inferior del login en móvil. | `shadow-sticky` |

#### 4.1.13. Radios y dimensiones

| Token | Claro | Oscuro | Propósito | Utilidad |
|---|---|---|---|---|
| `--sciem-radius-sm` | `6px` | Igual que el claro | Radio pequeño. | `rounded-sm` |
| `--sciem-radius-md` | `8px` | Igual que el claro | Radio base de controles (`--radius`). | `rounded-md` |
| `--sciem-radius-lg` | `10px` | Igual que el claro | Radio de tarjetas y botones. | `rounded-lg` |
| `--sciem-radius-xl` | `14px` | Igual que el claro | Radio de paneles y diálogos. | `rounded-xl` |
| `--sciem-radius-pill` | `9999px` | Igual que el claro | Radio de píldora; pulgar de las barras de desplazamiento. | `rounded-pill` |
| `--sciem-topbar-height` | `2.5rem` | Igual que el claro | Altura común de la barra superior y de la cabecera del sidebar. | `h-topbar` |

### 4.2. Capa 2: semántica de shadcn

| Variable | Referencia | Propósito |
|---|---|---|
| `--background` | `var(--sciem-bg-app)` | Fondo de página. |
| `--foreground` | `var(--sciem-text)` | Texto principal. |
| `--card` | `var(--sciem-surface)` | Fondo de tarjetas. |
| `--card-foreground` | `var(--sciem-text)` | Texto en tarjetas. |
| `--popover` | `var(--sciem-raised)` | Fondo de popovers, menús, diálogos y paneles laterales. |
| `--popover-foreground` | `var(--sciem-text)` | Texto en capas flotantes. |
| `--primary` | `var(--sciem-brand)` | Relleno de acción principal y opción resaltada de menús. |
| `--primary-foreground` | `var(--sciem-on-solid)` | Texto sobre `primary`. |
| `--secondary` | `var(--sciem-brand-soft)` | Relleno de acción secundaria. |
| `--secondary-foreground` | `var(--sciem-brand-deep)` | Texto sobre `secondary`. |
| `--muted` | `var(--sciem-muted-bg)` | Superficie sutil (hover, skeleton, pestañas). |
| `--muted-foreground` | `var(--sciem-muted)` | Texto secundario. |
| `--accent` | `var(--sciem-accent)` | Ámbar de shadcn; no se usa para resaltar opciones. |
| `--accent-foreground` | `var(--sciem-on-accent)` | Texto sobre ámbar. |
| `--destructive` | `var(--sciem-danger)` | Rojo de acciones destructivas como texto, borde o tinte. |
| `--destructive-foreground` | `var(--sciem-on-solid)` | Texto sobre rojo sólido. |
| `--border` | `var(--sciem-border)` | Borde por defecto de todos los elementos. |
| `--input` | `var(--sciem-input-border)` | Borde de controles de formulario. |
| `--ring` | `var(--sciem-brand-fg)` | Borde de foco sólido. |
| `--radius` | `var(--sciem-radius-md)` | Radio base de shadcn. |
| `--sidebar` | `var(--sciem-sb-bg)` | Fondo del sidebar. |
| `--sidebar-foreground` | `var(--sciem-sb-text)` | Texto del sidebar. |
| `--sidebar-primary` | `var(--sciem-brand)` | Logotipo del sidebar. |
| `--sidebar-primary-foreground` | `var(--sciem-on-solid)` | Texto del logotipo. |
| `--sidebar-accent` | `var(--sciem-sb-active)` | Avatar de la cuenta. |
| `--sidebar-accent-foreground` | `var(--sciem-on-solid)` | Texto sobre el avatar y nombre de la cuenta. |
| `--sidebar-border` | `var(--sciem-sb-border)` | Bordes del sidebar. |
| `--sidebar-ring` | `var(--sciem-sb-focus)` | Foco dentro del sidebar. |

### 4.3. Capa 3: utilidades de Tailwind

| Variable | Referencia | Utilidades que genera |
|---|---|---|
| `--color-brand` | `var(--sciem-brand)` | `bg-brand`, `text-brand`, `border-brand` |
| `--color-brand-hover` | `var(--sciem-brand-hover)` | `bg-brand-hover`, `text-brand-hover`, `border-brand-hover` |
| `--color-brand-active` | `var(--sciem-brand-active)` | `bg-brand-active`, `text-brand-active`, `border-brand-active` |
| `--color-brand-soft` | `var(--sciem-brand-soft)` | `bg-brand-soft`, `text-brand-soft`, `border-brand-soft` |
| `--color-brand-deep` | `var(--sciem-brand-deep)` | `bg-brand-deep`, `text-brand-deep`, `border-brand-deep` |
| `--color-brand-fg` | `var(--sciem-brand-fg)` | `bg-brand-fg`, `text-brand-fg`, `border-brand-fg` |
| `--color-brand-strong` | `var(--sciem-brand-strong)` | `bg-brand-strong`, `text-brand-strong`, `border-brand-strong` |
| `--color-accent-brand` | `var(--sciem-accent)` | `bg-accent-brand`, `text-accent-brand`, `border-accent-brand` |
| `--color-accent-brand-hover` | `var(--sciem-accent-hover)` | `bg-accent-brand-hover`, `text-accent-brand-hover`, `border-accent-brand-hover` |
| `--color-on-solid` | `var(--sciem-on-solid)` | `bg-on-solid`, `text-on-solid`, `border-on-solid` |
| `--color-on-accent` | `var(--sciem-on-accent)` | `bg-on-accent`, `text-on-accent`, `border-on-accent` |
| `--color-bg-app` | `var(--sciem-bg-app)` | `bg-bg-app`, `text-bg-app`, `border-bg-app` |
| `--color-surface` | `var(--sciem-surface)` | `bg-surface`, `text-surface`, `border-surface` |
| `--color-sunken` | `var(--sciem-sunken)` | `bg-sunken`, `text-sunken`, `border-sunken` |
| `--color-raised` | `var(--sciem-raised)` | `bg-raised`, `text-raised`, `border-raised` |
| `--color-field` | `var(--sciem-field-bg)` | `bg-field`, `text-field`, `border-field` |
| `--color-border-soft` | `var(--sciem-border)` | `bg-border-soft`, `text-border-soft`, `border-border-soft` |
| `--color-border-strong` | `var(--sciem-border-strong)` | `bg-border-strong`, `text-border-strong`, `border-border-strong` |
| `--color-text` | `var(--sciem-text)` | `bg-text`, `text-text`, `border-text` |
| `--color-subtle` | `var(--sciem-subtle)` | `bg-subtle`, `text-subtle`, `border-subtle` |
| `--color-muted` | `var(--muted)` | `bg-muted`, `text-muted`, `border-muted` |
| `--color-ok` | `var(--sciem-ok)` | `bg-ok`, `text-ok`, `border-ok` |
| `--color-ok-soft` | `var(--sciem-ok-soft)` | `bg-ok-soft`, `text-ok-soft`, `border-ok-soft` |
| `--color-ok-fg` | `var(--sciem-ok-fg)` | `bg-ok-fg`, `text-ok-fg`, `border-ok-fg` |
| `--color-ok-border` | `var(--sciem-ok-border)` | `bg-ok-border`, `text-ok-border`, `border-ok-border` |
| `--color-ok-mid` | `var(--sciem-ok-mid)` | `bg-ok-mid`, `text-ok-mid`, `border-ok-mid` |
| `--color-ok-solid` | `var(--sciem-ok-solid)` | `bg-ok-solid`, `text-ok-solid`, `border-ok-solid` |
| `--color-ok-solid-hover` | `var(--sciem-ok-solid-hover)` | `bg-ok-solid-hover`, `text-ok-solid-hover`, `border-ok-solid-hover` |
| `--color-ok-tint` | `var(--sciem-ok-tint)` | `bg-ok-tint`, `text-ok-tint`, `border-ok-tint` |
| `--color-danger` | `var(--sciem-danger)` | `bg-danger`, `text-danger`, `border-danger` |
| `--color-danger-soft` | `var(--sciem-danger-soft)` | `bg-danger-soft`, `text-danger-soft`, `border-danger-soft` |
| `--color-danger-fg` | `var(--sciem-danger-fg)` | `bg-danger-fg`, `text-danger-fg`, `border-danger-fg` |
| `--color-danger-border` | `var(--sciem-danger-border)` | `bg-danger-border`, `text-danger-border`, `border-danger-border` |
| `--color-danger-solid` | `var(--sciem-danger-solid)` | `bg-danger-solid`, `text-danger-solid`, `border-danger-solid` |
| `--color-danger-tint` | `var(--sciem-danger-tint)` | `bg-danger-tint`, `text-danger-tint`, `border-danger-tint` |
| `--color-warn` | `var(--sciem-warn)` | `bg-warn`, `text-warn`, `border-warn` |
| `--color-warn-soft` | `var(--sciem-warn-soft)` | `bg-warn-soft`, `text-warn-soft`, `border-warn-soft` |
| `--color-warn-border` | `var(--sciem-warn-border)` | `bg-warn-border`, `text-warn-border`, `border-warn-border` |
| `--color-warn-fg` | `var(--sciem-warn-fg)` | `bg-warn-fg`, `text-warn-fg`, `border-warn-fg` |
| `--color-warn-tint` | `var(--sciem-warn-tint)` | `bg-warn-tint`, `text-warn-tint`, `border-warn-tint` |
| `--color-info` | `var(--sciem-info)` | `bg-info`, `text-info`, `border-info` |
| `--color-info-soft` | `var(--sciem-info-soft)` | `bg-info-soft`, `text-info-soft`, `border-info-soft` |
| `--color-info-border` | `var(--sciem-info-border)` | `bg-info-border`, `text-info-border`, `border-info-border` |
| `--color-info-mid` | `var(--sciem-info-mid)` | `bg-info-mid`, `text-info-mid`, `border-info-mid` |
| `--color-neutral-soft` | `var(--sciem-neutral-soft)` | `bg-neutral-soft`, `text-neutral-soft`, `border-neutral-soft` |
| `--color-neutral-mid` | `var(--sciem-neutral-mid)` | `bg-neutral-mid`, `text-neutral-mid`, `border-neutral-mid` |
| `--color-neutral-fg` | `var(--sciem-neutral-fg)` | `bg-neutral-fg`, `text-neutral-fg`, `border-neutral-fg` |
| `--color-neutral-solid` | `var(--sciem-neutral-solid)` | `bg-neutral-solid`, `text-neutral-solid`, `border-neutral-solid` |
| `--color-sb-sub` | `var(--sciem-sb-sub)` | `bg-sb-sub`, `text-sb-sub`, `border-sb-sub` |
| `--color-sb-group` | `var(--sciem-sb-group)` | `bg-sb-group`, `text-sb-group`, `border-sb-group` |
| `--color-sb-divider` | `var(--sciem-sb-divider)` | `bg-sb-divider`, `text-sb-divider`, `border-sb-divider` |
| `--color-sb-hover` | `var(--sciem-sb-hover)` | `bg-sb-hover`, `text-sb-hover`, `border-sb-hover` |
| `--color-dis-text` | `var(--sciem-dis-text)` | `bg-dis-text`, `text-dis-text`, `border-dis-text` |
| `--color-dis-bg` | `var(--sciem-dis-bg)` | `bg-dis-bg`, `text-dis-bg`, `border-dis-bg` |
| `--color-dis-border` | `var(--sciem-dis-border)` | `bg-dis-border`, `text-dis-border`, `border-dis-border` |
| `--color-focus` | `var(--sciem-focus)` | `bg-focus`, `text-focus`, `border-focus` |
| `--color-scrim` | `var(--sciem-scrim)` | `bg-scrim`, `text-scrim`, `border-scrim` |
| `--color-background` | `var(--background)` | `bg-background`, `text-background`, `border-background` |
| `--color-foreground` | `var(--foreground)` | `bg-foreground`, `text-foreground`, `border-foreground` |
| `--color-card` | `var(--card)` | `bg-card`, `text-card`, `border-card` |
| `--color-card-foreground` | `var(--card-foreground)` | `bg-card-foreground`, `text-card-foreground`, `border-card-foreground` |
| `--color-popover` | `var(--popover)` | `bg-popover`, `text-popover`, `border-popover` |
| `--color-popover-foreground` | `var(--popover-foreground)` | `bg-popover-foreground`, `text-popover-foreground`, `border-popover-foreground` |
| `--color-primary` | `var(--primary)` | `bg-primary`, `text-primary`, `border-primary` |
| `--color-primary-foreground` | `var(--primary-foreground)` | `bg-primary-foreground`, `text-primary-foreground`, `border-primary-foreground` |
| `--color-secondary` | `var(--secondary)` | `bg-secondary`, `text-secondary`, `border-secondary` |
| `--color-secondary-foreground` | `var(--secondary-foreground)` | `bg-secondary-foreground`, `text-secondary-foreground`, `border-secondary-foreground` |
| `--color-muted-foreground` | `var(--muted-foreground)` | `bg-muted-foreground`, `text-muted-foreground`, `border-muted-foreground` |
| `--color-accent` | `var(--accent)` | `bg-accent`, `text-accent`, `border-accent` |
| `--color-accent-foreground` | `var(--accent-foreground)` | `bg-accent-foreground`, `text-accent-foreground`, `border-accent-foreground` |
| `--color-destructive` | `var(--destructive)` | `bg-destructive`, `text-destructive`, `border-destructive` |
| `--color-destructive-foreground` | `var(--destructive-foreground)` | `bg-destructive-foreground`, `text-destructive-foreground`, `border-destructive-foreground` |
| `--color-border` | `var(--border)` | `bg-border`, `text-border`, `border-border` |
| `--color-input` | `var(--input)` | `bg-input`, `text-input`, `border-input` |
| `--color-ring` | `var(--ring)` | `bg-ring`, `text-ring`, `border-ring` |
| `--color-sidebar` | `var(--sidebar)` | `bg-sidebar`, `text-sidebar`, `border-sidebar` |
| `--color-sidebar-foreground` | `var(--sidebar-foreground)` | `bg-sidebar-foreground`, `text-sidebar-foreground`, `border-sidebar-foreground` |
| `--color-sidebar-primary` | `var(--sidebar-primary)` | `bg-sidebar-primary`, `text-sidebar-primary`, `border-sidebar-primary` |
| `--color-sidebar-primary-foreground` | `var(--sidebar-primary-foreground)` | `bg-sidebar-primary-foreground`, `text-sidebar-primary-foreground`, `border-sidebar-primary-foreground` |
| `--color-sidebar-accent` | `var(--sidebar-accent)` | `bg-sidebar-accent`, `text-sidebar-accent`, `border-sidebar-accent` |
| `--color-sidebar-accent-foreground` | `var(--sidebar-accent-foreground)` | `bg-sidebar-accent-foreground`, `text-sidebar-accent-foreground`, `border-sidebar-accent-foreground` |
| `--color-sidebar-border` | `var(--sidebar-border)` | `bg-sidebar-border`, `text-sidebar-border`, `border-sidebar-border` |
| `--color-sidebar-ring` | `var(--sidebar-ring)` | `bg-sidebar-ring`, `text-sidebar-ring`, `border-sidebar-ring` |
| `--shadow-xs` | `var(--sciem-shadow-xs)` | `shadow-xs` |
| `--shadow-sm` | `var(--sciem-shadow-sm)` | `shadow-sm` |
| `--shadow-md` | `var(--sciem-shadow-md)` | `shadow-md` |
| `--shadow-lg` | `var(--sciem-shadow-lg)` | `shadow-lg` |
| `--shadow-overlay` | `var(--sciem-shadow-overlay)` | `shadow-overlay` |
| `--shadow-sticky` | `var(--sciem-shadow-sticky)` | `shadow-sticky` |
| `--radius-sm` | `var(--sciem-radius-sm)` | `rounded-sm` |
| `--radius-md` | `var(--sciem-radius-md)` | `rounded-md` |
| `--radius-lg` | `var(--sciem-radius-lg)` | `rounded-lg` |
| `--radius-xl` | `var(--sciem-radius-xl)` | `rounded-xl` |
| `--radius-pill` | `var(--sciem-radius-pill)` | `rounded-pill` |
| `--spacing-topbar` | `var(--sciem-topbar-height)` | `h-topbar` y demás utilidades de espaciado |
| `--font-sans` | `"Inter Variable", "Inter", system-ui, sans-serif` | `font-sans` |
| `--font-brand` | `"Poppins", "Inter", sans-serif` | `font-brand` |

## 5. Contrastes WCAG verificados

Relaciones de contraste WCAG 2.x calculadas con los valores de la sección 4. El mínimo es 4.5:1 para texto y 3:1 para componentes de interfaz y foco (criterios 1.4.3 y 1.4.11, nivel AA). Todos los pares cumplen en ambos temas; la prueba de contraste falla si alguno deja de cumplir.

| Primer plano | Fondo | Claro | Oscuro | Mínimo |
|---|---|---|---|---|
| `text` | `bg-app` | 13.03 | 15.37 | 4.5 |
| `text` | `surface` | 13.97 | 13.74 | 4.5 |
| `text` | `raised` | 13.97 | 12.31 | 4.5 |
| `text` | `field-bg` | 13.97 | 14.80 | 4.5 |
| `muted` | `bg-app` | 5.20 | 8.64 | 4.5 |
| `muted` | `surface` | 5.57 | 7.73 | 4.5 |
| `muted` | `sunken` | 5.31 | 8.09 | 4.5 |
| `muted` | `raised` | 5.57 | 6.92 | 4.5 |
| `muted` | `muted-bg` | 5.31 | 5.80 | 4.5 |
| `muted` | `brand-soft` | 4.55 | 6.13 | 4.5 |
| `muted` | `field-bg` | 5.57 | 8.32 | 4.5 |
| `subtle` | `bg-app` | 4.50 | 6.03 | 4.5 |
| `subtle` | `surface` | 4.83 | 5.39 | 4.5 |
| `brand-fg` | `bg-app` | 7.00 | 7.86 | 4.5 |
| `brand-fg` | `surface` | 7.50 | 7.02 | 4.5 |
| `brand-fg` | `raised` | 7.50 | 6.29 | 4.5 |
| `brand-fg` | `brand-soft` | 6.12 | 5.57 | 4.5 |
| `brand-deep` | `brand-soft` | 10.45 | 6.23 | 4.5 |
| `brand-deep` | `surface` | 12.79 | 7.85 | 4.5 |
| `ok-fg` | `ok-soft` | 6.91 | 5.85 | 4.5 |
| `warn-fg` | `warn-soft` | 5.77 | 6.94 | 4.5 |
| `danger-fg` | `danger-soft` | 6.34 | 5.82 | 4.5 |
| `info` | `info-soft` | 7.10 | 5.23 | 4.5 |
| `neutral-fg` | `neutral-soft` | 6.39 | 6.77 | 4.5 |
| `danger` | `surface` | 4.83 | 5.53 | 4.5 |
| `danger-fg` | `surface` | 7.77 | 6.25 | 4.5 |
| `ok-fg` | `surface` | 8.12 | 7.67 | 4.5 |
| `warn-fg` | `surface` | 6.32 | 7.77 | 4.5 |
| `on-solid` | `brand` | 7.50 | 4.69 | 4.5 |
| `on-solid` | `brand-hover` | 8.53 | 5.51 | 4.5 |
| `on-solid` | `brand-active` | 9.85 | 6.42 | 4.5 |
| `on-solid` | `brand-strong` | 12.79 | 6.42 | 4.5 |
| `on-solid` | `danger-solid` | 4.83 | 5.27 | 4.5 |
| `on-solid` | `ok-solid` | 5.02 | 5.01 | 4.5 |
| `on-solid` | `ok-solid-hover` | 8.12 | 6.30 | 4.5 |
| `on-solid` | `neutral-solid` | 6.99 | 5.42 | 4.5 |
| `on-accent` | `accent` | 9.20 | 9.20 | 4.5 |
| `on-accent` | `accent-hover` | 7.47 | 7.47 | 4.5 |
| `sb-text` | `sb-bg` | 10.45 | 16.38 | 4.5 |
| `sb-text` | `sb-hover` | 8.47 | 14.37 | 4.5 |
| `sb-text` | `sb-active` | 6.38 | 12.79 | 4.5 |
| `sb-sub` | `sb-bg` | 6.83 | 9.21 | 4.5 |
| `sb-group` | `sb-bg` | 5.35 | 6.43 | 4.5 |
| `on-solid` | `sb-active` | 7.81 | 14.95 | 4.5 |
| `sb-focus` | `sb-bg` | 6.83 | 8.37 | 3 |
| `focus` | `bg-app` | 3.03 | 3.78 | 3 |
| `focus` | `surface` | 3.25 | 3.38 | 3 |
| `focus` | `raised` | 3.25 | 3.03 | 3 |
| `input-border` | `bg-app` | 3.01 | 3.75 | 3 |
| `input-border` | `surface` | 3.23 | 3.35 | 3 |
| `input-border` | `raised` | 3.23 | 3.00 | 3 |
| `input-border` | `field-bg` | 3.23 | 3.61 | 3 |
| `ok` | `surface` | 5.02 | 5.71 | 3 |
| `brand` | `surface` | 7.50 | 3.42 | 3 |

## 6. Funcionamiento del modo oscuro

### 6.1. Activación

El tema sigue la preferencia del navegador o del sistema operativo mediante `prefers-color-scheme`; no hay selector en la interfaz ni preferencia guardada. El bloque oscuro de la capa 1 se declara dentro de `@media screen and (prefers-color-scheme: dark)` y la variante `dark:` de Tailwind se define con la misma consulta, de modo que las pocas primitivas de shadcn que la usan reaccionan igual que los tokens.

### 6.2. Impresión

Tanto el bloque oscuro como la variante `dark:` están limitados a `screen`. Al imprimir (por ejemplo, el reporte final de un examen) siempre rige el tema claro, aunque el sistema esté en modo oscuro.

### 6.3. Metadatos de `index.html`

`<meta name="color-scheme" content="light dark">` indica al navegador que la página admite ambos esquemas, de modo que pinta el fondo, los controles nativos (fecha, hora, casillas) y las barras con el esquema correcto desde el primer momento. Los dos `<meta name="theme-color">`, uno por esquema, colorean la barra del navegador en móvil con el mismo valor que `--sciem-bg-app`, el fondo de la barra superior de la aplicación. Como HTML no puede leer variables CSS, esos valores se escriben en el HTML y una prueba verifica que coincidan con el token.

## 7. Verificación

### 7.1. Pruebas automáticas

Las pruebas se ejecutan con `npm run test` en `frontend/`:

| Prueba | Archivo | Qué comprueba |
|---|---|---|
| Guardián de tokens | `src/designTokens.test.ts` | Que el código fuente no tenga literales de color, `white`/`black`, paleta por defecto, `text-muted` ni variables inexistentes; que toda utilidad de color apunte a un token; que los literales vivan solo en la capa 1; que `sciem-tokens.css` y los `theme-color` estén sincronizados. |
| Contraste | `src/designContrast.test.ts` | Los pares de la sección 5 en ambos temas, el orden de elevación, que el sidebar oscuro sea la superficie más oscura y neutra, y que el hover sutil se vea sobre todas las capas. |
| Documentación | `src/designDocs.test.ts` | Que las tablas de este documento coincidan con `index.css` (tokens, valores claro y oscuro, capas 2 y 3) y que los contrastes publicados sean los calculados. |

`npm run build` completa la verificación (comprobación de tipos y compilación).

### 7.2. Revisión manual

Cada cambio visual se revisa en ambos temas (cambiando la preferencia del sistema o con la emulación de `prefers-color-scheme` de las herramientas del navegador) y en tres anchos: 360 px, 768 px y 1280 px. El orden de revisión prioriza el control de ingreso en móvil, que es el dispositivo principal de esa operación:

1. Control de ingreso: lista de exámenes abiertos, verificación con los tres veredictos (permitido, precaución y rechazado), auxiliar, aviso de aula incorrecta y solicitudes de ingreso con retraso.
2. Ingreso al sistema: panel de marca, formulario, barra fija inferior y avisos de bloqueo.
3. Exámenes: registro y edición (campos y menús), programados, en curso (botón «Terminar examen» y su diálogo), control de tiempo, habilitados, distribución, reporte final y su vista de impresión, historial.
4. Incidencias, Central de Riesgo y rectificaciones.
5. Gestión académica, nóminas, cuentas, bitácora y reportes.

En cada pantalla se revisa: borde visible en tarjetas y diálogos, hover y selección en tablas, menús y pestañas, campos deshabilitados, barras de desplazamiento, alineación de la barra superior con la cabecera del sidebar y el comportamiento del botón para ocultar o mostrar el menú.

## 8. Historial de cambios

| Versión | Fecha | Requerimiento | Cambio |
|---|---|---|---|
| 1.0.0 | 2026-11-26 | RNF-19 | Tokens en tres capas para tema claro y oscuro, correcciones de contraste del tema claro, modo oscuro por `prefers-color-scheme`, pruebas de tokens y contraste. |
| 1.1.0 | 2026-11-26 | RNF-19 | Sidebar oscuro neutro, campos unificados, menús en teal, barras de desplazamiento finas, altura común de la barra superior y este documento con su prueba de sincronización. |
