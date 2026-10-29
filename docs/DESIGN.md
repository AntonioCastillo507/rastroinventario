---
version: alpha
name: Rastro del Istmo
description: Sistema de diseño de una tienda en línea con panel de administración, vendible como plantilla. Lienzo oscuro casi negro con un único color de acento amarillo (#FCD535) que carga todas las acciones principales, y un modo claro que comparte los mismos botones amarillos y bordes gris-azulados. Oscuro por defecto, claro con interruptor, ambos en todo el sitio. Superficies planas, bordes finos, radios de 6 a 12 px, cifras en tipografía tabular. Toda la paleta se deriva de un único color principal, de modo que un comprador puede cambiarlo sin tocar el resto.

colors:
  # --- Valores originales del análisis base (sin cambios) ---
  primary: "#fcd535"
  primary-active: "#f0b90b"
  primary-disabled: "#3a3a1f"
  ink: "#181a20"
  body: "#eaecef"
  body-on-light: "#181a20"
  muted: "#707a8a"
  muted-strong: "#929aa5"
  hairline-on-light: "#eaecef"
  hairline-on-dark: "#2b3139"
  border-strong: "#cdd1d6"
  canvas-light: "#ffffff"
  canvas-dark: "#0b0e11"
  surface-card-dark: "#1e2329"
  surface-elevated-dark: "#2b3139"
  surface-soft-light: "#f7f3e8"
  surface-strong-light: "#f2ecde"
  surface-elevated-light: "#f2ecde"
  on-primary: "#181a20"
  on-dark: "#ffffff"
  trading-up: "#0ecb81"
  trading-down: "#f6465d"
  info: "#3b82f6"
  info-ring: "#3b82f6"
  # --- Tokens añadidos para cumplir AA y para una tienda (no sustituyen a los originales) ---
  primary-text-on-dark: "#fcd535"
  muted-on-light: "#5e6673"
  link-on-light: "#7a5c00"
  success: "#0ecb81"
  success-on-light: "#0a7a4f"
  danger: "#f6475e"
  danger-on-elevated: "#f8677a"
  danger-on-light: "#c0233a"
  warning: "#f0b90b"
  warning-on-light: "#8a5a00"
  input-border-dark: "#707a8a"
  input-border-light: "#838d9c"
  focus-ring: "#3b82f6"

typography:
  # Familias: ver la sección "Tipografía". Display y texto en Manrope; cifras en IBM Plex Sans.
  hero-display:
    fontFamily: "Manrope, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif"
    fontSize: 64px
    fontWeight: 700
    lineHeight: 1.1
    letterSpacing: -1px
  hero-display-mobile:
    fontFamily: "Manrope, system-ui, sans-serif"
    fontSize: 36px
    fontWeight: 700
    lineHeight: 1.15
    letterSpacing: -0.5px
  display-lg:
    fontFamily: "Manrope, system-ui, sans-serif"
    fontSize: 48px
    fontWeight: 700
    lineHeight: 1.1
    letterSpacing: -0.5px
  display-md:
    fontFamily: "Manrope, system-ui, sans-serif"
    fontSize: 40px
    fontWeight: 600
    lineHeight: 1.15
    letterSpacing: -0.3px
  display-sm:
    fontFamily: "Manrope, system-ui, sans-serif"
    fontSize: 32px
    fontWeight: 600
    lineHeight: 1.2
    letterSpacing: 0
  title-lg:
    fontFamily: "Manrope, system-ui, sans-serif"
    fontSize: 24px
    fontWeight: 600
    lineHeight: 1.3
    letterSpacing: 0
  title-md:
    fontFamily: "Manrope, system-ui, sans-serif"
    fontSize: 20px
    fontWeight: 600
    lineHeight: 1.35
    letterSpacing: 0
  title-sm:
    fontFamily: "Manrope, system-ui, sans-serif"
    fontSize: 16px
    fontWeight: 600
    lineHeight: 1.4
    letterSpacing: 0
  number-display:
    fontFamily: "'IBM Plex Sans', Manrope, system-ui, sans-serif"
    fontSize: 40px
    fontWeight: 700
    lineHeight: 1.1
    letterSpacing: -0.3px
  number-md:
    fontFamily: "'IBM Plex Sans', Manrope, system-ui, sans-serif"
    fontSize: 16px
    fontWeight: 500
    lineHeight: 1.4
    letterSpacing: 0
  number-sm:
    fontFamily: "'IBM Plex Sans', Manrope, system-ui, sans-serif"
    fontSize: 14px
    fontWeight: 500
    lineHeight: 1.4
    letterSpacing: 0
  body-lg:
    fontFamily: "Manrope, system-ui, sans-serif"
    fontSize: 16px
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: 0
  body-md:
    fontFamily: "Manrope, system-ui, sans-serif"
    fontSize: 14px
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: 0
  body-sm:
    fontFamily: "Manrope, system-ui, sans-serif"
    fontSize: 13px
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: 0
  caption:
    fontFamily: "Manrope, system-ui, sans-serif"
    fontSize: 12px
    fontWeight: 500
    lineHeight: 1.4
    letterSpacing: 0
  button:
    fontFamily: "Manrope, system-ui, sans-serif"
    fontSize: 14px
    fontWeight: 600
    lineHeight: 1
    letterSpacing: 0
  input-text:
    fontFamily: "Manrope, system-ui, sans-serif"
    fontSize: 16px
    fontWeight: 400
    lineHeight: 1.4
    letterSpacing: 0
  nav-link:
    fontFamily: "Manrope, system-ui, sans-serif"
    fontSize: 14px
    fontWeight: 500
    lineHeight: 1.4
    letterSpacing: 0

rounded:
  xs: 2px
  sm: 4px
  md: 6px
  lg: 8px
  xl: 12px
  pill: 9999px
  full: 9999px

spacing:
  xxs: 4px
  xs: 8px
  sm: 12px
  md: 16px
  lg: 24px
  xl: 32px
  xxl: 48px
  section: 80px

components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.on-primary}"
    typography: "{typography.button}"
    rounded: "{rounded.md}"
    padding: 12px 24px
    height: 40px
    height-touch: 44px
  button-primary-active:
    backgroundColor: "{colors.primary-active}"
    textColor: "{colors.on-primary}"
    rounded: "{rounded.md}"
  button-primary-disabled:
    backgroundColor: "{colors.primary-disabled}"
    textColor: "{colors.muted}"
    rounded: "{rounded.md}"
  button-primary-disabled-on-light:
    backgroundColor: "{colors.surface-strong-light}"
    textColor: "{colors.muted-on-light}"
    borderColor: "{colors.border-strong}"
    rounded: "{rounded.md}"
  button-primary-pill:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.on-primary}"
    typography: "{typography.button}"
    rounded: "{rounded.pill}"
    padding: 14px 32px
    height-touch: 44px
  button-secondary-on-dark:
    backgroundColor: "{colors.surface-card-dark}"
    textColor: "{colors.on-dark}"
    typography: "{typography.button}"
    rounded: "{rounded.md}"
    padding: 12px 24px
    height-touch: 44px
  button-secondary-on-light:
    backgroundColor: "{colors.canvas-light}"
    textColor: "{colors.ink}"
    typography: "{typography.button}"
    rounded: "{rounded.md}"
    padding: 12px 24px
    height-touch: 44px
  button-tertiary-text:
    backgroundColor: transparent
    textColor: "{colors.body}"
    typography: "{typography.button}"
  button-danger-on-dark:
    backgroundColor: "{colors.danger}"
    textColor: "{colors.ink}"
    typography: "{typography.button}"
    rounded: "{rounded.md}"
    padding: 12px 24px
  button-danger-on-light:
    backgroundColor: "{colors.danger-on-light}"
    textColor: "{colors.on-dark}"
    typography: "{typography.button}"
    rounded: "{rounded.md}"
    padding: 12px 24px
  text-link-on-dark:
    backgroundColor: transparent
    textColor: "{colors.primary}"
    typography: "{typography.body-md}"
  text-link-on-light:
    backgroundColor: transparent
    textColor: "{colors.link-on-light}"
    typography: "{typography.body-md}"
  top-nav-dark:
    backgroundColor: "{colors.canvas-dark}"
    textColor: "{colors.on-dark}"
    typography: "{typography.nav-link}"
    height: 64px
  top-nav-light:
    backgroundColor: "{colors.canvas-light}"
    textColor: "{colors.ink}"
    typography: "{typography.nav-link}"
    height: 64px
  hero-band:
    backgroundColor: "{colors.canvas-dark}"
    textColor: "{colors.on-dark}"
    typography: "{typography.hero-display}"
    padding: 80px
  hero-band-light:
    backgroundColor: "{colors.surface-soft-light}"
    textColor: "{colors.ink}"
    typography: "{typography.hero-display}"
    padding: 80px
  stat-callout-card:
    backgroundColor: transparent
    textColor: "{colors.primary}"
    typography: "{typography.number-display}"
  kpi-card-value:
    backgroundColor: transparent
    textColor: "{colors.primary-text-on-dark}"
    fontFamily: "IBM Plex Sans"
    fontSize: 32px
    fontWeight: 700
    lineHeight: 1.2
  brand-lockup:
    backgroundColor: transparent
    textColor: "{colors.on-dark}"
    fontFamily: "Manrope"
    fontSize: 16px
    fontWeight: 700
    lineHeight: 1.2
    taglineFontSize: 12px
    taglineFontWeight: 500
    height: 40px
    gap: 12px
  product-photo-tile:
    backgroundColor: "{colors.canvas-light}"
    rounded: "{rounded.xl}"
  trust-badge:
    backgroundColor: "{colors.surface-card-dark}"
    textColor: "{colors.on-dark}"
    typography: "{typography.title-sm}"
    rounded: "{rounded.lg}"
    padding: 16px 20px
  product-card:
    backgroundColor: "{colors.surface-card-dark}"
    textColor: "{colors.on-dark}"
    typography: "{typography.body-md}"
    rounded: "{rounded.xl}"
    padding: 16px
  product-price:
    backgroundColor: transparent
    textColor: "{colors.primary-text-on-dark}"
    fontFamily: "IBM Plex Sans"
    fontSize: 20px
    fontWeight: 600
    lineHeight: 1.3
  product-price-on-light:
    backgroundColor: transparent
    textColor: "{colors.link-on-light}"
    fontFamily: "IBM Plex Sans"
    fontSize: 20px
    fontWeight: 600
    lineHeight: 1.3
  badge-stock-ok:
    backgroundColor: transparent
    textColor: "{colors.success}"
    typography: "{typography.caption}"
    rounded: "{rounded.sm}"
  badge-stock-low:
    backgroundColor: transparent
    textColor: "{colors.warning}"
    typography: "{typography.caption}"
    rounded: "{rounded.sm}"
  badge-stock-out:
    backgroundColor: transparent
    textColor: "{colors.danger}"
    typography: "{typography.caption}"
    rounded: "{rounded.sm}"
  data-table-card:
    backgroundColor: "{colors.surface-card-dark}"
    textColor: "{colors.on-dark}"
    typography: "{typography.body-md}"
    rounded: "{rounded.xl}"
    padding: 24px
  data-row:
    backgroundColor: transparent
    textColor: "{colors.on-dark}"
    typography: "{typography.number-md}"
    padding: 12px 0
  admin-card-mobile:
    backgroundColor: "{colors.surface-card-dark}"
    textColor: "{colors.on-dark}"
    typography: "{typography.body-md}"
    rounded: "{rounded.lg}"
    padding: 16px
  search-input-on-dark:
    backgroundColor: "{colors.surface-card-dark}"
    textColor: "{colors.on-dark}"
    typography: "{typography.input-text}"
    rounded: "{rounded.lg}"
    padding: 10px 16px
    height: 40px
    height-touch: 44px
  text-input-on-light:
    backgroundColor: "{colors.canvas-light}"
    textColor: "{colors.ink}"
    typography: "{typography.input-text}"
    rounded: "{rounded.md}"
    padding: 10px 16px
    height: 40px
    height-touch: 44px
  modal-confirm:
    backgroundColor: "{colors.surface-elevated-dark}"
    textColor: "{colors.on-dark}"
    typography: "{typography.body-md}"
    rounded: "{rounded.xl}"
    padding: 24px
  modal-error-text:
    backgroundColor: "{colors.surface-elevated-dark}"
    textColor: "{colors.danger-on-elevated}"
    typography: "{typography.body-md}"
  surface-elevated-on-light:
    backgroundColor: "{colors.surface-elevated-light}"
    textColor: "{colors.ink}"
    typography: "{typography.body-md}"
    rounded: "{rounded.lg}"
  alert:
    backgroundColor: "{colors.surface-card-dark}"
    textColor: "{colors.on-dark}"
    typography: "{typography.body-md}"
    rounded: "{rounded.lg}"
    padding: 12px 16px
  cta-band:
    backgroundColor: "{colors.surface-card-dark}"
    textColor: "{colors.on-dark}"
    typography: "{typography.display-sm}"
    rounded: "{rounded.xl}"
    padding: 48px
  footer-dark:
    backgroundColor: "{colors.surface-card-dark}"
    textColor: "{colors.body}"
    typography: "{typography.body-md}"
    padding: 64px
  footer-light:
    backgroundColor: "{colors.surface-strong-light}"
    textColor: "{colors.body-on-light}"
    typography: "{typography.body-md}"
    padding: 64px
---

## Visión general

Rastro del Istmo se lee como una tienda técnica y confiable: un **lienzo casi negro** (`{colors.canvas-dark}` #0B0E11) con texto claro y **un único acento**, el amarillo `{colors.primary}` (#FCD535). Ese amarillo hace casi todo el trabajo de marca: botones principales, precios destacados, enlaces sobre fondo oscuro y las cifras que quieren llamar la atención. No hay un segundo color de marca. La escasez del amarillo es lo que le da fuerza.

La paleta, la escala de radios, el espaciado y la jerarquía tipográfica provienen de un análisis de diseño de una plataforma financiera, que se usa como base fiel; las únicas diferencias están en la sección "Adaptaciones". Esta plantilla **no usa marcas, logos, textos, ilustraciones ni tipografías de terceros**.

El sistema es **bimodal en todo el sitio**: oscuro por defecto y claro con interruptor que recuerda la elección. En ambos modos los botones principales son idénticos (amarillo con texto casi negro) y los bordes son finos y gris-azulados. Cambian solo el lienzo, las superficies y los tonos de texto.

**Características clave**
- Un solo color de acento (`{colors.primary}`): botones principales, precios destacados, marca.
- Superficies **planas**: la separación sale del contraste entre `{colors.canvas-dark}` y `{colors.surface-card-dark}`, no de sombras ni degradados.
- Bordes de 1 px (`{colors.hairline-on-dark}` / `{colors.hairline-on-light}`), usados con generosidad.
- Radios pequeños a medianos: 6 px botones, 8 px campos y tarjetas de contenido, 12 px contenedores elevados.
- Espaciado en múltiplos de 4; bandas de sección de 80 px.
- Las cifras (precios, stock, totales) se escriben siempre en la fuente numérica, con cifras tabulares.

## Colores

### Marca y acento
- **Amarillo principal** (`{colors.primary}` #FCD535): botones principales, marca, precios destacados, enlaces **sobre fondo oscuro**. Nunca para texto sobre fondo claro (ver "Adaptaciones").
- **Amarillo activo** (`{colors.primary-active}` #F0B90B): estado pulsado o hover del botón principal.
- **Amarillo deshabilitado** (`{colors.primary-disabled}` #3A3A1F): botón principal deshabilitado sobre oscuro.

### Superficies
**Modo oscuro (por defecto)**
- `{colors.canvas-dark}` #0B0E11: el suelo de la página. Casi negro, con un matiz frío; nunca negro puro.
- `{colors.surface-card-dark}` #1E2329: tarjetas, menús desplegables, botones secundarios, tablas.
- `{colors.surface-elevated-dark}` #2B3139: tarjetas anidadas, elementos con hover, modal de confirmación.

**Modo claro**
- **Suelo de la página:** `{colors.surface-soft-light}` #F7F3E8, un tono hueso cálido que acompaña al dorado (ver "Adaptaciones"). También es el fondo de la banda de portada en claro (`hero-band-light`).
- **Tarjetas y campos:** `{colors.canvas-light}` #FFFFFF, con borde `{colors.hairline-on-light}`.
- `{colors.surface-strong-light}` #F2ECDE: pie de página y zonas atenuadas, en un tono cálido que pasa AA con todos los textos del modo claro. Los campos de formulario **no** van directo sobre esta superficie (su borde daría 2,85:1).
- `{colors.surface-elevated-light}` #F2ECDE: menús desplegables, marcador de foto y botón principal deshabilitado en claro (valor provisional aprobado, igual que `surface-strong-light`).
- Los campos de formulario van sobre blanco (`{colors.canvas-light}`) o sobre el suelo; su borde `{colors.input-border-light}` da 3,36:1 sobre blanco y 3,03:1 sobre el suelo cálido.

### Bordes
- `{colors.hairline-on-dark}` #2B3139 y `{colors.hairline-on-light}` #EAECEF para divisores y contornos decorativos.
- `{colors.border-strong}` #CDD1D6: bordes más marcados en botones secundarios deshabilitados.
- **Bordes de campos de formulario** (deben distinguirse, 3:1): `{colors.input-border-dark}` #707A8A en oscuro (3,65:1 sobre tarjeta) y `{colors.input-border-light}` #838D9C en claro (3,36:1 sobre blanco y 3,03:1 sobre el suelo cálido).

### Texto
- **Oscuro:** `{colors.body}` #EAECEF (cuerpo), `{colors.on-dark}` #FFFFFF (titulares), `{colors.muted-strong}` #929AA5 (texto secundario; 5,56:1 sobre tarjeta y 6,81:1 sobre el lienzo).
- **Claro:** `{colors.body-on-light}` / `{colors.ink}` #181A20 (17,4:1 sobre blanco), `{colors.muted-on-light}` #5E6673 (texto secundario; 5,79:1 sobre blanco y 5,23:1 sobre el suelo #F7F3E8), `{colors.link-on-light}` #7A5C00 (enlaces y texto de acento; 6,25:1 sobre blanco).
- `{colors.muted}` #707A8A: solo para elementos **decorativos o deshabilitados**, nunca para texto que se deba leer (sobre tarjeta oscura da 3,65:1).
- `{colors.on-primary}` #181A20: texto sobre el amarillo (12,18:1).

### Semántica de estados
Los verdes y rojos del análisis base eran para precios que suben o bajan. En esta tienda se reasignan a **éxito, error y aviso**:
- **Éxito:** `{colors.success}` #0ECB81 en oscuro (7,44:1 sobre tarjeta); `{colors.success-on-light}` #0A7A4F en claro (5,37:1).
- **Error:** `{colors.danger}` #F6475E en oscuro (4,51:1 sobre tarjeta y 5,51:1 sobre el lienzo); `{colors.danger-on-elevated}` #F8677A para **texto rojo sobre el modal o cualquier superficie elevada** (4,50:1 sobre `surface-elevated-dark`); `{colors.danger-on-light}` #C0233A en claro (5,93:1).
- **Aviso y stock bajo:** `{colors.warning}` #F0B90B en oscuro (8,77:1); `{colors.warning-on-light}` #8A5A00 en claro (5,93:1).
- **Información y foco:** `{colors.info}` #3B82F6.
- Un botón relleno de verde o de rojo usa **texto oscuro** (`{colors.ink}`) sobre el tono vivo en oscuro (8,18:1 sobre verde; 4,95:1 sobre rojo) o **texto blanco** sobre el tono oscuro en claro (5,37:1 y 5,93:1).
- Los estados nunca se indican solo con color: llevan siempre texto o un icono con nombre accesible.

### Gráficas (S6a)
Tokens de `tema.css` (`--chart-1` a `--chart-6` y `--chart-otras`), un único juego para todas las gráficas. Cada categoría se pinta siempre con el mismo tono, asignado **por id** (lugar entre todas las secciones, módulo 6): una categoría nueva toma el siguiente tono libre y las demás no cambian. «Otras» y «Sin categoría» van en `--chart-otras` (gris secundario). Con más de 6 categorías un tono se repite; la leyenda con nombre y monto evita depender solo del color. La dona no pasa de 5 segmentos.

| Token | Oscuro | Claro |
|---|---|---|
| `chart-1` | `{colors.primary-text-on-dark}` (el acento, `--link`) | `{colors.link-on-light}` |
| `chart-2` (azul) | #3B82F6 | #2563EB |
| `chart-3` (rosa) | #F472B6 | #BE185D |
| `chart-4` (cian) | #22D3EE | #0891B2 |
| `chart-5` (violeta) | #A78BFA | #7C3AED |
| `chart-6` (naranja) | #FB923C | #C2410C |
| `chart-otras` | `{colors.muted-strong}` | `{colors.muted-on-light}` |

Validados con el script de contraste de dataviz contra la tarjeta (≥ 3:1 en los siete, en los dos modos) y con separación para daltonismo entre tonos vecinos (ΔE ≥ 8,5 en oscuro, ≥ 13 en claro). El amarillo de marca queda fuera de la banda de luminosidad recomendada en oscuro: es la marca y se mantiene. Series únicas (barras de piezas y marcas, línea de ventas): `chart-1`, el acento. El periodo anterior va en gris secundario y punteado.

## Tipografía

### Familias (alternativas libres a las tipografías propietarias del análisis base)
- **Manrope** (licencia SIL OFL): titulares, cuerpo, botones y navegación. Geométrica y moderna, con carácter propio y buena lectura a tamaños pequeños.
- **IBM Plex Sans** (licencia SIL OFL): **todas las cifras** (precios, stock, totales, porcentajes, contadores). Aporta la voz de "número fiable" de tipo técnico.
- Ambas se **autoalojan** en `assets/fonts/` (archivos WOFF2 de los subconjuntos latino y latino extendido para el español, con `font-display: swap`) y se incluye su archivo de licencia. No se carga ninguna fuente desde servicios externos.
- Pila de reserva: `system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`.
- Las cifras llevan `font-variant-numeric: tabular-nums` para que los precios y totales se alineen en columna.
- *Alternativas con la misma estructura:* Plus Jakarta Sans (más cálida y comercial) o Sora (más técnica y ancha, con DM Sans para el cuerpo). Para cambiar la fuente basta editar las dos familias de este archivo y del `tema.css`.

### Jerarquía

| Token | Tamaño | Peso | Interlineado | Espaciado de letras | Uso |
|---|---|---|---|---|---|
| `{typography.hero-display}` | 64 px | 700 | 1,1 | -1 px | Título principal de la portada (escritorio) |
| `{typography.hero-display-mobile}` | 36 px | 700 | 1,15 | -0,5 px | Título principal en móvil |
| `{typography.display-lg}` | 48 px | 700 | 1,1 | -0,5 px | Titulares de marca |
| `{typography.display-md}` | 40 px | 600 | 1,15 | -0,3 px | Encabezados de sección |
| `{typography.display-sm}` | 32 px | 600 | 1,2 | 0 | Titulares de bandas de llamada a la acción |
| `{typography.title-lg}` | 24 px | 600 | 1,3 | 0 | Subtítulos de sección, título de página del panel |
| `{typography.title-md}` | 20 px | 600 | 1,35 | 0 | Títulos de tarjetas que no son de pieza (el nombre de la pieza usa `{typography.title-sm}` en todos los anchos) |
| `{typography.title-sm}` | 16 px | 600 | 1,4 | 0 | Insignias de confianza, etiquetas |
| `{typography.number-display}` | 40 px | 700 | 1,1 | -0,3 px | Cifras grandes (estadísticas) |
| `{typography.number-md}` | 16 px | 500 | 1,4 | 0 | Celdas de tabla y cifras en línea |
| Precio (E2) | 20 px | 600 | 1,3 | 0 | Precio en tarjeta y detalle (IBM Plex Sans, token CSS `--precio` para el color) |
| Valor KPI (E3) | 32 px | 700 | 1,2 | -0,3 px | Valor de las tarjetas KPI del panel |
| Marca (E6) | 16 px / 12 px | 700 / 500 | 1,2 | 0 | Nombre y lema del `brand-lockup` |
| `{typography.number-sm}` | 14 px | 500 | 1,4 | 0 | Precios y cantidades en línea |
| `{typography.body-lg}` | 16 px | 400 | 1,5 | 0 | **Texto base de la tienda** |
| `{typography.body-md}` | 14 px | 400 | 1,5 | 0 | **Texto base del panel** y texto secundario |
| `{typography.body-sm}` | 13 px | 400 | 1,5 | 0 | Notas y pies |
| `{typography.caption}` | 12 px | 500 | 1,4 | 0 | Etiquetas pequeñas |
| `{typography.button}` | 14 px | 600 | 1 | 0 | Botones |
| `{typography.input-text}` | 16 px | 400 | 1,4 | 0 | **Todos los campos de formulario** |
| `{typography.nav-link}` | 14 px | 500 | 1,4 | 0 | Menú |

### Principios
Los tamaños grandes llevan peso 700 y los medianos 600: el sistema confía en el tamaño y en el amarillo, no en el exceso de negrita. Los campos de formulario van **siempre a 16 px** para que los teléfonos no amplíen la pantalla al enfocarlos. Un precio o una cantidad jamás se escribe en la fuente de texto.

## Distribución

### Espaciado
- Unidad base 4 px. Tokens: `{spacing.xxs}` 4 · `{spacing.xs}` 8 · `{spacing.sm}` 12 · `{spacing.md}` 16 · `{spacing.lg}` 24 · `{spacing.xl}` 32 · `{spacing.xxl}` 48 · `{spacing.section}` 80.
- Bandas de sección: 80 px en escritorio, menos en móvil.
- Relleno de tarjetas: 24 px de contenido, 32 px en bandas de llamada a la acción, 16 px en filas de tabla e insignias.
- Separación entre tarjetas: 24 px en escritorio; en móvil un gutter responsivo que **no provoque desplazamiento horizontal**.

### Rejilla y contenedor
- Ancho máximo: 1280 px en la tienda y 1440 px en el panel.
- Relleno lateral del contenedor (E5): 16 px en móvil, 24 px desde 768 px y 32 px desde 1200 px.
- Catálogo (E1): 4 columnas desde 1200 px, 3 desde 768 px, 2 desde 576 px y, por debajo, filas horizontales de una columna con la foto de 96 px a la izquierda.
- Filtros del catálogo en móvil: plegables, para que el primer producto aparezca sin bajar media pantalla.

### Filosofía del espacio
Más denso que una web de marketing: el contraste (amarillo sobre oscuro, tarjetas sobre lienzo) separa, no el vacío. Donde hay aire, es uniforme.

## Elevación y profundidad

| Nivel | Tratamiento | Uso |
|---|---|---|
| Plano | Sin sombra ni borde | Secciones, menú superior, bandas |
| Borde fino | 1 px `{colors.hairline-on-dark}` o `{colors.hairline-on-light}` | Divisores, tablas, tarjetas en modo claro |
| Superficie de tarjeta | `{colors.surface-card-dark}` sobre `{colors.canvas-dark}` (sin sombra); `{colors.canvas-light}` con borde en claro | Tarjetas de producto y de contenido |
| Elevado | `{colors.surface-elevated-dark}` | Modal de confirmación, menús desplegables |
| Foco | Anillo **sólido** de 2 px en `{colors.focus-ring}` con 2 px de separación | Teclado: botones, enlaces, campos |

No hay degradados atmosféricos, efectos de vidrio ni sombras pesadas. El hover de una tarjeta no la mueve más de 2 px y respeta `prefers-reduced-motion`.

## Formas

| Token | Valor | Uso |
|---|---|---|
| `{rounded.xs}` | 2 px | Casi sin uso |
| `{rounded.sm}` | 4 px | Insignias pequeñas |
| `{rounded.md}` | 6 px | Botones y campos principales |
| `{rounded.lg}` | 8 px | Campos de búsqueda, tarjetas de contenido, insignias de confianza |
| `{rounded.xl}` | 12 px | Contenedores elevados: tarjetas de producto, tablas, bandas |
| `{rounded.pill}` | 9999 px | Una sola acción destacada por pantalla (por ejemplo "Ver catálogo") |

### Iconos y fotografía
- Pocos iconos, **de un solo estilo y tamaño** (Bootstrap Icons, autoalojados), con nombre accesible cuando son botones. **Prohibido** un icono en cada título, la etiqueta en mayúsculas con icono y los emojis. **Excepción aprobada por Tony (2026-10-25, corrige la regla anterior): solo en el pie** se permiten los iconos con función de ubicación, teléfono, correo y reloj del horario, en el color de acento del tema sobre una pastilla redondeada de fondo suave (`pie-icono`, 32 px, radio 6 px, acento al 14 %), y los botones de redes y mensajería (WhatsApp, Instagram, TikTok) con icono y nombre visible. Los iconos decorativos llevan `aria-hidden`; el texto accesible es el dato o el nombre. Fuera del pie, ni la pastilla ni el icono en títulos ni en botones del resto del sitio. **Segunda excepción (Tony, 2026-10-26): las tarjetas de «Compra por categoría» de la portada** llevan un icono por categoría (Bootstrap Icons, configurable en `portada.categoria_iconos` de `marca.php`, por id de sección) en una pastilla de 40 px con radio `{rounded.md}` y el acento al 14 %, como la del pie; los iconos son decorativos (`aria-hidden`) y el nombre de la categoría va escrito al lado.
- **Logos de las marcas de autos (S7):** los sube el administrador en Marcas (PNG o WebP con fondo transparente, o SVG sencillo) y se muestran **siempre en una sola tinta**: máscara sobre el color del texto secundario en reposo y el acento (`--link`) al pasar el cursor o enfocar, así valen logos claros y oscuros. Sin logo, un monograma (una o dos iniciales, `title-sm` 16/700) en la misma casilla. Casilla (`marca-casilla`): 96×44 px en móvil y 128×56 px desde 768 px, radio `{rounded.lg}`, borde fino `{colors.hairline-on-dark}` / `{colors.hairline-on-light}`, fondo de tarjeta; en el panel se usa tal cual. En la portada la tarjeta es cuadrada (`marca-casilla--tarjeta`: 112 px en móvil y 136 px desde 768 px) con el logo arriba y el nombre de la marca debajo (`body-md`, 600), y es un solo enlace al catálogo de esa marca; el borde pasa a `{colors.primary}` al pasar el cursor o enfocar. Las dos flechas del carrusel son botones circulares de 44 px (`{rounded.pill}`, borde fino) a la derecha del título, en la misma fila; sin botón de pausa.
- Los símbolos funcionales (calificación con estrellas, flechas de orden) se resuelven con iconos o con texto, no con caracteres decorativos.
- Las fotos de producto se recortan a `{rounded.xl}` y llevan texto alternativo; si la imagen no existe, se muestra un marcador neutro, nunca una imagen rota.

## Componentes

### Navegación
- **`top-nav-dark` / `top-nav-light`**: 64 px de alto en la tienda y en el panel (E7), marca a la izquierda (`brand-lockup`, E6), menú horizontal (Inicio, Catálogo, Quiénes somos, Contacto), a la derecha el interruptor de tema, el carrito y la cuenta. La opción activa la decide el servidor, no JavaScript. En móvil se pliega a un menú que **se cierra al elegir una opción**.
- Un enlace de salto al contenido, un solo `h1` por página y un `<main>` en cada vista.

- **`brand-lockup`** (E6): un solo enlace de al menos 44 px de alto con el logo (40 px de alto, 36 en móvil; ancho automático), 12 px de separación y, a la derecha, el nombre (Manrope 16/700) con el lema (Manrope 12/500, sin mayúsculas forzadas) 2 px debajo. El conjunto va centrado en vertical. El lema se muestra desde 768 px y se oculta por debajo. El logo es de un solo color y va siempre en el **amarillo de marca** (`{colors.primary}`, token CSS `--logo`) en los dos modos, sin filtros CSS; el nombre usa el color de titulares. **Es una excepción a la regla de contraste:** el icono no es texto y sobre el suelo claro cálido da ~1,4:1, pero el nombre «Rastro del Istmo» va escrito al lado (≥ 4,5:1), así que la marca no depende del color del icono. Solo el icono de la R: ni el nombre ni el lema cambian de color. Misma pieza en el pie (32 px), en el acceso (48 px) y en el panel (32 px).

### Botones
- **`button-primary`**: amarillo con texto `{colors.on-primary}`, 6 px de radio, 40 px de alto en escritorio y **44 px en pantallas táctiles**. Pulsado: `{colors.primary-active}`. Deshabilitado: `{colors.primary-disabled}`.
- **`button-primary-pill`**: la variante en píldora, para una acción destacada por pantalla.
- **`button-secondary-on-dark` / `-on-light`**: acciones secundarias, con borde fino en claro.
- **`button-tertiary-text`**: acciones de texto, como "Iniciar sesión".
- **`button-danger-*`**: solo para acciones destructivas dentro de un modal de confirmación, con el verbo y el nombre del objeto en el texto ("Eliminar sección B" / "Conservar").
- Un solo botón principal por vista.

### Tarjetas y contenedores
- **`product-card`**: foto sobre la baldosa blanca fija (`product-photo-tile`, E4: `{colors.canvas-light}` en los dos modos, foto completa con `contain`), nombre (`title-sm`, 16/600, en todos los anchos; máximo 2 líneas), marca y modelo, precio (`product-price`, IBM Plex Sans 20/600: amarillo `{colors.primary-text-on-dark}` en oscuro y bronce `{colors.link-on-light}` en claro; el verde no se usa en precios), indicador de stock y botón principal "Añadir al carrito". El indicador usa texto y color (`badge-stock-*`), nunca solo color.
- **`stat-callout-card`**: cifras grandes del panel y de la portada, en amarillo sobre fondo plano, en `{typography.number-display}`. El valor de las tarjetas KPI del panel (`kpi-card-value`, E3) va en IBM Plex Sans 32/700.
- **`trust-badge`**: etiquetas de confianza (envío, garantía), opcionales.
- **`data-table-card` y `data-row`**: tablas del panel. Cabecera fija, cifras alineadas, acciones al final con nombre accesible. **En móvil (menos de 768 px) cada fila pasa a `admin-card-mobile`**: una tarjeta con el nombre, los datos clave y las acciones siempre visibles, hecha con CSS y un `data-label` por celda, sin duplicar el HTML.
- **`cta-band`**: banda de llamada a la acción con un solo botón.

### Formularios
- **`search-input-on-dark` / `text-input-on-light`**: borde fino, 40 px de alto en escritorio y 44 px en táctil, texto a 16 px. Foco con el anillo sólido. Etiqueta **siempre visible** y asociada al campo (`for`/`id`); el marcador del campo no sustituye a la etiqueta. Error: borde y mensaje en `{colors.danger}` (o `-on-light`), con icono y texto, junto al campo.
- Atributos `autocomplete` correctos en nombre, correo, teléfono y dirección.
- **Desplegables (`select`)**: todos llevan una flecha (chevron) propia a la derecha, con 44 px de relleno derecho para que el texto no la pise: texto secundario (`--text-muted`) en reposo y titular (`--text-strong`) al pasar el cursor y en foco; el borde pasa al amarillo de marca (`--btn-primary-bg`) en foco; cursor de mano. Se define una sola vez en la regla global de `select` y `.form-select` de `base.css`, con tokens y dos degradados, sin imágenes ni JavaScript. Misma altura que los demás campos (40 px; 44 en táctil).
- **Enlace de volver** (`.enlace-volver`): flecha `bi-arrow-left` a la izquierda del texto, peso 600, color de enlace (`--link`), subrayado al pasar el cursor y zona de 44 px; se usa en el acceso («Volver a la tienda», «Volver a iniciar sesión») y sobre el título de los formularios del panel.
- **Formularios del panel**: columna de 720 px (480 la de «Cambiar mi contraseña»), etiquetas visibles sin mayúsculas forzadas y barra de acciones que en móvil queda fija abajo con botones de 44 px.

### Avisos y confirmaciones
- **`alert`**: éxito, error, aviso e información, con icono, texto y `role` adecuado; el cierre es un botón con nombre accesible. Los avisos no desaparecen solos si contienen un error.
- **Destacar en la portada (S7b-C.2)**: interruptor «Mostrar en la portada (destacada)» en la tarjeta Organización del formulario de partes. Al marcarlo abre un `modal-confirm` más ancho (560 px) con el aviso del formato y una vista previa real del recorte en la caja `.hero-foto` de la tarjeta del hero (16 : 9, `object-fit: cover`, radio `{rounded.xl}`), con «Sí, se ve bien, destacarla» y «Cancelar» (al cancelar vuelve a desmarcarse). En la lista de partes las destacadas llevan la ficha «Portada» (`chip-info` pequeña, junto al nombre). Con 1 o más destacadas con stock y foto, el hero muestra solo esas (máximo 6, orden por id descendente); sin ninguna, las 4 aleatorias de siempre. Con una sola pieza la tarjeta es fija: sin flechas, puntos ni barrita y sin cambio automático. Autorizada por Tony (2026-10-29).
- **`modal-confirm`**: para eliminar, entregar un pedido y desactivar usuarios. Muestra el nombre del objeto y un botón con el verbo. Las acciones reversibles no piden confirmación y ofrecen deshacer.

### Carrito en dos pasos (E8)
- **`checkout-steps`**: lista ordenada "1 Carrito · 2 Tus datos" sobre el contenido. Cada paso lleva número y texto (`caption`, 12/500); el actual va en `{colors.text-strong}` con el número sobre `{colors.primary}` y `aria-current="step"`; el otro, en `{colors.muted-strong}` con el número en contorno `{colors.border-strong}`. Nunca solo color.
- **Cantidad − / +** de la línea: el mismo control de la ficha (`cantidad-control`: contorno `{colors.input-border-dark}`, botones de 44 px, número en la fuente numérica). − se desactiva en 1 y + en el stock; el nombre accesible dice la acción y la pieza. Quitar es un botón de icono de 44 px con nombre accesible; se deshace con un aviso "Quitaste «…» · Deshacer", sin `confirm()`.
- **Resumen del pedido**: tarjeta `{colors.surface-card-dark}` con borde fino y radio `{rounded.xl}`, desglose subtotal / impuesto / total (el total en `product-price`) y la nota de que los precios no incluyen impuesto. En escritorio es `sticky` y nunca más alto que la ventana (la lista de líneas hace scroll, los totales no); en móvil pasa a una barra inferior fija de 64 px ("Total $X · Ver resumen") que abre el mismo bloque como hoja inferior.

### Cuadro «Por qué elegirnos» (S7b-A.2)
Tarjeta de la portada (`porque-tarjeta`): `{colors.surface-card-dark}` con borde fino, radio `{rounded.xl}` y la misma sombra suave del hero, en dos mitades (foto y texto) desde 768 px y, por debajo, foto arriba (16 : 10) y texto abajo. Texto: insignia en píldora (`caption`, color de enlace), título (`title-lg`, `display-sm` desde 768 px), subtítulo destacado (`title-md` en color de enlace), párrafo y un `button-primary`. La foto es `nosotros.imagen` de `marca.php`; sin foto, el texto ocupa todo el ancho. Entra con la aparición de las secciones de la portada. Autorizada por Tony (2026-10-29).

### Paginación, pie de página
- Paginación con números, "Anterior" y "Siguiente", elemento activo con `aria-current`, y el texto "Mostrando X–Y de N".
- **`footer-dark` / `footer-light`**: el pie **sigue el tema** de la página. Sin créditos de terceros; el texto sale de la configuración de marca. Fondo un paso distinto al de la página (`surface-strong`: tarjeta en oscuro, `surface-strong-light` en claro) con una línea fina superior; relleno vertical de 48 px (64 px desde 992 px) arriba y 48 px abajo. Cuatro columnas desde 992 px (marca 1,6 | Comprar 1 | Empresa 1 | Contacto 1,3 fr, con 32 px entre ellas) y, por debajo, 2 columnas con la marca y Contacto a todo el ancho. **Marca**: logo, descripción y las redes como botones circulares de 44 px de solo icono (borde fino, nombre en `aria-label` y `title`, en una sola fila, solo las que tengan dirección en `marca.php`; abren otra pestaña con `rel="noopener noreferrer"` y aviso accesible). **Comprar**: Catálogo y una entrada por categoría activa (mismo enlace que «Compra por categoría»). **Empresa**: Quiénes somos, Contacto, Mi cuenta y Carrito. No hay columna de marcas (cambiaría de tamaño con cada marca nueva) ni Términos ni Privacidad mientras esas páginas no existan. Los títulos (`body-sm`, peso 700, color de titular) llevan una línea de 1 px debajo con el color de texto secundario del tema. Los enlaces miden 36 px de alto (44 px en móvil y con puntero táctil), sin separación extra, y al pasar el cursor toman el color de titular con subrayado amarillo de 1 px, sin mover el texto. **Contacto**: dirección (enlace a Google Maps con `Marca::mapa()`: clave `mapa` de `marca.php` o, sin ella, una búsqueda con el texto de la dirección; abre otra pestaña), teléfono, correo y horario en filas día | horas; iconos sin caja de 20 px en una columna fija, el texto siempre en el mismo borde. Barra inferior separada por una línea fina: copyright a la izquierda y «Volver arriba» a la derecha.

## Movimiento

Movimiento sutil y barato, autorizado por Tony el 2026-10-26. Solo para dar continuidad, nunca para decorar.

| Qué | Valor |
|---|---|
| Duración | de 150 a 250 ms (hover 150 a 200 ms, aparición 250 ms) |
| Curva | `ease-out` |
| Propiedades | `transform` y `opacity`; en el hover también el color del borde. **Nada de animar tamaño** (`width`, `height`, `padding`) **ni sombras pesadas** |
| Desplazamiento | hover de tarjeta: 2 px hacia arriba y borde en `{colors.primary}`; casilla de marca de la portada: 2 px hacia arriba y borde en `{colors.primary}`; botón principal: 1 px hacia arriba |
| Aparición | el texto del hero sube 8 px y aparece al cargar la página (250 ms); cada sección de la portada sube 12 px y aparece al entrar en pantalla (`IntersectionObserver` en `main.js`, sin librerías; sin JavaScript el contenido ya está visible) |
| Contenido principal | todo el `<main>` (menos la portada, que ya tiene su hero) sube 8 px y aparece al cargar la página (250 ms, `ease-out`) |
| Aparición escalonada | las tarjetas de pieza, las filas de las listas del panel y las tarjetas de formulario entran con 8 px de subida y opacidad al aparecer en pantalla, con retraso de 0 a 300 ms (de 50 en 50 dentro de cada lote); el mismo `IntersectionObserver` de `main.js`, que solo añade `.rv-item` (sin JavaScript todo se ve) |
| Agrandado en panel (S6c.3) | las tarjetas y paneles del Dashboard se agrandan con `transform: scale(1.02)` (máx.) en 180 ms al pasar el cursor, con una sombra suave que solo cambia de opacidad; sin hover agrandado en táctil. Autorizado por Tony (2026-10-27) |
| Botones | 1 px hacia arriba al pasar el cursor y de vuelta al pulsar (150 ms); los pegados a un campo (`.input-group`, buscador del hero) y los del modal no se mueven |
| Menús desplegables | aparecen en 150 ms (opacidad y 4 px) |
| Cajón del panel y modal de confirmación | el cajón se desliza en 250 ms (`transform`); el modal entra en 200 ms (opacidad y 8 px). La barra lateral contraída cambia al instante: no se anima tamaño |
| Carrusel de marcas (S7) | **Movimiento continuo** de la portada, aprobado por Tony (2026-10-29): 30 px por segundo (unos 40 s por vuelta a 1200 px), lineal y sin saltos (bucle de tres bloques iguales). Lo hace `marcas_carrusel.js` desplazando la ventana (`scrollLeft`), no una animación CSS, para que el deslizamiento táctil, las flechas y el foco del teclado sigan siendo los del navegador; no anima tamaño. Se pausa con el cursor encima, con el foco dentro, al tocar o usar las flechas, con la pestaña oculta y fuera de pantalla, y se detiene solo tras 30 s en movimiento: después es manual con las flechas (WCAG 2.2.2: pausa por cursor, foco y toque; sin botón de pausa). Con menos de 4 marcas no se mueve. Con `prefers-reduced-motion: reduce` no se mueve sola: queda una fila con scroll |
| Carrusel del hero (S7b-A) | Tarjeta de piezas en el espacio libre a la derecha del título, desde 992 px (por debajo se oculta): 400 px de ancho (444 desde 1200 px: foto 16 : 9 de unos 250 px de alto), centrada en vertical dentro de la altura del hero, que no cambia (400 px). Foto 16 : 9 con `object-fit: cover` (se recorta, nunca se deforma), leyenda debajo de la foto, con la barrita de 5 s entre las dos (leyenda: nombre `title-sm`, marca y modelo `body-sm`, precio `product-price` a la izquierda y «Ver detalle →» a la derecha como botón secundario), radio `{rounded.xl}`, borde fino y sombra suave `0 6px 16px` con `{colors.canvas-dark}` al 30 % (`color-mix`). Flechas: chevrones simples (‹ ›) sin fondo ni círculo, centrados en vertical sobre la foto y dentro de ella, en `{colors.on-dark}` con sombra suave (opacidad .8, 1 al pasar el cursor o enfocar), con zona de clic de 44 px invisible. Puntos de 8 px debajo (el activo en `{colors.primary}`, zona de 24 px). Bloque tarjeta + puntos centrado en vertical en el hero con el mismo espacio arriba y abajo y 12 px entre la tarjeta y los puntos; leyenda con relleno de 12 px arriba y abajo y 16 px a los lados. Barrita de 3 px entre la foto y la leyenda: riel con `{colors.primary}` al 22 % y relleno en `{colors.primary}` que se llena de izquierda a derecha en 5 s con `transform: scaleX` (nada de `width`); se detiene con la pausa, se reinicia al cambiar de pieza, queda vacía tras la vuelta completa y con `prefers-reduced-motion` no se muestra. Zoom de la foto al pasar el ratón: `scale(1.05)` en 400 ms `ease-out` dentro del marco con `overflow: hidden` (solo con puntero fino y sin movimiento reducido); la foto que entra se asienta de `scale(1.04)` y opacidad 0 a su sitio en 600 ms. Cambio cada 5 s con fundido de opacidad de 500 ms (no se anima tamaño); se pausa con el cursor, el foco del teclado y el toque, y se detiene solo tras una vuelta completa: después es manual con flechas y puntos. Sin botón de pausa. Con `prefers-reduced-motion: reduce` no cambia sola. Sin JavaScript queda la primera pieza. Las secciones listadas en `portada.hero_excluir_secciones` de `marca.php` no aportan piezas. Autorizado por Tony (2026-10-29) |
| Fuera de alcance | contadores y entrada de los gráficos del dashboard: se harán en S6a (la tarjeta KPI conserva por ahora su sombra animada) |
| Movimiento reducido | con `prefers-reduced-motion: reduce` no hay aparición, ni desplazamiento en hover ni animaciones (regla global de `base.css`); los cambios de color y de borde siguen, sin transición |

## Qué hacer y qué no

### Qué hacer
- Reservar el amarillo (`{colors.primary}` y su variante como texto, `{colors.primary-text-on-dark}`) para acciones principales, la marca y los precios. Su escasez es su fuerza.
- Mantener `button-primary` idéntico en los dos modos.
- Usar la fuente numérica para toda cifra.
- Elegir el modo con el interruptor del usuario, oscuro por defecto, sin cambiar de tema según la pantalla.
- Cuidar el contraste: texto normal 4,5:1, texto grande y componentes 3:1, comprobado con un script en ambos modos.
- Mantener las zonas táctiles de al menos 44 px en móvil.

### Qué no hacer
- No introducir un segundo color de marca. Un nuevo acento se obtiene cambiando el color principal, no añadiendo otro.
- No usar amarillo en texto sobre fondo claro, ni en rellenos grandes de superficie.
- No usar verde ni rojo como fondo de tarjetas: son señales de estado, en texto o en insignias pequeñas.
- No suavizar el peso de los titulares ni añadir degradados atmosféricos.
- No invertir el texto del botón principal: negro sobre amarillo es la firma del sistema.
- No indicar un estado solo con color.
- No copiar marcas, nombres, textos, ilustraciones ni tipografías de terceros.

## Comportamiento responsivo

| Nombre | Ancho | Cambios clave |
|---|---|---|
| Móvil | < 768 px | **Se diseña primero.** Menú plegable que se cierra al elegir; buscador en fila propia; título principal a 36 px; catálogo en filas horizontales (< 576 px) o 2 columnas; lema de la marca oculto; filtros plegables; tablas del panel como tarjetas; pie en 1 columna; sin desplazamiento horizontal |
| Tableta | 768–1024 px | Menú horizontal más compacto; catálogo en 3 columnas (E1); lema de la marca visible |
| Escritorio | 1024–1440 px | Menú completo; catálogo en 3 columnas hasta 1199 px y en 4 desde 1200 px; tablas completas |
| Ancho | > 1440 px | Igual, con más margen; contenedor limitado a 1280–1440 px |

### Zonas táctiles
- Botones y campos: 44 px de alto mínimo en táctil. Ningún botón de 28 px en móvil.
- Enlaces del pie y de los formularios con suficiente separación, para que una fila entera se pueda tocar.
- El texto base de la tienda es de 16 px; el del panel, 14 px; los campos, 16 px.

## Personalización (plantilla vendible)

Toda la paleta se **deriva de un único color principal** (hoy `{colors.primary}`), más unos pocos ajustes. Un comprador cambia ese valor y el resto sigue, en ambos modos.

| Token derivado | Cómo se obtiene |
|---|---|
| `primary-active` | El principal, un poco más oscuro (en el dorado: #FCD535 pasa a #F0B90B) |
| `primary-disabled` | El principal mezclado con el lienzo oscuro, con baja intensidad |
| `on-primary` | Texto oscuro o blanco, el que dé al menos 4,5:1 sobre el principal |
| `link-on-light` | El principal oscurecido, conservando el tono, hasta alcanzar 4,5:1 sobre el suelo claro (en el dorado: #7A5C00) |
| `primary-text-on-dark` | El principal como texto o precio sobre oscuro: se aclara, conservando el tono, hasta 4,5:1 sobre lienzo, tarjeta y superficie elevada (en el dorado coincide con el principal) |
| `focus-ring` | Un color que cumpla 3:1 sobre cada superficie; se puede mantener el azul `info`, salvo que el principal esté a menos de 35° de tono del azul: entonces blanco en oscuro y tinta en claro |

La derivación la hace un **script de línea de comandos** que recibe el color principal y genera `assets/css/tema.css` derivado y validado (se implementa al aplicar el diseño al sitio). Se valida con tres colores de prueba (dorado, naranja y azul) en ambos modos con un script de contraste; si un color elegido no pasa, el script avisa y escribe el valor corregido (por ejemplo, el `primary-active` del naranja se corrige a #DB5D06 y el principal del azul como texto sobre oscuro a #6997F1). Los textos, el nombre, el logo y los datos del negocio viven en `app/Config/marca.php`, no en las vistas, y los valores de color en `assets/css/tema.css`.

## Adaptaciones respecto al análisis base

| # | Cambio | Motivo y cifra |
|---|---|---|
| 1 | Tipografía: Manrope y IBM Plex Sans autoalojadas en lugar de las fuentes propietarias del análisis | Las originales tienen licencia propietaria y no se pueden usar ni vender |
| 2 | Texto secundario sobre oscuro: `muted-strong` en lugar de `muted` | `muted` da 3,65:1 sobre tarjeta oscura y 4,46:1 sobre el lienzo (AA exige 4,5:1) |
| 3 | Enlaces y texto de acento sobre claro: `link-on-light` en lugar del amarillo | El amarillo como texto sobre blanco da 1,43:1 |
| 4 | Texto secundario sobre claro: `muted-on-light` | `muted` da 4,34:1 sobre blanco y 3,91:1 sobre el suelo #F7F3E8 |
| 5 | Verde y rojo reasignados a éxito y error, con variantes para el modo claro | Sobre blanco el verde vivo da 2,13:1 y el rojo base #F6465D 3,53:1; no hay trading en la tienda |
| 6 | Botones rellenos de verde o rojo con texto oscuro (o variantes oscuras con texto blanco) | Blanco sobre #0ECB81 da 2,13:1 y sobre #F6465D 3,53:1 |
| 7 | Foco: anillo sólido de 2 px (no al 50 % de opacidad) | El anillo semitransparente no alcanza 3:1 |
| 8 | Modo claro en todo el sitio, con suelo cálido #F7F3E8 en lugar de blanco puro | El análisis base usa el modo claro solo en superficies transaccionales; aquí hay un interruptor global. Con #FAFAFA las tarjetas blancas se separaban del suelo 1,04:1 y se veía plano; con #F7F3E8 son 1,11:1 y combina con el dorado |
| 9 | Zonas táctiles de 44 px, campos y texto base de la tienda a 16 px | Decisión de accesibilidad AA con 44 px; evita que iOS amplíe la pantalla |
| 10 | Bordes de campos: `input-border-dark` y `input-border-light` | Los divisores decorativos dan 1,2 a 1,5:1; los campos necesitan 3:1 |
| 11 | Se eliminan los componentes de trading, mercados, QR, "Smart Money", la arena, las cookies de ejemplo y las monedas 3D, y se reemplazan por componentes de tienda | Son de otra industria |
| 12 | El pie de página sigue el tema; se elimina el pie claro sobre fondo oscuro | Es un rasgo de marca de un tercero; aquí hay interruptor global |
| 13 | El turquesa del análisis base ya no forma parte del sistema | Era un acento de un solo producto |
| 14 | Se documentan los estados de hover y de foco | El análisis base solo documenta los estados por defecto y pulsado |
| 15 | `danger` oscuro pasa de #F6465D a #F6475E | Como texto sobre tarjeta oscura daba 4,48:1 (AA exige 4,5); con #F6475E da 4,51:1 |
| 16 | Nuevo `danger-on-elevated` #F8677A | El texto rojo sobre el modal o superficie elevada daba 3,72:1; con #F8677A da 4,50:1 |
| 17 | `input-border-light` pasa de #848E9C a #838D9C | Con el suelo cálido, el borde de un campo sobre el suelo daba 2,99:1; ahora 3,03:1 (3,36:1 sobre blanco) |
| 18 | `surface-strong-light` pasa de #F5F5F5 a #F2ECDE (pie claro cálido) | Combina con el suelo cálido; todos los textos del modo claro quedan ≥ 4,5:1 (el más bajo, `success-on-light`, 4,56:1) |
| 19 | Provisional aprobado: botón principal deshabilitado en claro (`button-primary-disabled-on-light`) | El archivo base solo definía el deshabilitado sobre oscuro; fondo `surface-strong-light`, texto `muted-on-light`, borde `border-strong` (los controles deshabilitados están exentos de contraste) |
| 20 | Provisional aprobado: fondo de portada y superficie elevada en claro (`hero-band-light`, `surface-elevated-light`) | El archivo base solo definía la banda de portada y la superficie elevada sobre oscuro |
| 21 | Nuevo token `primary-text-on-dark` | Con un principal oscuro (el azul de prueba) el principal como texto da 2,87:1 sobre oscuro; el token lo aclara hasta 4,5:1 (en el dorado coincide con el principal) |
| 22 | Nombre de la pieza en tarjeta: `title-sm` (16/600) en todos los anchos. Aprobada por Tony (2026-10-25) | Con 4 columnas y la barra lateral la tarjeta mide ~220 px y `title-md` partía el nombre en tres líneas |
| 23 | E1: catálogo en 3 columnas desde 768 px (antes 2 en tableta) | A 768 px, 2 columnas daban fotos de 348 px, demasiado grandes. Aprobada por Tony (2026-10-25) |
| 24 | E2: precio de tarjeta y detalle a 20/600 con token `--precio`: amarillo (`primary-text-on-dark`) en oscuro y bronce (`link-on-light`, 6,25:1 sobre blanco) en claro. El verde queda solo para éxito y stock disponible | El sitio pintaba los precios en verde (`success`), contra lo que decía este archivo. Aprobada con el color decidido por Tony |
| 25 | E3: valor de las tarjetas KPI a 32 px (IBM Plex Sans 32/700) | Hoy 30,4 px; se alinea con la escala (`display-sm`) |
| 26 | E4: baldosa blanca fija (`canvas-light`) para toda foto de producto, en los dos modos | Las fotos de pieza suelen tener fondo blanco: sobre tarjeta oscura mostrarían un rectángulo |
| 27 | E5: relleno lateral del contenedor 16 / 24 / 32 px | No estaba definido |
| 28 | E6: especificación de la marca del encabezado (`brand-lockup`), con el lema oculto por debajo de 768 px y una sola versión del logo (sin variante clara) | Hoy el lema (9,28 px) queda 13 px lejos del nombre y el logo tiene un filtro CSS |
| 29 | E7: barra superior de 64 px en la tienda y en el panel | Hoy mide 81 px en la tienda y 58 en el panel |
| 30 | E8: componentes del carrito en dos pasos (`checkout-steps`, cantidad − / + de la línea y resumen del pedido), hechos solo con tokens existentes. Aprobada por Tony (2026-10-25) | La compra pasa a dos pasos ("Carrito" y "Tus datos"); ninguno de los tres componentes existía. No añaden colores ni medidas nuevas (ver "Carrito en dos pasos") |
| 31 | El icono del logo va en `{colors.primary}` (amarillo de marca) en los dos modos, token `--logo`; excepción documentada a la regla de contraste porque el nombre va escrito al lado. Decisión de Tony (2026-10-26) | En claro el bronce `link-on-light` no gustaba; el amarillo sobre el suelo claro da ~1,4:1, aceptado por ser un logotipo con el nombre al lado |
| 32 | Controles de 40 px en escritorio y 44 px en táctil, aplicado en la regla global de `base.css` (antes campos de 36 px y botones de 28 a 32 px). Corrección de implementación, no cambia el diseño | DESIGN.md ya lo pedía (`button-primary`, `search-input-on-dark`, `text-input-on-light`) |
| 33 | S5.4: movimiento extendido a todo el sitio (contenido principal, aparición escalonada, botones, menús, cajón y modal), flecha propia en todos los `select`, `.enlace-volver` y formularios del panel a 720 px. Pedido por Tony (2026-10-26) | Hasta S4.5 solo la portada se movía y los desplegables parecían texto fijo; las reglas de duración, propiedades y movimiento reducido son las de la sección «Movimiento» |
| 34 | S6a: paleta categórica de gráficas (`chart-1` a `chart-6` y `chart-otras`, ver «Gráficas»). Aprobada por Tony (2026-10-26) | Las gráficas del dashboard necesitan seis tonos distinguibles que no sean los de estado; salen del generador de tema y se validan en los dos modos |
| 35 | S7: logos de marca en un carrusel continuo en la portada, casilla de marca (96×44 y 128×56 px), logo en una sola tinta con monograma por defecto, flechas y pausa de 44 px, y el movimiento continuo a 30 px/s (ver «Movimiento» e «Iconos y fotografía»). Aprobada por Tony (2026-10-29) | La portada solo tenía tarjetas de texto; el administrador necesita subir logos de marcas nuevas y la única animación permitida era de 150 a 250 ms. Solo tokens existentes, ningún color nuevo |
| 36 | S7b-A: carrusel de piezas en el hero, cuadro «Por qué elegirnos» y sombra suave `0 6px 16px` con `{colors.canvas-dark}` al 30 % en esas dos tarjetas (ver «Movimiento» y «Cuadro Por qué elegirnos»). Autorizada por Tony (2026-10-29) | La portada no tenía fotos de piezas ni presentación del negocio; la sombra sale del color del lienzo oscuro, ningún color nuevo |

## Guía de iteración

1. Trabajar **un componente a la vez**, citando su clave del YAML (`{components.button-primary}`).
2. Al añadir un componente, definir primero su versión oscura y su versión clara.
3. Las variantes (`-active`, `-disabled`) son entradas separadas de `components:`.
4. Citar siempre los tokens en vez de valores sueltos; no escribir colores en las vistas.
5. Toda cifra va en la fuente numérica.
6. Verde y rojo son estados (éxito y error), nunca decoración ni color de precio.
7. Antes de dar por terminada una pantalla: contraste en ambos modos, vista en 390 px y en 1280 px, foco visible con teclado, y `composer test` en verde.

## Lagunas conocidas

- Los estados de formulario más allá del campo básico (error, éxito, deshabilitado) deben definirse al implementar y medirse.
- Las animaciones quedan formalizadas en la sección «Movimiento»; fuera de ella no se añaden otras.
- La pantalla del panel para editar el tema queda como bosquejo futuro; por ahora se edita `assets/css/tema.css`.
- Falta decidir el logo y el nombre definitivos de cada comprador de la plantilla.
