# Auditoría de diseño y UX — Rastro del Istmo (solo lectura)

Fecha: 2026-10-24. Rama `mejoras`. Navegador integrado con la sesión de Administrador. No se editó ningún archivo del proyecto, no se tocó ninguna base y no se hizo commit.

## 0. Cómo se midió (y qué no se cubrió)

- Viewports: 390x844 (móvil), 768x1024 (tableta, pantallas principales), 1280x800 y 1366x768 (escritorio). Contraste calculado con JavaScript sobre colores calculados (mezcla de transparencias incluida). Objetivos táctiles, fuentes, colores, alt, etiquetas, encabezados, desbordes y foco medidos con script por pantalla.
- Pantallas abiertas: portada (y su menú), catálogo (pág. 1, pág. 5 = última, sin resultados), detalle (id 21 con 1 unidad, id 49), carrito vacío y con 1 pieza, formulario de compra, mis pedidos, mi perfil, login, registro, olvidé, restablecer sin token, 404, URL de pieza inexistente. Panel: dashboard, partes (lista, nueva, editar), autos (lista, nuevo), secciones (lista, nueva), ventas (lista, nueva), pedidos (lista y detalle del #1 entregado), usuarios (lista, nuevo, editar, mi clave), roles (lista, nuevo), comentarios, reportes.
- Para ver login/registro/olvidé/restablecer/404 como visitante sin cerrar tu sesión usé `http://127.0.0.1/...` (otro origen, sin tu cookie). Desde ahí no se envió nada. Para escanear colores de varias pantallas cargué páginas con `fetch` GET en un iframe oculto.
- Acciones que cambian estado: solo "Añadir al carrito" y "quitar" (las que autorizaste); el carrito quedó vacío. Ningún formulario enviado. Sesión de Administrador intacta. Navegador devuelto a escritorio; el almacenamiento local que usé para mi script quedó borrado.
- Un tropiezo: mi primera búsqueda de símbolos abarcó todo el proyecto y devolvió una línea de `app/Config/config.php` (un comentario de la línea 10, sin datos sensibles). No volví a incluirlo.
- **No cubierto:** envío de formularios (estados de error del servidor, validación), `confirm()` (el navegador los cancela), `compra_confirmar` y `compra_exitosa` (requieren POST), pedidos en estados distintos de "entregado" (no hay), una pieza agotada o sin imagen (el catálogo público solo lista piezas con stock y las 49 tienen foto), la página de error 500 (no se provocó; se revisó por código), hover real (se leyó por CSS), mis pedidos con pedidos, contraste del texto sobre la foto del hero (la foto lleva una capa oscura que mi cálculo no ve; marcado como "revisar a ojo"), registro/olvidé/restablecer solo en escritorio, tableta solo en pantallas principales.
- Las capturas del navegador a 1280 salen recortadas por el tamaño del panel; las conclusiones se basan en las mediciones, no en las capturas.

## 1. Resumen

**Conservar:** identidad oscura + dorada coherente; tokens ya centralizados en `:root` de `assets/css/style.css:5-53`; catálogo con filtros, estado vacío con salida, paginación accesible (`nav aria-label`, "Mostrando 1–12 de 49"); detalle con precio, stock y compra claros; carrito con desglose de ITBMS; cero errores de consola, cero imágenes rotas, todas las peticiones 200; fotos con `alt` = nombre de la pieza; `lang="es"`, `<title>` por página, viewport; botones primario (dorado) y secundario (contorno) bien jerarquizados en formularios; tablas del panel dentro de `.table-responsive`; matriz de permisos visible.

**Los 5 problemas más graves:**
1. El botón principal dorado (`.btn-gold`/`.btn-warning`) no muestra foco con teclado, y los demás indicadores de foco son un azul de Bootstrap a 25 % (1,3:1) o grises (1,7–1,9:1).
2. Desplazamiento horizontal en móvil y tableta en portada y detalle (12–13 px), por `row g-5` (`home.php:117`, `detalle.php:23`; igual en `compra.php:7` y `compra_confirmar.php:7`, esas dos sin medir en móvil).
3. Casi ningún campo tiene nombre accesible: 76 `<label>` pero solo 5 `for=`; además no hay `<main>`, ni enlace de salto, ni `h1` fuera de la portada.
4. El menú de la portada marca mal la sección (al ir a "Quiénes somos" queda marcado "Contacto"), no actualiza la URL, deja el título de la sección tapado por la barra fija y en móvil no se cierra al elegir.
5. Comprar obliga a crear cuenta, y el contraste falla en muchos textos (`#848E9C` sobre `#2B2F36` = 4,05:1; insignia de stock 2,77:1; `.text-success` 3,59:1; pie del panel 1,91:1).

## 2. Hallazgos por severidad

Estándares: WCAG = https://www.w3.org/WAI/WCAG22/Understanding/ + criterio. Baymard y NN/g con enlace en la sección 4.

### Alta

| # | Dónde | Qué pasa | Evidencia | Estándar | Arreglo general |
|---|---|---|---|---|---|
| A1 | Todo el sitio | `.btn-gold` sin indicador de foco; enlaces de menú con aro azul Bootstrap translúcido; `outline-secondary` gris; sin regla `:focus-visible` propia | `style.css:146-152` (sin foco); medido con Tab: `btn-gold` "NINGUNO", `nav-link` 1,30:1, `outline-secondary` 1,7–1,9:1, `page-link` 1,29:1. 0 reglas `focus-visible` en `style.css`/`extra.css` | WCAG 2.4.7 (AA), 1.4.11 (AA, 3:1) | Una sola regla global `:focus-visible` con contorno de 2 px y offset, color del token de foco con ≥3:1 sobre cada fondo |
| A2 | Portada, detalle (compra y compra_confirmar usan lo mismo) | Desplazamiento horizontal: la fila con gutter 3rem sobresale del contenedor de 0,75rem | 390 px: ancho de contenido 402 vs 390; 768 px: 774 vs 762. `home.php:117`, `detalle.php:23`, `compra.php:7`, `compra_confirmar.php:7` | WCAG 1.4.10 (AA) | Gutter horizontal pequeño en móvil (`g-3 gx-md-5` o equivalente) |
| A3 | Todos los formularios y los dos buscadores | `<label>` sin `for` y sin envolver el campo; buscadores solo con placeholder | 76 `<label>` / 5 `for=` en `app/Views`; listas de "sin nombre" en catálogo (marca, precio, orden), carrito, perfil, panel, matriz de permisos (32 casillas) | WCAG 1.3.1, 3.3.2, 4.1.2 (A); NN/g: no usar placeholder como etiqueta | `for`/`id` en cada campo; `aria-label` en buscadores y casillas |
| A4 | Menú público (portada) | (a) tras pulsar "Quiénes somos" queda activo "Contacto"; (b) la URL no cambia; (c) la sección queda con `top=0` bajo la barra fija; (d) en móvil el menú abierto (290 px) no se cierra | Reproducido: clic en "Quiénes somos" a 1280x800 y 1366x768 → `Conta:ACTIVE`; cargar `/#nosotros` → igual. `nosotros.top=0`. Móvil: `menuOpen:true` tras el clic. Detalle en 3.1 | WCAG 2.4.11 (AA), 4.1.2; NN/g (estado del sistema) | Ajustar el margen de observación del ScrollSpy, `scroll-margin-top`, cerrar el colapsable al elegir |
| A5 | Carrito y compra | Para pedir hay que crear cuenta (contraseña obligatoria); además al ser Administrador el carrito mostró el formulario de invitado | `carrito.php`: "Crear cuenta y pedir", campo `nueva_pass`; `compra.php`: "Crear cuenta y comprar" | Baymard: 19 % abandona si se obliga a registrarse | Compra como invitado visible y crear cuenta después (decisión de negocio) |
| A6 | Varias | Contraste de texto insuficiente | `#848E9C` sobre `#2B2F36` = 4,05:1 (cabeceras de tabla, etiquetas de stats, filtros, "Kia Rio" en compra, fecha del dashboard); insignia `badge-stock-ok` blanco sobre `#07B270` 2,77:1 (`style.css:314`); `.text-success` `#198754` sobre `#1E2026` 3,59:1 (precio del carrito, total del pedido); `#474D57` sobre `#1E2026` 1,91:1 (pie del panel, "Anterior/Siguiente" deshabilitados); `review-empty` 2,27:1; chips `info` 4,29 y `danger` 4,07 | WCAG 1.4.3 (AA) | Subir `--text-2` y `--text-3`; tokenizar verde/dorado de Bootstrap; texto oscuro en insignias claras |
| A7 | Estructura | Sin `<main>`, sin enlace "saltar al contenido", `h1` solo en la portada, saltos h4→h6 | 0 `<main`/`skip` en `app/Views`; catálogo: `4:Catálogo Completo`→`6:`; carrito `h4`→`h5` | WCAG 2.4.1 (A), 1.3.1, 2.4.6 | `<main id>` + skip link + un `h1` por página |

### Media

| # | Dónde | Qué pasa | Evidencia | Estándar | Arreglo |
|---|---|---|---|---|---|
| M1 | Táctil | Zonas menores de 44 px y varias menores de 24 | Menú móvil 350x31; carrito/iconos 32x31; acciones de tabla 29x31; casillas de usuarios 13x13 y de permisos 16x16 (sin etiqueta asociada); breadcrumb del detalle 38x22 y 64x22; login "¿Olvidaste…" 164x19, "Volver a la tienda" 124x18 | WCAG 2.5.8 (AA, 24 px); web.dev 48 px + 8 px de separación | Altura mínima 44 px en móvil (`any-pointer: coarse`) |
| M2 | Color | Dos dorados, dos verdes, rojos y azules de Bootstrap fuera de paleta | `.text-warning` = `#FFC107` (144 usos) vs `--accent #F0B90B`; `.text-success` = `#198754` (11) vs `--success #03C076`; `invalid-feedback` `#DC3545` vs `#F6465D`; foco azul `rgba(13,110,253,.25)`; `.btn-primary/.btn-secondary/.alert-primary/.alert-secondary/.btn-light/.badge bg-danger` salen con colores por defecto (probados en un contenedor temporal) | Coherencia (NN/g) | Redefinir `--bs-*-rgb` en `:root` |
| M3 | Estados | Campo inválido sin borde rojo; deshabilitado casi igual al activo | `.dark-input`/`.form-control` con `!important` (`style.css:124-129`) anulan `.is-invalid`; deshabilitado = solo `opacity .65` | WCAG 3.3.1, 1.4.1; NN/g (errores junto al campo con color + icono) | Estados `is-invalid`/`:disabled` propios |
| M4 | Mensajes | Avisos sin `role`, se cierran solos a los 4,5 s; `btn-close` sin nombre | `public_header.php:80`, `main.js:6-8` | WCAG 4.1.3 (AA) | `role="status"`/`alert`, `aria-label` |
| M5 | Iconos | 88 iconos distintos; botones solo-icono sin nombre (menú hamburguesa público y del panel, carrito, buscar, mostrar contraseña, eliminar, salir, llave); solo 6 `aria-hidden` en vistas | `public_header.php:33,47,51`; `iconOnlyNoName` en cada página | WCAG 4.1.2 | `aria-label` + `aria-hidden` en iconos decorativos |
| M6 | Tablas del panel en móvil | Solo se ven 4 de 9 columnas; precio, stock y acciones fuera de vista, sin pista de desplazamiento | partes: 701>341 px; ventas 703; pedidos 777; usuarios 775; comentarios 636 | NN/g (tablas móviles); WCAG 1.4.10 (las tablas están exentas) | Columnas prioritarias o tarjetas; acciones siempre visibles |
| M7 | Panel (listas) | Pedidos, ventas, usuarios, comentarios sin filtros ni orden por columna; usuarios (16) sin paginar; cabeceras no fijas | listas medidas | NN/g (tablas); GitLab (>20 filas → paginar) | Filtros por estado/fecha; cabecera fija |
| M8 | Catálogo | "12 piezas encontradas" mientras la paginación dice "de 49"; en la última página "1 pieza encontrada" | `catalogo.php:15` usa `count($partes)` (la página), no el total | NN/g (estado del sistema) | Mostrar el total |
| M9 | Errores | 404 de Apache: sin estilo, Times New Roman, y muestra "Apache/2.4.65 … PHP/8.3.28 … Port 80"; pantalla de error 500 sin tema (fondo blanco, enlace azul) | medido en `127.0.0.1/rastroinventario/noexiste.php`; `bootstrap.php:35-40` | NN/g (mensajes de error útiles) | `ErrorDocument` y plantilla temática |
| M10 | Peso | `logo.png` 2 MB (1536x1024) se muestra a 33x22 y 48x32; `hero-bg.jpg` 2 MB; `hero-bgg.jpg` 714 KB sin uso aparente; miniaturas de ~315 px se estiran a 361 px en tableta y se recortan de ~1:1 a 4:3 | `assets/img`; `cropped` en catálogo | rendimiento | Versiones optimizadas y `srcset` |
| M11 | Campos | Sin `autocomplete` en nombre, teléfono y correo (carrito, compra, perfil, registro, panel) | `noAutocomplete` en 7 pantallas | Baymard (autocompletar); WCAG 1.3.5 (no leído en esta sesión) | atributo `autocomplete` |
| M12 | Confirmaciones | `confirm()` nativo en quitar/eliminar/entregar; desactivar usuarios sin ninguna (ya anotado en ESTADO.md) | `carrito.php`, `partes/index.php:52`, `autos`, `secciones`, `comentarios`, `pedidos/detalle.php:62` | NN/g: confirmaciones específicas, botones con el verbo | Modal propio con nombre del objeto |
| M13 | Movimiento | Tarjetas suben 4–6 px al pasar el cursor, zoom de imagen; sin `prefers-reduced-motion` | `style.css:230,246,308,311`; 0 coincidencias de `prefers-reduced-motion` | WCAG 2.3.3 (AAA, buena práctica); web.dev | Media query para anular |
| M14 | Plantilla | Datos fijos en el código: nombre, teléfono, correo, horario, texto de "Quiénes somos", crédito "Desarrollo de Software VII … UTP"; datos personales reales en la demo (correos y teléfonos de clientes en pedidos y usuarios) | `public_footer.php:6-24`, `admin_footer.php`, `home.php:124-130`; lista de pedidos | — | Pasar a configuración; datos de demo ficticios |
| M15 | Botón flotante "Volver" | Creado por JavaScript con `history.back()`, fijo sobre el contenido, solo `title`, aparece solo si la URL tiene `action=`/`seccion=` | `main.js:26-36`, `extra.css:32-39` | WCAG 4.1.2; 2.5.8 ok (48 px) | Quitar o convertirlo en enlace real con nombre |

### Baja

| # | Dónde | Qué pasa | Evidencia |
|---|---|---|---|
| B1 | Tipografía | 25 tamaños distintos y 5 pesos (400/500/600/700/800), textos de 9,28 px ("RASTRO & PARTES", `public_header.php:30`) y 11,52 px (insignias) | acumulado de todas las pantallas |
| B2 | Tipografía | Controles 14 px en la tienda y 16 px en login/formularios del panel; textarea 16 px junto a inputs de 14 px (detalle); título de página 24 px en tienda, 16,8 px en el panel | medido |
| B3 | Fechas | Cuatro formatos: `2026-07-12 00:23:37` (ventas, bitácora), `12/07/2026 00:22` (pedidos), `24 Oct 2026, 17:44` (dashboard), `11/07/2026` (usuarios); IP `::1` en la bitácora | medido |
| B4 | Portada | "49+" cuando el total es exacto; "Piezas destacadas" = mayor stock; hero título 800 de peso en Segoe | `home.php:29,72` |
| B5 | CSS | Reglas duplicadas entre `style.css` y `extra.css` (`.sec-card`, `.hero-*`, `.stats-bar`, `.about-section`, `.public-footer`); `!important` masivo; `.dark-table` repite a `.table` (`style.css:100-121` y `258-261`); ~60 estilos en línea | grep |
| B6 | Menú | `main.js:60-67` añade `nav-active` por prefijo de URL además de ScrollSpy y del `active` de PHP (tres mecanismos para lo mismo); el detalle marca "Catálogo" pero carrito, perfil y pedidos no marcan nada | `public_header.php:10`, `main.js` |
| B7 | Landmarks | Dos `<nav>` en el detalle sin `aria-label` (menú y breadcrumb) | `detalle.php:16` |
| B8 | Logout | "Salir" es un botón rojo permanente en el panel y un GET (ya anotado) | `admin_header.php` |
| B9 | Detalle inexistente | `?action=detalle&id=99999` redirige a la portada con aviso (bien), pero un módulo de panel inexistente redirige sin aviso | `PublicController.php:34`, `admin/index.php:35` |
| B10 | iOS | Inputs de 14 px en la tienda: Safari en iPhone amplía la pantalla al enfocar campos menores de 16 px (comportamiento conocido; no verificado con fuente en esta sesión) | medido 14 px |

## 3. Agrupado por tipo

### 3.1 Menú y navegación

- Causa del fallo del menú (`public_header.php:23`, `home.php`): la portada usa ScrollSpy de Bootstrap 5.3.3 con `data-bs-offset="90"`. En 5.3 esa opción ya no se usa; el observador usa un margen por defecto (`0px 0px -25%`). Las secciones son bajas y `#contacto` es el pie (349 px). Al saltar a "Quiénes somos" (queda en `top=0`), el pie entra en la banda observada y gana la última sección que intersecta: queda marcado "Contacto".
- Inicio marcado a la vez: no lo reproduje tal cual. Lo más parecido: en cuanto la sección activa depende de la altura de la ventana, el marcado cambia con el tamaño; probé 800 y 768 px de alto. Conviene volver a probar con tu ventana exacta.
- Otros parecidos: (1) el título de cualquier sección queda debajo de la barra fija (sin `scroll-margin-top`), con la barra a 81 px en escritorio y 290 px en móvil con el menú abierto; (2) la URL no cambia, así que no se puede compartir ni usar "atrás" (`href` queda sin hash); (3) Inicio al pulsarlo deja la página en `y=81`; (4) las páginas internas (carrito, perfil, pedidos, login) no marcan ninguna opción.
- En móvil: menú abierto ocupa 34 % de la pantalla, los elementos miden 31 px de alto y no se cierra al pulsar un enlace; el botón no tiene nombre ni `aria-controls`.
- Cuentas: cliente = menú desplegable con perfil y pedidos (no probado con sesión de cliente); administrador = botón "Admin".

### 3.2 Emojis y símbolos

No hay pictogramas emoji (U+1F300 en adelante) en vistas, controladores, modelos ni JS; solo en pruebas (`🚗` en `SanitizerTest.php:36` y `ValidatorPasswordTest.php:21`, donde son datos de prueba).

| Dónde | Símbolo | Tipo | Reemplazo |
|---|---|---|---|
| `VentaController.php:39`, `ParteController.php:35`, `PedidoModel.php:82,88,103` | ✔ al final de mensajes de éxito | Decorativo (el aviso ya es verde) | Quitarlo; el icono `bi-check-circle-fill` lo pone la caja de aviso |
| `detalle.php:88-92` | ★ en las 5 etiquetas de la calificación | Funcional, pero es texto y solo tiene `title` | Icono `bi-star-fill` + texto oculto "5 estrellas" |
| `catalogo.php:55-56`, `partes/index.php:22-25` | ↑ ↓ en opciones de orden ("Precio ↑") | Funcional, ambiguo | "Precio: menor a mayor" |
| `partes/index.php:15`, `dashboard.php:17` | ≤ en "Stock bajo (≤5)" | Funcional | "5 o menos" |
| `paginacion.php:11-27` | « » | Funcional | `bi-chevron-left/right` + texto |
| `carrito.php:53` | × en "×1" | Funcional | Mantener |
| varias | … — – · | Tipográficos | Mantener |

Aspecto "de plantilla" (sin ser emoji): 88 iconos de Bootstrap Icons distintos, un icono en casi cada título y botón; el cuadrito redondeado con icono dorado (`sec-icon`, `contact-icon`, `seccion-icon`); iconos de colores sueltos en "Quiénes somos" (verde, dorado, azul, verde: `home.php:127-130`); texto pequeño en mayúsculas con espaciado y un icono ("RASTRO DE AUTOS — PANAMÁ"); palabra clave en dorado en cada título ("mejor precio", "Sección", "Destacadas", "confianza"). Recomendación: mantener un solo estilo de icono, reservarlos a acciones y navegación, no a decoración.

### 3.3 Tipografía

- Fuente aplicada (calculada): `"Segoe UI"` en cuerpo, h1–h6, botones, inputs, selects, textareas, tablas, tarjetas, avisos, enlaces, etiquetas, insignias, `dropdown-item`, `page-link`. Ningún control cae a Times, Arial o Courier: los controles heredan bien.
- Excepciones: `<code>` usa la pila monoespaciada de Bootstrap (SFMono-Regular… ; 30 nodos; IP en `dashboard.php:41`); 404 de Apache = Times New Roman; `paginaError` = `sans-serif` (`bootstrap.php:37`).
- Riesgo de plantilla: `Segoe UI` solo existe en Windows; no se carga ninguna fuente web. En Mac/Android/Linux se verá otra fuente (`system-ui`), con otras métricas.
- Escala: 25 tamaños y 5 pesos (ver B1, B2). Tamaños dominantes: 14 px (950 nodos), 16 px (499), 12,48 (354), 11,52 (317), 12 (184), 12,8 (158).
- Peso/estilo de etiquetas: 12,8 px/600 mayúsculas en formularios; cabeceras de tabla 12 px mayúsculas con contraste 4,05.
- Interlineado: 1,5 en cuerpo; botones 21/14. Longitud de línea: no medida en caracteres; las columnas de texto largo (descripción, "Quiénes somos") van en ~600 px, lo que parece aceptable pero no está verificado.

### 3.4 Color

Texto calculado en uso (nodos): `#848E9C` (1112; `--text-2`), `#EAECEF` (884), `#FFFFFF` (248; fuera de token), `#03C076` (224), `#FFC107` (144; **fuera de paleta**), `#000000` (108; texto sobre dorado), `#F0B90B` (78), `#474D57` (45; `--text-3`, falla contraste), `#1890FF` (32; info), `#198754` (11; **fuera de paleta, falla**), `#0B0E11` (7), `#F6465D` (7).
Fondos: `#2B2F36`, `#1E2026`, `#F0B90B`, verdes/rojos/azules translúcidos, `rgba(3,192,118,.9)` (insignias), y un `#F0F0F0` en el botón de envío oculto del carrito (invisible).
Bordes: `rgba(255,255,255,.08)` (623) y los tonos de estado.
Fuera de paleta o con problemas: `#FFC107`, `#198754`, `#DC3545`, `rgba(13,110,253,.25)` (foco), `rgba(108,117,125,.5)` (foco secundario), `rgba(255,193,7,.5)` (foco de `outline-warning`), blanco puro. `--info #1890FF` pertenece a la paleta pero es un segundo color de acento azul en un tema dorado (chips "Cliente", estado "procesando", iconos).
Estados incoherentes: hover (tarjetas suben 4, 6 o 6 px; `stat-card` se define con 4 en `style.css:246` y 6 en `extra.css:87`), foco (cinco looks distintos, A1), deshabilitado (opacidad 0,65, paginación `#474D57`), error (rojo `#DC3545` solo en el texto), éxito (verde de paleta en avisos, verde de Bootstrap en precios).

### 3.5 Componentes

- Botones: `.btn-gold`/`.btn-warning` (alias), `outline-warning`, `outline-secondary`, `outline-danger`, `btn-danger`, `btn-back` (cuadrado 36 px), `icon-btn`, `btn-float-back`, `sec-pill`, `quick-link`; tamaños `btn-sm`, normal y `-lg` mezclados; letra de 11,5 px a 14 px (`extra.css:134-153`: panel .72rem, tienda .78rem).
- Tarjetas: 12 recetas casi iguales (borde 1 px, radio 12, fondo `--bg-1`): `.card`, `.dark-card`, `.stat-card`, `.pub-card`, `.sec-card`, `.compra-card`, `.compra-resumen`, `.exito-card`, `.comment-card`, `.review-card`, `.cart-item`, `.filter-bar`.
- Tablas: `.table` y `.dark-table` duplican reglas; fechas y alineación de números sin criterio.
- Formularios: `.form-control` + `.dark-input` + `.form-label-dark`; mismo campo con tamaños 14 y 16 px; texto de ayuda en minúsculas dentro de una etiqueta en mayúsculas (mi clave) o en mayúsculas (otras).
- Avisos: `.alert-*` con `!important`, `.alert-dark-danger/info`, chips, `badge-role`, `badge-date`, `pub-card-badge`, clases `estado-*`: cinco formas de decir "estado".
- Paginación: un solo componente (`paginacion.php`), consistente. Bien.

### 3.6 Móvil

- Desplazamiento horizontal: portada y detalle (A2). Resto de pantallas medidas: sin desplazamiento de página.
- Tablas del panel: scroll lateral interno, acciones fuera de vista (M6).
- Zonas táctiles: 15 de 56 controles del catálogo ≥ 44 px; carrito 3 de 15; menú 31 px de alto.
- Menú: no se cierra al navegar (A4).
- Catálogo a 390: 2 columnas de 178 px, filtros de 456 px de alto antes del primer producto (el usuario baja ~450 px antes de ver una pieza).
- Hero: placeholder del buscador cortado ("Buscar: puerta, motor,…"), botón de buscar bien.
- Login a 390: bien centrado, tarjeta clara; enlace "¿Olvidaste tu contraseña?" de 19 px de alto.

### 3.7 Accesibilidad (resumen)

Foco (A1), etiquetas (A3), landmarks y encabezados (A7), nombres de iconos (M5), mensajes de estado (M4), contraste (A6), táctil (M1), reflow (A2), movimiento (M13), `alt=""` en miniaturas de tablas y en el carrito (decorativas pero informativas: la miniatura acompaña al nombre; aceptable si el nombre está al lado; revisar), logo del panel con `alt=""`.

### 3.8 Errores técnicos

Consola sin mensajes en las páginas comprobadas; ninguna imagen rota; todas las peticiones 200; 404 de Apache y error 500 sin tema (M9); peso de imágenes (M10); versiones del servidor visibles en el 404; Bootstrap y Bootstrap Icons por CDN sin SRI (ya anotado como hallazgo 16).

### 3.9 UX de compra

- Cuenta obligatoria para comprar (A5). Cuatro entradas: "Comprar ahora" (formulario de cuenta), "Añadir al carrito", carrito con formulario de cuenta, y confirmación para clientes con sesión.
- Costo total: el carrito muestra subtotal + ITBMS 7 % + total antes de pedir (bien, Baymard recomienda mostrar el costo completo pronto). No hay envío ni fecha de entrega.
- Campos: nombre, teléfono, correo, contraseña, dirección, mensaje: 6 campos, razonable; el nombre en un solo campo coincide con la recomendación de Baymard.
- Cantidad: campo numérico sin botones +/−; botón "Actualizar cantidades" aparte; "quitar" es un icono de papelera con `confirm()`.
- Detalle: "Comprar ahora" y "Añadir al carrito" ambos con peso alto; una sola foto, sin galería ni zoom; sin información de envío/devolución; stock visible ("Disponible 1 uds.").
- Reseñas: formulario público con nombre libre; mensaje de que se necesita sesión como Cliente para calificar.
- Catálogo: resumen de filtros aplicados no se muestra (solo se marca la sección); no se recuerda la posición al volver desde el detalle (no verificado).

### 3.10 UX del panel

- Dashboard: cuatro tarjetas, bitácora de accesos con IP y resultado, accesos rápidos. Útil, pero fechas ISO e IPv6.
- Cabeceras: un patrón repetido (botón volver + título + acción), bien.
- Formularios del panel: etiquetas sin asociar, ayudas dentro de la etiqueta; el selector de partes de ventas lista las piezas en un `<select>` (ya anotado).
- Pedidos: detalle con dos enlaces a la misma factura ("Descargar Factura PDF" y "Ver Factura PDF"); texto de ayuda sobre estados bien.
- Roles: matriz con 32 casillas de 16 px sin nombre.
- Salir: botón rojo siempre visible.

## 4. Estándares

Fuentes leídas (resumidas con mis palabras):

- W3C WCAG 2.2 (Understanding): [1.4.3 contraste](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html) 4,5:1 (3:1 texto grande ≥24 px o ≥18,5 px en negrita); [1.4.11 contraste no textual](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html) 3:1 para controles, estados y foco; [2.4.7 foco visible](https://www.w3.org/WAI/WCAG22/Understanding/focus-visible.html); [2.4.11 foco no oculto](https://www.w3.org/WAI/WCAG22/Understanding/focus-not-obscured-minimum.html) (cabeceras fijas); [2.4.13 aspecto del foco](https://www.w3.org/WAI/WCAG22/Understanding/focus-appearance.html) (AAA, 2 px); [1.4.10 reflow](https://www.w3.org/WAI/WCAG22/Understanding/reflow.html) a 320 px sin scroll en dos dimensiones; [2.5.8 tamaño de objetivo](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html) 24x24 px o separación equivalente; [1.3.1](https://www.w3.org/WAI/WCAG22/Understanding/info-and-relationships.html), [3.3.2](https://www.w3.org/WAI/WCAG22/Understanding/labels-or-instructions.html), [4.1.2](https://www.w3.org/WAI/WCAG22/Understanding/name-role-value.html), [2.4.1](https://www.w3.org/WAI/WCAG22/Understanding/bypass-blocks.html), [2.4.6](https://www.w3.org/WAI/WCAG22/Understanding/headings-and-labels.html), [3.3.1](https://www.w3.org/WAI/WCAG22/Understanding/error-identification.html), [4.1.3](https://www.w3.org/WAI/WCAG22/Understanding/status-messages.html), [2.3.3](https://www.w3.org/WAI/WCAG22/Understanding/animation-from-interactions.html), [3.3.7](https://www.w3.org/WAI/WCAG22/Understanding/redundant-entry.html).
- NN/g: [10 heurísticas](https://www.nngroup.com/articles/ten-usability-heuristics/) (estado del sistema, consistencia, prevención de errores), [placeholders](https://www.nngroup.com/articles/form-design-placeholders/) (no sustituyen a la etiqueta), [confirmaciones](https://www.nngroup.com/articles/confirmation-dialog/) (solo para lo grave, mensajes específicos, botones con el verbo), [tablas de datos](https://www.nngroup.com/articles/data-tables/), [tablas en móvil](https://www.nngroup.com/articles/mobile-tables/), [errores en formularios](https://www.nngroup.com/articles/errors-forms-design-guidelines/).
- Baymard: [estado del checkout](https://baymard.com/blog/current-state-of-checkout-ux), [abandono del carrito](https://baymard.com/blog/reduce-cart-abandonment) (19 % abandona si se obliga a registrarse; costos extra 39 %), [campos del checkout](https://baymard.com/blog/checkout-flow-average-form-fields) (unos 8 campos bastan; nombre en un campo), [listados de producto](https://baymard.com/blog/ecommerce-product-lists-report-and-benchmark) (solo leí titulares), [página de producto](https://baymard.com/blog/current-state-ecommerce-product-page-ux). Gran parte de Baymard está tras suscripción; usé lo público.
- Móvil: [web.dev, objetivos táctiles](https://web.dev/articles/accessible-tap-targets) (48 px y 8 px de separación; `any-pointer: coarse`), [web.dev, movimiento reducido](https://web.dev/articles/prefers-reduced-motion), [MDN :focus-visible](https://developer.mozilla.org/en-US/docs/Web/CSS/:focus-visible). Las páginas de Apple HIG y Material Design no entregaron texto legible (se cargan con JavaScript), así que no las cito para cifras.
- Paneles: [GitLab Pajamas, acciones destructivas](https://design.gitlab.com/patterns/destructive-actions) (la gravedad decide la fricción; solo lo grave pide confirmación y botón de peligro), [GitLab, tablas](https://design.gitlab.com/components/table) (paginar a partir de 20 filas, acciones siempre visibles con cabecera "Acciones", texto a la izquierda y números a la derecha), [Carbon, tabla de datos](https://carbondesignsystem.com/components/data-table/usage/) (alturas de fila 24–64 px, menú de desbordamiento visible, esqueleto en carga). Material data tables y Polaris no se pudieron leer.

### Estándares para una tienda en línea (✔ cumple · ✖ incumple · ◐ parcial)

1. ✖ Texto con contraste ≥4,5:1 (3:1 grande) — WCAG 1.4.3.
2. ✖ Controles y foco con ≥3:1 y foco visible en todo botón — WCAG 1.4.11/2.4.7.
3. ◐ Objetivos ≥24 px, ideal 44–48 px y 8 px de separación — WCAG 2.5.8, web.dev.
4. ✖ Sin scroll horizontal a 320 px — WCAG 1.4.10.
5. ✖ Etiquetas visibles y asociadas; el placeholder no es etiqueta — WCAG 1.3.1/3.3.2, NN/g.
6. ✖ `main`, enlace de salto, un `h1`, jerarquía de encabezados — WCAG 2.4.1/1.3.1.
7. ✖ Iconos con nombre accesible — WCAG 4.1.2.
8. ◐ Mensajes de estado anunciables y errores en texto junto al campo — WCAG 4.1.3/3.3.1, NN/g (hoy: aviso flash arriba, sin `role`).
9. ✖ Compra como invitado, cuenta después — Baymard.
10. ✔ Costo total (con impuestos) visible antes de pedir — Baymard.
11. ✔ Pocos campos, nombre en un campo; ◐ falta autocompletar — Baymard, WCAG 1.3.5.
12. ◐ Listado: orden por precio y novedad sí; resumen de filtros aplicados no — Baymard.
13. ✔ Estados vacíos con salida (carrito, sin resultados) — NN/g.
14. ✖ Contar bien ("12 encontradas" vs 49) — NN/g (estado del sistema).
15. ✖ Movimiento reducible — web.dev, WCAG 2.3.3.
16. ◐ Confirmaciones específicas (el texto de "quitar" nombra la acción) pero con diálogo nativo — NN/g.
17. ✖ Páginas de error útiles y con la marca — NN/g (errores).

### Estándares para un panel de administración

1. ◐ Tablas: identificador legible en la primera columna (sí), columnas por importancia (sí), filtros visibles y activos (solo partes), cabecera fija (no) — NN/g.
2. ✔ Acciones siempre visibles bajo cabecera "Acciones"; ◐ iconos solo con `title` — GitLab.
3. ◐ Paginar desde ~20 filas (partes sí; el resto aún no lo necesita) — GitLab.
4. ◐ Gravedad → fricción: eliminar pide `confirm()`, desactivar usuario no pide nada, "Entregado" pide confirmación con texto específico — NN/g, GitLab.
5. ✔ Jerarquía primario/secundario en formularios (Guardar dorado, Cancelar de contorno); ✖ "Salir" en rojo permanente — GitLab.
6. ◐ Retroalimentación tras cada acción: aviso verde que se cierra a los 4,5 s, sin `role` — NN/g, WCAG 4.1.3.
7. ✖ Tablas en móvil con columnas prioritarias o primera columna fija — NN/g.
8. ✔ Permisos visibles (matriz por módulo); ✖ casillas sin nombre — WCAG 4.1.2.
9. ✖ Errores junto al campo, con color e icono (hoy el campo inválido ni se pinta) — NN/g.
10. ✖ Foco y objetivos del panel (acciones de 29x31) — WCAG 2.4.7/2.5.8.
11. ◐ Formatos de fecha y alineación numérica consistentes (cuatro formatos de fecha) — GitLab.

## 5. Qué se resuelve solo con variables CSS y qué exige cambiar vistas

**Solo CSS (variables y hojas, sin tocar las vistas):**
- Paleta completa (`--bg-*`, `--accent`, `--text-*`, `--success`, `--danger`, `--info`), radios, sombras.
- Reconectar Bootstrap a los tokens: `--bs-warning-rgb`, `--bs-success-rgb`, `--bs-danger-rgb`, `--bs-primary-rgb`, `--bs-secondary-color`, `--bs-focus-ring-*`. Con esto las 144 apariciones de `.text-warning` y las de `.text-success` dejan de usar el dorado y el verde de Bootstrap, y el foco azul desaparece.
- Subir `--text-2`/`--text-3` para pasar contraste; texto oscuro en insignias claras.
- Regla global `:focus-visible` con variable de foco (A1) y foco en `.btn-gold`.
- `prefers-reduced-motion`; altura mínima de controles y `any-pointer: coarse` para 44 px.
- Estados `is-invalid`, `:disabled`, `::selection`; `scroll-padding-top`/`scroll-margin-top` para la barra fija.
- Tipografía: `font-family` del cuerpo (con `@font-face` autoalojado), escala de tamaños por variables; quitar `!important`.
- Reglas de tabla y tarjetas unificadas (un solo componente de tarjeta, una sola tabla).

**Exige cambiar vistas, controladores o JS:**
- `h1`, `<main>`, enlace de salto, orden de encabezados, `aria-label` en `nav`.
- `for`/`id` en etiquetas, `aria-label` en iconos, `aria-hidden` en decorativos, `autocomplete`.
- `g-5` → gutter responsivo (home, detalle, compra, compra_confirmar).
- Configuración de ScrollSpy (atributo en la cabecera), cierre del menú móvil (JS), quitar `nav-active` de `main.js`.
- ✔ ★ ↑ ↓ ≤ « » (vistas y mensajes de controladores/modelo).
- Recuento "piezas encontradas" (controlador/vista).
- `role="status"`/`alert` en los avisos y su auto-cierre (`public_header.php`, `admin_header.php`, `main.js`).
- Modal de confirmación propio (JS + vistas).
- Tablas del panel en móvil como tarjetas (vistas + CSS); filtros en pedidos/ventas/usuarios.
- Páginas 404/500 temáticas (`bootstrap.php`, `.htaccess`/Apache).
- Contenido de plantilla fuera del código (nombre, contacto, horario, créditos, textos de portada) y datos de demo ficticios.
- Optimizar `logo.png`, `hero-bg.jpg`, borrar `hero-bgg.jpg` si no se usa, `srcset`.
- Quitar o rehacer el botón flotante "Volver".

## 6. Decisiones que necesito

1. Tema: ¿solo oscuro (como hoy) o oscuro y claro? Cambia los contrastes y las variables.
2. Bootstrap: ¿seguir con CDN o autoalojar Bootstrap/Icons (necesario para SRI, CSP y modo sin conexión de una plantilla vendible)?
3. Tipografía: ¿fuente web autoalojada (hay que elegir licencia) o pila del sistema? Sin esto, `Segoe UI` solo se verá en Windows.
4. Iconos: ¿seguir con Bootstrap Icons (88 distintos hoy) o un conjunto más corto y un solo estilo?
5. Compra: ¿permitir compra como invitado sin contraseña? Es decisión de negocio (hoy exige cuenta).
6. Panel en móvil: ¿tablas como tarjetas apiladas o scroll con primera columna fija?
7. Confirmaciones: ¿modal propio o `confirm()` nativo? Y si desactivar usuarios también pide confirmación.
8. Nivel objetivo: AA de WCAG 2.2 con objetivos de 44 px (propuesto) o solo 24 px.
9. Portada: ¿secciones por ancla (como hoy) o páginas propias (Quiénes somos, Contacto) con URL compartible?
10. Plantilla: ¿qué texto pasa a configuración (nombre, contacto, horario, pie) y se quita el crédito "Desarrollo de Software VII | … UTP"?
11. Datos de demostración: ¿sustituir los correos y teléfonos reales que hay en pedidos y usuarios por datos ficticios antes de venderla?
12. Personalización: ¿el tema (colores y fuente) será un archivo de variables o se editará desde el panel?
