# Guía de UX para el rediseño — Rastro del Istmo

Fecha: 2026-10-25. Rama `mejoras`. Documento de **solo lectura y propuesta**: no se tocó código, vistas, base de datos ni se hizo commit. Complementa `docs/DESIGN.md` (colores, tipografía, componentes) y `docs/auditoria-diseno.md` (hallazgos de accesibilidad). Aquí se decide la **composición**: qué secciones lleva cada pantalla, en qué orden y con qué medidas.

> **Estado (2026-10-25): Tony ya respondió las 8 preguntas.** Sus respuestas están en la sección 10 y **prevalecen** sobre cualquier recomendación anterior de este documento. El resto del texto se conserva como justificación.

Contenido: 0 Resumen · 1 Cómo se midió · 2 Investigación · 3 Sistema de medidas · 4 Diagnóstico por pantalla · 5 Propuestas A/B · 6 Las observaciones 1, 3, 4, 5, 6 y 7 resueltas · 7 Backlog por sesiones · 8 Preguntas · 9 Fuentes · 10 Decisiones de Tony.

---

## 0. Resumen de recomendaciones

| # | Tema | Recomendación | Por qué (dato medido) |
|---|---|---|---|
| 1 | Marca del encabezado | Un solo bloque: logo de 40 px de alto, 12 px de separación, nombre 16/700 y lema 12/500 pegados (2 px). Logo SVG (`currentColor`) o PNG sin filtro CSS; encabezado de 64 px | Hoy el encabezado mide 81 px; el lema (9,28 px) vive en una línea de 24 px que lo aleja 13 px del nombre; el logo (48×32) es más pequeño que el texto (46 px) |
| 2 | Portada | Hero compacto con buscador **y selector de vehículo** → franja de confianza → categorías → "Recién llegadas" → "Compra por marca" → banda de llamada a la acción. Se elimina la franja de cifras ("49+ piezas, 6 marcas, 4 secciones") | La portada mide 2.364 px (1280) y 3.781 px (390); la primera pieza aparece a ~1.000 px; la franja de cifras no ayuda a decidir una compra |
| 3 | Fondo de la portada | Hero tipográfico (sin foto) como valor por defecto de la plantilla; en Rastro del Istmo, fotos propias con capa que depende del modo. La foto actual no tiene origen ni licencia conocidos | `hero-bg.jpg` ya viene oscurecida (brillo medio 0,15, máx. 0,49) y se desconoce su fuente |
| 4 | Carrito | **Dos pasos** (Carrito → Tus datos) con resumen compacto fijo; cantidad con botones − / +; quitar con "Deshacer" | La columna derecha mezcla resumen y 7 campos; la izquierda queda vacía |
| 5 | Fotos | **1:1**, mostrar **completa** (`contain`) sobre una baldosa clara fija, miniatura de 640 px y detalle de 1.200 px | Hoy la caja cambia de proporción con la pantalla: 0,87 (390), 1,33 (1280) y 1,60 (1024), por una regla `height:200px` que pelea con `aspect-ratio:4/3` |
| 6 | Detalle | Galería reservada: figura 1:1 + tira de miniaturas de 64 px (oculta con una sola foto) + botón "Ampliar" de 44 px | Patrón para la futura función; no se diseña en detalle |
| 7 | Navegación | Todo enlace interno en la misma pestaña; "Salir" pasa a **POST**; las acciones que redirigen no mueven al usuario de página; los errores conservan lo escrito; token CSRF que no se rompe con varias pestañas | Dos `target="_blank"` internos, tres "Salir" por GET, `agregarCarrito` siempre redirige al detalle, y el token CSRF de un solo uso falla en cuanto hay dos pestañas abiertas |
| 8 | Catálogo | Contenedor de 1280, 4 columnas (3 desde 768 px), 24 piezas por página, filtros plegados en móvil, **lista horizontal** en teléfono, y el conteo real ("49 piezas") | En 390 px los filtros ocupan 496 px y la primera pieza está en y = 688 de 844; el conteo dice "12 encontradas" aunque hay 49 |
| 9 | Dashboard | Primero lo accionable (pedidos por atender, stock bajo con lista), luego la bitácora de accesos reducida | La bitácora es el bloque más grande (810×531) y la tarjeta de stock bajo solo da un número |
| 10 | Listas del panel | Tabla de 5 columnas útiles + tarjetas en móvil (ya decidido) | En 390 px se ven 4 de 9 columnas (705 px de ancho contra 335 visibles) y cada fila mide 149 px |

Las composiciones A (conservadora) y B (ambiciosa) de cada pantalla están en la sección 5; mi recomendación está marcada en cada una. Las preguntas que necesito responder están en la sección 8.

---

## 1. Cómo se midió y qué no se cubrió

- **Código leído**: 16 vistas públicas y de acceso, 13 del panel, los dos encabezados y pies, `paginacion.php`, `base.css`, `extra.css`, `style.css` (líneas 120–328), `main.js`, `ImageHelper.php`, `PublicController`, `PedidoController`, `ParteController`, `CsrfToken`, `logout.php`. **No** se leyó `app/Config/` (ni `marca.php`: las claves de la marca que se citan salen de cómo las llaman las vistas).
- **Medición en el navegador integrado** (solo GET, sesión de administrador ya abierta, sin enviar ningún formulario): geometría real con `getBoundingClientRect` y estilos calculados en 390, 1024 y 1280 px (las pantallas se cargaron en un iframe del ancho pedido). Se midieron catálogo, portada, detalle, carrito vacío, Quiénes somos, dashboard, partes y pedidos.
- **Imágenes**: dimensiones y pesos reales de las 50 miniaturas y las 50 originales de `uploads/`, y brillo de las tres fotos de `assets/img/` (muestreo de ~3.600 puntos con GD).
- **Capturas nuevas (3 de 3)**: panel de partes en 390 px, catálogo en 390 px y detalle (pieza 21) en 1280 px. Más las 4 que adjuntaste (carrito, pie, portada, dashboard y encabezado).
- **No cubierto**: carrito con productos (estaba vacío; la medida del carrito sale de tu captura y del código), pantallas que exigen POST (`compra_confirmar`, `compra_exitosa`), pedidos en estados distintos de "entregado", hover real, tableta de 768 px en el panel, y la prueba con un lector de pantalla. En el panel, las alturas de fila de escritorio (69 px) se midieron con emulación táctil activa, así que los botones de 44 px pueden inflarlas respecto a un ratón.
- **Fuentes**: leí los **extractos** que devuelve la búsqueda de cada fuente, no abrí cada artículo completo. Gran parte de Baymard y de NN/g es de pago; lo que dicen sobre eso está marcado como "no leído".

---

## 2. Investigación acotada (12 fuentes)

Las 12 fuentes nuevas están en la sección 9. Además se reutilizan, sin contarlas, las del informe de auditoría (WCAG, NN/g sobre confirmaciones y tablas, Baymard sobre invitados y costo total, GitLab sobre acciones destructivas).

### 2.1 Principio → cómo se aplica a ESTE sitio

| Principio (fuente) | Qué dice, con mis palabras | Cómo se aplica aquí |
|---|---|---|
| **Ley de Jakob** (Laws of UX, leída) | La gente espera que tu tienda funcione como las demás que ya usa | Logo arriba a la izquierda, buscador y carrito arriba a la derecha, filtros a la izquierda o plegados, "Añadir al carrito" como botón principal de la ficha. No se inventan patrones |
| **Ley de Hick** (Laws of UX, leída) | Más opciones, más tiempo para decidir | Menú de 4 opciones; filtros del catálogo plegados en móvil; un solo botón principal por pantalla (DESIGN.md ya lo exige); portada con 6 bloques, no 10 |
| **Ley de Fitts** (Laws of UX, leída) | Objetivos pequeños y lejanos tardan más | 44 px táctiles, 8 px entre objetivos; la tarjeta entera es un enlace; acciones principales del detalle en el borde inferior en móvil |
| **Ley de Miller / fragmentar** (Laws of UX, leída) | La memoria de trabajo es corta; agrupa | Filtros en grupos (Sección / Marca / Precio); formulario de compra en dos pasos de 3–4 campos; tablas con 5 columnas visibles |
| **Efecto Von Restorff** (Laws of UX, leída) | Lo distinto se recuerda; no abuses | El amarillo solo en la acción principal; la tarjeta "Pedidos por atender" del panel es la única que cambia de énfasis cuando hay pendientes |
| **Proximidad y región común** (Laws of UX, por conocimiento general; no reabrí la página) | Lo cercano se lee como grupo | Espacio dentro del grupo < espacio entre grupos (8 / 16 / 24 / 48). Resuelve la marca del encabezado (nombre y lema juntos) y el pie |
| **Umbral de Doherty** (Laws of UX, por conocimiento general) | Responde en menos de ~400 ms o muestra progreso | "Añadir al carrito" sin recargar la página: contador y aviso inmediatos |
| **Efecto de posición serial** (Laws of UX, por conocimiento general) | Se recuerda lo primero y lo último | En menú y pie, lo importante va al inicio y al final: Catálogo primero, Contacto último |
| **Homepage de tienda** (NN/g, extracto) | La portada debe decir qué vendes y en qué te diferencias, mostrar las ofertas principales y no esconder las categorías tras un solo "Tienda" | Hero con propuesta clara, categorías visibles justo debajo, menú con las secciones reales; fotos de piezas reales, no de banco de imágenes |
| **Búsqueda visible y ancha** (NN/g, extracto) | La búsqueda debe estar siempre a la vista y no ser diminuta | En móvil el buscador pasa a su propia fila fija (hoy está dentro del menú plegado); en escritorio, campo de ≥ 27 caracteres (~280 px) |
| **Listados de producto** (Baymard, extracto) | Miniaturas y atributos homogéneos; en productos con muchas especificaciones, la lista suele servir mejor que la rejilla; imágenes muy grandes bajan los productos visibles | Una sola proporción de foto (1:1), compatibilidad (marca, modelo, año) siempre en la misma línea, lista horizontal en móvil |
| **Galería y zoom** (Baymard, extracto) | Usar miniaturas (no puntos) para fotos adicionales; en móvil soportar pellizco y doble toque; avisar que se puede ampliar; cargar una foto de más resolución al ampliar | Patrón reservado en el detalle (sección 6.5) |
| **Nuevas pestañas** (NN/g, extracto) | Abrir pestañas sin que el usuario lo pida desorienta, rompe "Atrás" y perjudica a quien usa lector de pantalla o móvil; solo vale para consultar un documento al lado de un formulario | Observación 7: solo PDF y enlaces externos abren pestaña |
| **Mensajes de error** (NN/g, extracto) | Conserva lo que escribió el usuario, junto al campo, con más de una señal | Errores de compra y registro conservan los datos; el error junto al campo con icono y texto |
| **Paneles** (Carbon y GitLab Pajamas, extractos) | Datos por importancia: lo más importante más grande y arriba; contexto y tendencia, no solo una cifra; lo frecuente a la vista | Dashboard: lo accionable primero, KPI con periodo, bitácora abajo |
| **Licencias de imágenes** (Unsplash, Pexels) | Uso comercial sin permiso ni crédito, pero no revender sin modificar, ni usar marcas o personas reconocibles, ni armar un servicio similar | Sección 6.3: no incluir fotos de banco dentro de una plantilla que se vende |
| **Cierre de sesión** (Symfony UX, extracto) | El cierre de sesión debe ser POST con protección CSRF para que un enlace o una imagen ajena no pueda dispararlo | Observación 7: "Salir" por POST |

---

## 3. Sistema de medidas propuesto

Todo se apoya en `docs/DESIGN.md`. Donde DESIGN.md no dice nada o dice otra cosa, se marca **[enmienda]** y se pide tu OK en la pregunta 8.

### 3.1 Contenedores y rejilla

| Elemento | Hoy (medido) | Propuesta | Justificación |
|---|---|---|---|
| Tienda, ancho máximo | `container-xl` de Bootstrap = 1140 px (contenido de 1116 a 1280 px) | **1280 px** de borde a borde, relleno lateral 16 (móvil) / 24 (≥ 768) / 32 (≥ 1200); contenido útil de 1216 px | DESIGN.md pide "unos 1280". 1140 deja 70 px vacíos por lado a 1280 |
| Panel, ancho máximo | `container-fluid` sin tope (1226 px útiles a 1280; se estira en monitores grandes) | **1440 px** máx., mismo relleno | DESIGN.md pide 1440 |
| Rejilla | 12 columnas de Bootstrap; gutter horizontal `g-4` = 24 px | 12 columnas; gutter 16 px en móvil, 24 px desde 768 px | Gutter pequeño en móvil evita el desborde de 12 px ya corregido (ronda 4a) |
| Rejilla de 8 px | Mezcla de múltiplos de 4 y de `rem` sueltos (0,6; 0,85; 1,4 rem) | Layout en múltiplos de **8** (8, 16, 24, 32, 48, 64, 80). Se permite 4 y 12 solo dentro de un componente | DESIGN.md usa 4 como unidad: 8 es el ritmo de layout; 4 y 12 son los medios pasos |
| Ritmo vertical entre secciones | `py-5` = 48 px siempre | **48** (móvil) / **64** (tableta) / **80** (escritorio, `spacing.section`). Título de sección → contenido: 24 (32 en escritorio). Entre tarjetas: 12 (móvil) / 24 | Proximidad: más espacio entre grupos que dentro de ellos |
| Longitud de línea | Párrafo de Quiénes somos: 631 px a 16 px ≈ 70–75 caracteres | Texto corrido ≤ **65ch** (~600 px); texto en tarjetas ≤ 45ch; formularios de una columna ≤ 560 px | 45–75 caracteres es el rango cómodo de lectura |

### 3.2 Columnas por ancho

| Ancho | Catálogo (tarjetas) | Panel (KPI) | Notas |
|---|---|---|---|
| < 576 px | **1 columna, tarjeta horizontal** (foto 96 px a la izquierda) — B recomendada; o 2 columnas compactas — A | 2×2 | Con 2 columnas, cada tarjeta mide 173 px de ancho y 380 px de alto |
| 576–767 | 2 columnas | 2×2 | |
| 768–1023 | **3 columnas** [enmienda: DESIGN.md dice 2] | 2×2 o 4 | A 768 px, 2 columnas dan fotos de 348 px, demasiado grandes |
| 1024–1199 | 3 columnas | 4 | Hoy: 3 columnas de 321 px |
| ≥ 1200 | **4 columnas de ~286 px** (contenido 1216, gutter 24) | 4 | Hoy: 4 columnas de 267 px |

### 3.3 Tarjeta de producto

Medido hoy: 267×349 px (escritorio), 176×380 px (móvil de 2 columnas). Foto de 200 px de alto fijo, dos botones de 25 px en escritorio, tres líneas de metadatos con icono y etiqueta de stock sobre la foto.

| Parte | Propuesta (escritorio 286 px de ancho) | Móvil horizontal (358 px de ancho) |
|---|---|---|
| Foto | **1:1**, 286×286, `object-fit: contain`, baldosa clara fija, 8 px de margen interno, radio 12 arriba | 96×96, mismo tratamiento |
| Título | 16/600, `lh` 1,4, máx. 2 líneas (45 px) | igual |
| Compatibilidad | 14/400 en gris secundario, una línea: "Honda Civic · 2020". Sin icono | igual |
| Estado de stock | Solo si importa: "Agotado" o "Quedan 3" (≤ 5). Nada si hay más. Se quita "55 en stock" (dato interno) | igual |
| Precio | Plex Sans, **20/600** [enmienda: DESIGN.md dice 16] | igual |
| Acción | Botón de carrito de 44×44 a la derecha del precio; la tarjeta entera es el enlace al detalle (título como "enlace extendido"). Se quita el botón del ojo | igual |
| Relleno / separación | 16 px de relleno, 8 px entre líneas, 12 px entre título y precio | 12 px |
| Alto resultante | ≈ 443 px (286 + 16 + 45 + 4 + 20 + 12 + 44 + 16) | ≈ 120 px por fila (96 + 24) |
| Hover | Borde y sombra nula; la foto no se amplía (hoy `scale(1.07)`); sube a lo sumo 2 px | — |

Por qué pasa de 349 a 443 px: la foto cuadrada es la proporción más cercana a tus fotos (18 de las 50 miniaturas ya son 315×315 y la mediana es 1,07). Se compensa quitando ruido (dos líneas de iconos y la etiqueta de stock). Con 24 piezas por página (4×6) y filas de 443 + 24 px, el catálogo mide ≈ 2.800 px, similar a hoy con 12 piezas por página en móvil (3.826 px).

### 3.4 Fotos de producto

Ver la sección 6.5 para el detalle (proporción, mostrar completa, tamaños y subidas).

### 3.5 Conjunto marca del encabezado

Ver sección 6.1.

### 3.6 Densidad de tablas del panel (escritorio)

| Elemento | Hoy | Propuesta |
|---|---|---|
| Alto de fila | 61–69 px | **56 px** (44 de objetivo + 12) |
| Cabecera | 44 px, 12 px en mayúsculas con espaciado | 44 px, 13 px, sin mayúsculas forzadas, fija al desplazar |
| Relleno de celda | Bootstrap (8/12) | 12 px vertical, 16 px horizontal |
| Columnas visibles | 9 (partes), 8 (pedidos) | **≤ 6 en ≥ 1024 px, ≤ 4 en 768, tarjeta en < 768** |
| Números | Alineación irregular | A la derecha, Plex Sans tabular |
| Filas por página | 20 | 20 (sin cambio; GitLab recomienda paginar desde ~20) |
| Acciones | Iconos de 44 px solo con `title` | Editar visible como botón; las demás en el mismo lugar siempre, con nombre accesible |

### 3.7 Enmiendas propuestas a DESIGN.md

| # | Qué | Hoy | Propuesta |
|---|---|---|---|
| E1 | Columnas en tableta | 2 | 3 desde 768 px |
| E2 | Precio en tarjeta | `number-md` 16 px, amarillo | 20/600. Además: el sitio usa verde (`--success`) para precios en tarjeta, detalle, carrito y pedidos; DESIGN.md dice que los precios destacados son amarillos y que el verde es solo estado. **Decidir cuál** |
| E3 | Valor de las tarjetas KPI del panel | 30,4 px | 32 px (`display-sm`) |
| E4 | Baldosa de la foto | no existe | Fondo `canvas-light` (#FFFFFF) en los dos modos para toda foto de producto |
| E5 | Relleno lateral del contenedor | no existe | 16 / 24 / 32 |
| E6 | Marca del encabezado | solo "marca a la izquierda" | Especificación de la sección 6.1 |
| E7 | Barra superior | 64 px (DESIGN) | Se mantiene 64; hoy el encabezado público mide 81 px (el panel, 58) |

---

## 4. Diagnóstico pantalla por pantalla

Leyenda: **Incumple** = qué rompe de los principios; **Conservar** = lo que ya funciona. Los números son medidas de esta sesión.

### 4.1 Tienda

**Portada** (`home.php`, 2.364 px a 1280 y 3.781 px a 390)
- Incumple:
  - Estructura de 2007: hero (452) → franja de cifras (87) → secciones (396) → destacadas (951) → pie (349). La pieza que se puede comprar aparece a ~1.000 px.
  - "Destacadas" se ordena por mayor stock (`findFeatured`), una regla interna que no le dice nada al comprador. Es una lista de lo que más sobra, no de lo nuevo ni de lo mejor.
  - La franja de cifras ("49+", "6", "4") ocupa 87 px sin ayudar a decidir.
  - Cuadrito con icono (`sec-icon`) en cada sección, icono en cada título y palabra clave en amarillo en cada título: DESIGN.md lo prohíbe.
  - El hero repite lo mismo dos veces: un buscador y dos botones ("Ver todo el catálogo" dos veces en la misma pantalla, líneas 18 y 75–77, más un tercero al final).
  - Título del hero de 51 px en escritorio (DESIGN: 64) y 32 px en móvil (DESIGN: 36).
- Conservar: buscador grande en el hero, secciones como puerta de entrada, el pie ya sale de la marca.

**Catálogo y tarjeta** (`catalogo.php`)
- Incumple:
  - **Conteo falso**: `count($partes)` cuenta la página, no el total. Dice "12 piezas encontradas" cuando hay 49 (línea 14).
  - **Filtros**: en 390 px miden 496 px y la primera pieza queda en y = 688 de 844. En escritorio 208 px (y = 171 a 379).
  - Las secciones aparecen como píldoras largas ("Sección A — Carrocería, puertas y paneles (12)") y además como un menú Marca/Precio/Orden: 6 controles antes de la primera pieza.
  - Los selectores envían el formulario solo al cambiar (`onchange`): cambia de contexto sin avisar (WCAG 3.2.2, criterio conocido; no abrí su página).
  - Foto: la caja mide 265×200 (1280), 319×200 (1024) y 174×200 (390): proporción 1,33 / 1,60 / 0,87. Dos reglas se pisan: `style.css:281` (`height:200px`) y `extra.css:11` (`aspect-ratio:4/3`).
  - Etiqueta de stock sobre la foto ("55 en stock"), dos botones de 25 px en escritorio, tres líneas de metadatos con icono.
- Conservar: filtros por GET (URL compartible y botón Atrás correctos), paginación accesible con "Mostrando X–Y de N", estado vacío con salida, fotos con `alt`.

**Detalle** (`detalle.php`)
- Incumple:
  - Foto en caja 4:3 (437×328) con `contain`: las fotos cuadradas dejan barras laterales. Además carga la **original** (hasta 2.560 px y 565 KB) para una caja de 437 px.
  - Tres botones con peso parecido: "Comprar ahora" (amarillo), "Añadir al carrito" (contorno amarillo) y "Catálogo" (contorno gris). DESIGN.md: un solo botón principal por vista.
  - La columna de información termina a 360 px de alto y debajo hay un vacío hasta las reseñas.
  - En móvil: foto 359×269, título en y = 490, precio en 637, botón principal en 788 (queda a 56 px del borde inferior de 844) y los secundarios en 848 (fuera de la pantalla).
  - "Verificado con firma digital HMAC-SHA256": jerga técnica para un comprador.
  - Precio de 38 px en verde.
  - Sin compatibilidad destacada, sin garantía o devolución, sin piezas relacionadas, sin cantidad.
- Conservar: migas de pan, stock visible, descripción clara, reseñas públicas, `alt`, estrellas accesibles.

**Carrito y compra** (`carrito.php`, `compra.php`, `compra_confirmar.php`, `compra_exitosa.php`)
- Incumple:
  - Resumen **y** formulario en la misma columna derecha. Para un invitado son seis campos más la contraseña (≈ 850 px) con un resumen pegajoso (`top: 80px`). Si el cuadro es más alto que la ventana, el fondo del formulario queda fuera de alcance mientras la columna izquierda ya se acabó (tu captura: el producto desaparece al bajar).
  - Cantidad como cuadro numérico de 70 px más un botón aparte "Actualizar cantidades"; borrar con `confirm()`.
  - Hay **cuatro** entradas de compra distintas (Comprar ahora invitado, Comprar ahora cliente, carrito invitado, carrito cliente) con textos y campos diferentes.
  - Fotos del carrito de 70×70 con recorte (`cover`), "Resumen del pedido" con nombres cortados a 28 caracteres.
  - Si hay un error, la redirección vuelve a la pantalla **sin lo escrito** (`$_SESSION["carrito_error"]`, `compra_error`): hay que reescribir los seis campos.
  - Cuenta obligatoria (decisión ya tomada: compra como invitado, función futura).
- Conservar: desglose de ITBMS antes de pedir, totales siempre visibles, estado vacío con salida, la compra de un solo clic para clientes con sesión, la pantalla de éxito con factura.

**Acceso y registro** (`login.php`, `registro.php`)
- Incumple: icono de herramientas genérico en lugar de la marca; el correo se pierde si falla el inicio de sesión; el enlace "¿Olvidaste…?" es texto suelto de ~19 px de alto (la auditoría lo midió; no lo volví a medir).
- Conservar: tarjeta centrada de 430 px, etiquetas visibles, mostrar/ocultar contraseña, mensajes genéricos de error.

**Páginas informativas** (`nosotros.php`, `contacto.php`)
- Incumple: Quiénes somos usa una foto y párrafos de 631 px (~73 caracteres) en gris secundario; la banda final repite el título del hero; Contacto es una tarjeta de datos sin mapa ni acción principal (un botón "Llamar" / "WhatsApp" en móvil sería la acción natural).
- Conservar: dos páginas con URL propia, `info-card`, `trust-badge`, `cta-band` ya definidos en DESIGN.md.

**Pie** (`public_footer.php`)
- Incumple: cuadritos de icono (`contact-icon`) que DESIGN.md prohíbe; no hay enlaces de navegación; el horario con iconos de reloj y círculo rojo mezcla iconos decorativos con estado.
- Conservar: sale de la marca, sigue el tema, 3 columnas que se apilan en móvil.

**Encabezado** (`public_header.php`)
- Incumple: ver sección 6.1. Además, el buscador va dentro del menú plegado en móvil y el encabezado mide 81 px (DESIGN: 64).
- Conservar: opción activa decidida por PHP, enlace de salto, nombres accesibles.

### 4.2 Panel

**Dashboard** (`dashboard.php`, 934 px a 1280)
- Incumple:
  - Orden por frecuencia equivocado: el bloque más grande (810×531) es la bitácora de accesos (10 filas con IP y fecha ISO), una tarea de seguridad poco frecuente. Lo que se hace a diario (pedidos por atender, stock que se acaba) es una cifra.
  - Las tarjetas KPI miden 295×157 con un icono de 32 px en cuatro colores (dorado, rojo, azul, verde): decoración, y el rojo/verde no son estados aquí.
  - "Total vendido" sin periodo (¿histórico, mes, hoy?). "Stock bajo (≤5)" da el número, no la lista.
  - "Accesos rápidos" es una lista lateral de cinco enlaces con chevron; las dos acciones frecuentes (Agregar parte, Registrar venta) deberían ser botones en la cabecera.
- Conservar: tarjetas clicables que llevan a la lista filtrada; la bitácora como dato (pero más abajo y más corta).

**Listas** (`partes/index.php`, `pedidos/index.php`, `usuarios/index.php`, resto)
- Incumple:
  - Partes: 9 columnas; en 390 px se ven 4 (tabla de 705 px en 335 visibles) y cada fila mide 149 px; el precio, el stock y las acciones quedan fuera de la vista (lo viste en la captura nueva).
  - Pedidos: 8 columnas con correo y teléfono completos en la lista (755 px de ancho), sin filtro por estado ni por fecha.
  - Filtros de partes en 390 px: 208 px de alto para tres controles y un botón de lupa que en móvil mide el ancho de la pantalla.
  - "Ver público" abre pestaña nueva (observación 7).
  - Desactivar un usuario o una parte: `confirm()` nativo o nada.
- Conservar: orden y filtros en partes, paginación de 20 filas, nombres accesibles, la matriz de permisos.

**Formularios** (`partes/form.php`, `ventas/form.php`, etc.)
- Incumple: ancho completo en todo el panel (campos de 1.200 px); la foto actual aparece a 90×68 recortada en `cover` (otra proporción más); **al crear una parte con error se pierde lo escrito** (`ParteController::crear` no devuelve `$parte` a la vista; `editar` sí lo conserva); el selector de piezas en ventas es un `<select>` gigante.
- Conservar: etiquetas asociadas, Guardar amarillo + Cancelar de contorno, "Volver" como botón.

**Detalle de pedido** (`pedidos/detalle.php`)
- Incumple: dos enlaces a la misma factura ("Descargar" y "Ver"), tres tarjetas en dos columnas con el cambio de estado en un lateral; la acción más importante (avanzar el estado) compite con los datos del cliente.
- Conservar: texto explicativo de estados, bloqueo de pedidos entregados, desglose de impuesto.

**Encabezado del panel** (`admin_header.php`)
- Incumple: "Tienda" con `target="_blank"` (línea 48); "Salir" como botón rojo siempre visible y por GET (línea 49); el nombre del usuario suelto en amarillo; logo de 22 px.
- Conservar: menú con "Más", opción activa por PHP, pliegue por debajo de 1200 px.

---

## 5. Propuestas A / B por pantalla

Convención: A = conservadora (mismas piezas, mejor ordenadas), B = más ambiciosa. Los bocetos son esquemas, no maquetas; las medidas son las de la sección 3.

### 5.1 Portada

**Secciones que debe tener una tienda profesional, y en qué orden** (resumen de lo que dicen NN/g y los patrones habituales de tiendas; cada una con su razón):

| Orden | Bloque | Para qué | Alto escritorio / móvil | Contenido |
|---|---|---|---|---|
| 1 | Encabezado con búsqueda | Encontrar rápido (Fitts, Jakob) | 64 / 56 + 48 de buscador | Logo, menú, búsqueda, carrito, cuenta |
| 2 | Hero con buscador y **"Busca por tu vehículo"** | Decir qué vendes y llevar a la pieza correcta. En autopartes la compatibilidad es la pregunta nº 1 | 400 / 320 | Título + una línea, buscador, tres selectores (Marca, Modelo, Año) que filtran el catálogo |
| 3 | Franja de confianza | Reducir el miedo a lo usado (garantía, devolución, retiro) | 88 / 4 filas de 56 o 2×2 | 4 elementos reutilizando `nosotros.destacados` de la marca. **Solo afirmaciones ciertas** (pregunta 6) |
| 4 | Categorías (secciones) | No esconder lo que se vende | 240 / 2×2 de 120 | 4 baldosas con nombre y número de piezas; sin cuadrito de icono |
| 5 | Recién llegadas | Mostrar piezas reales y novedad | ~480 (1 fila de 4) / carrusel horizontal | 4 piezas con la tarjeta de 3.3; botón "Ver todo" |
| 6 | Compra por marca | Segundo camino al mismo catálogo | 96 / 2 filas de chips | 6 enlaces de marca (Hyundai, Chevrolet…) con el número de piezas |
| 7 | Banda de llamada a la acción | Cerrar con una acción (`cta-band` de DESIGN) | 160 / 200 | "¿No encuentras tu pieza?" + botón de WhatsApp o llamar |
| 8 | Pie | Datos de contacto y navegación | 280 / 520 | Marca, enlaces, contacto, horario |

Se **quita** la franja de cifras y se **sustituye** "Destacadas por mayor stock" por "Recién llegadas" (el orden "recientes" del catálogo ya existe; hay que reutilizarlo en `findFeatured`). "Ofertas" queda fuera de esta fase porque la base no guarda un precio anterior (haría falta un cambio de esquema).

**Composición A (conservadora)**: el mismo flujo de hoy, ordenado.

```
┌──────────────────────────────────────────────────────────────┐
│ [logo] Rastro del Istmo   Inicio Catálogo Quiénes Contacto   │ 64
│                        [ Buscar pieza…        ][☼][🛒][Cuenta]│
├──────────────────────────────────────────────────────────────┤
│  (foto con capa según modo)                                  │
│        RASTRO DE AUTOS — PANAMÁ (opcional)                   │ 400
│        Tu pieza al mejor precio                              │
│        [ Buscar: puerta, motor, vidrio…        ][Buscar]     │
│        [Ver catálogo]   (sin segundo botón)                  │
├──────────────────────────────────────────────────────────────┤
│ Compra por categoría                                         │
│ [Carrocería 12] [Motores 12] [Vidrios 12] [Eléctrico 13]     │ 240
├──────────────────────────────────────────────────────────────┤
│ Recién llegadas                         [Ver todo →]         │
│ [tarjeta][tarjeta][tarjeta][tarjeta]                         │ ~560
├──────────────────────────────────────────────────────────────┤
│ [ banda: ¿No encuentras tu pieza? WhatsApp / Llamar ]        │ 160
├──────────────────────────────────────────────────────────────┤
│ pie de 4 columnas                                            │ 280
└──────────────────────────────────────────────────────────────┘
```
Altura total ≈ 1.800 px (hoy 2.364). Respaldo: Hick (menos bloques), proximidad, NN/g (categorías visibles).

**Composición B (ambiciosa)**: hero en dos columnas, buscador por vehículo, confianza y estantes.

```
┌──────────────────────────────────────────────────────────────┐
│ encabezado 64                                                │
├──────────────────────────────┬───────────────────────────────┤
│ Tu pieza al mejor precio     │  [foto de una pieza real,     │
│ Repuestos usados verificados │   1 sola, recortada con       │ 400
│ ┌ Busca por tu vehículo ────┐│   esquinas de 12 px; sin     │
│ │[Marca▾][Modelo▾][Año▾][Ir]││   texto encima]              │
│ └───────────────────────────┘│                               │
│ [ o busca por nombre… ]      │                               │
├──────────────────────────────┴───────────────────────────────┤
│ ✓ Piezas revisadas │ ✓ Garantía │ ✓ Retiro en tienda │ ✓ WhatsApp │ 88
├──────────────────────────────────────────────────────────────┤
│ Categorías con foto (4 baldosas 1:1 con nombre y conteo)     │ 300
├──────────────────────────────────────────────────────────────┤
│ Recién llegadas (4)  ·  [Ver todo]                           │
│ Más vendidas / por categoría (estantes de 4)                 │ ~1000
├──────────────────────────────────────────────────────────────┤
│ Compra por marca: [Hyundai 8][Chevrolet 9][Honda 10]…        │ 96
├──────────────────────────────────────────────────────────────┤
│ banda de llamada a la acción                                 │ 160
├──────────────────────────────────────────────────────────────┤
│ pie                                                          │
└──────────────────────────────────────────────────────────────┘
```
Ventaja de B: el texto no va sobre la foto, así que **desaparece el problema de contraste del hero** y la foto puede ser clara; el selector de vehículo es el patrón de las tiendas de autopartes (Baymard tiene una guía de auditoría de autopartes que no pude leer por ser de pago). Costo: hace falta que `autos` tenga marca, modelo y año filtrables (ya están en la base) y un par de consultas nuevas.

**Recomiendo B** si aceptas el selector de vehículo (es lo que más diferencia a una tienda de repuestos de una de zapatos, y para otros rubros se reemplaza por otro filtro: talla, tipo de perfume…). Si prefieres no tocar consultas ahora, A + selector en una sesión posterior.

### 5.2 Catálogo y tarjeta de producto

**A (conservadora)**: rejilla de 4/3/2 columnas, filtros en una fila plegable.

```
Escritorio ≥ 1200
┌──────────────────────────────────────────────────────────────┐
│ Inicio › Catálogo                                            │
│ Catálogo · 49 piezas                       Ordenar [Recientes▾]│
├────────────┬─────────────────────────────────────────────────┤
│ FILTROS    │ [Sección A ×] [Honda ×]            (chips activos)│
│ Sección ▾  │ ┌────────┐┌────────┐┌────────┐┌────────┐         │
│ ○ Todas    │ │ foto   ││ foto   ││ foto   ││ foto   │  4 × 6  │
│ ○ A (12)   │ │ 1:1    ││        ││        ││        │         │
│ Marca ▾    │ │ título ││        ││        ││        │         │
│ Precio ▾   │ │ Honda… ││        ││        ││        │         │
│ [Aplicar]  │ │ $15 [🛒]││        ││        ││        │         │
│            │ └────────┘└────────┘└────────┘└────────┘         │
│            │ Mostrando 1–24 de 49       « 1 2 3 »             │
└────────────┴─────────────────────────────────────────────────┘
Móvil < 576
┌─────────────────────────┐
│ Catálogo · 49 piezas    │
│ [Filtros (2)]  [Ordenar▾]│  ← barra fija de 48 px
│ [Sección A ×][Honda ×]   │
│┌──┐ Faro de posición     │
││1:1│ Hyundai Elantra 2020 │  96 px de foto
│└──┘ $15.00        [🛒]   │
│  … (12 filas visibles    │
│     en ~1.500 px)        │
└─────────────────────────┘
```
Cambios: barra lateral izquierda de 240 px en escritorio (el patrón más reconocido en tiendas, Jakob), "Aplicar" explícito (sin envío al cambiar), chips de filtros activos que se pueden quitar, conteo real, 24 por página. En móvil los filtros pasan a una hoja inferior (`<dialog>`) abierta desde "Filtros (2)".

**B (ambiciosa)**: A + vista rejilla/lista conmutable, filtro por vehículo persistente ("Mi auto: Honda Civic 2020" fijado arriba, filtra todo el sitio), orden con "Más vendidas", y estantes por sección cuando no hay filtros.

**Recomiendo A.** B añade el "Mi auto" que depende del selector de vehículo de la portada (sesión posterior). En móvil, la fila horizontal de 96 px respalda a Baymard (listas para productos con muchas especificaciones) y a Fitts (objetivos de 44 px).

### 5.3 Detalle de pieza

**A (conservadora)**

```
Escritorio                                    Móvil
┌─────────────────┬───────────────────┐      ┌───────────────────┐
│ Inicio›Catálogo›│                   │      │ ‹ Catálogo        │
│ ┌─────────────┐ │ Sección A         │      │ ┌───────────────┐ │
│ │             │ │ Faro de posición  │      │ │  foto 1:1     │ │
│ │ foto 1:1    │ │ Hyundai Elantra   │      │ │               │ │ 358
│ │ 560×560     │ │ 2020  (compatible)│      │ └───────────────┘ │
│ │             │ │ ★★★★☆ 4,5 (8)     │      │ [▢][▢][▢]  miniat.│ 64
│ │ [Ampliar]   │ │ $15.00 En stock   │      │ Faro de posición  │
│ └─────────────┘ │ Cant [−1+]        │      │ Hyundai Elantra   │
│ [▢][▢][▢] mini. │ [Añadir al carrito]│      │ $15.00  Disponible│
│ (oculta si 1)   │ [Comprar ahora]   │      │ [Añadir al carrito]│ fija abajo
│                 │ Descripción…      │      └───────────────────┘
├─────────────────┴───────────────────┤
│ Reseñas y comentarios               │
└─────────────────────────────────────┘
```
Foto 560×560 en escritorio (mitad de 1.216 menos el gutter); derecha, información en una columna de 560 px (línea de texto de ≤ 65ch); un solo botón principal ("Añadir al carrito"), "Comprar ahora" como secundario de contorno; cantidad con − / + solo si hay más de 1 unidad; en móvil la barra inferior fija de 64 px con precio y botón.

**B (ambiciosa)**: A + cuadro de compra pegajoso en escritorio (precio, stock, cantidad y botones quedan visibles al leer reseñas), pestañas "Descripción / Compatibilidad / Reseñas", sello de confianza corto (garantía, retiro) y estante de "Piezas relacionadas" (misma sección o mismo auto) al final.

**Recomiendo A ahora + el estante de relacionadas de B** (poca complejidad, mucho valor para un inventario de piezas únicas que se agotan: si esta ya no está, hay una cercana). El cuadro pegajoso y las pestañas, en la sesión de la galería.

### 5.4 Carrito

Ver sección 6.4 (observación 4) con las dos composiciones.

### 5.5 Encabezado y pie

**Encabezado A (recomendada)**: una barra de 64 px.

```
Escritorio 64 px
[logo40] Rastro del Istmo   Inicio Catálogo Quiénes Contacto  [ Buscar pieza…   🔍 ][☼][🛒 1][Cuenta▾]
         RASTRO & PARTES
Móvil: fila 1 (56 px fija)          fila 2 (48 px, no fija)
[logo36] Rastro del Istmo  [🛒][☰]   [ Buscar pieza…                 🔍 ]
```
Cuenta: "Iniciar sesión" como enlace de texto y "Crear cuenta" como botón secundario; con sesión, un menú de usuario (Perfil, Pedidos, Cerrar sesión por POST). El botón "Admin" pasa al menú de cuenta como "Ir al panel".

**Encabezado B**: dos niveles (arriba: marca, búsqueda, cuenta, carrito; abajo: categorías como menú con las secciones y conteos). Útil para plantillas con muchas categorías (ropa); con 4 secciones es demasiado.

**Pie A (recomendada)**: 4 columnas (marca y descripción · Comprar: Catálogo, Carrito, Mi cuenta · Empresa: Quiénes somos, Contacto · Contacto y horario), con los iconos con función permitidos en el pie (ubicación, teléfono, correo, reloj y botones de redes; decisión de Tony, 2026-10-25, que corrige la regla de «sin cuadritos»); los datos de contacto como enlaces `tel:` y `mailto:`. **Pie B**: A + banda de confianza previa y fila de redes/métodos de pago.

### 5.6 Dashboard

**A (recomendada)**

```
┌──────────────────────────────────────────────────────────────┐
│ Panel de control                    [+ Nueva parte][Registrar venta]│
├──────────────┬──────────────┬──────────────┬─────────────────┤
│ Pedidos por  │ Stock bajo   │ Vendido este │ Piezas activas  │ 112 px
│ atender  3   │ 13           │ mes $1,240   │ 49              │
│ → ver        │ → ver lista  │ → ventas     │ → partes        │
├──────────────┴──────────────┼──────────────┴─────────────────┤
│ Pedidos por atender (5)      │ Stock bajo (5 más urgentes)    │
│ #  Cliente  Total  Estado    │ Pieza            Quedan        │
│ …                            │ …                              │
├──────────────────────────────┴────────────────────────────────┤
│ Accesos recientes (5 filas)        [Ver bitácora completa →]  │
└──────────────────────────────────────────────────────────────┘
```
Un solo rojo (stock bajo > 0) y un solo énfasis ("Pedidos por atender" cuando hay pendientes: Von Restorff). Sin iconos de colores. Cada KPI con periodo y destino. Alto ≈ 700 px: todo cabe sin bajar a 1280×800.

**B**: A + gráfico de ventas de los últimos 30 días (barras en SVG, sin librería), "Más vendidas" y KPI con comparación contra el periodo anterior (GitLab). Distinto por rol: el Vendedor ve ventas y pedidos, el Consultor solo lectura, el Administrador además la bitácora.

**Recomiendo A.** B pide consultas por fecha y un gráfico: sesión aparte, después de las listas.

### 5.7 Lista del panel (Partes como modelo para las demás)

**A (recomendada)**

```
Escritorio                                           Móvil (< 768)
┌────────────────────────────────────────────────┐   ┌─────────────────────┐
│ Inventario de partes (49)         [+ Nueva parte]│   │ Inventario    [+]   │
│ [ Buscar…        ] [Todas|Con stock|Bajo|Agotadas]│   │ [ Buscar…       ]   │
├────────────────────────────────────────────────┤   │ [Todas ▾]           │
│ Foto Nombre / Auto · Sección  Precio Stock Acc. │   │ ┌─────────────────┐ │
│ [48] Faro de posición         $15.00  55  ✎ ⌫ ↗│   │ │[56] Faro posic.  │ │
│      Hyundai Elantra · Sec. C                   │   │ │ Hyundai · Sec C  │ │
│ …  (filas de 56 px)                             │   │ │ $15.00 · 55 en st│ │
└────────────────────────────────────────────────┘   │ │ [Editar][Desact.]│ │
                                                      │ └─────────────────┘ │
                                                      └─────────────────────┘
```
Columnas: foto 48, Nombre (con auto y sección como segunda línea), Precio, Stock (insignia con texto), Acciones. Salen de la lista: id (va al editar), Firma (a editar/detalle, es dato técnico) y la sección y el auto como columnas separadas. En móvil, tarjeta (`admin-card-mobile`, ya decidida) con las acciones siempre visibles. El enlace "Ver en la tienda" abre en la **misma pestaña**.

**B**: A + selección múltiple con acciones en lote (desactivar varias, exportar), cabecera fija, filtros guardados y edición rápida en un panel lateral. Más útil cuando el inventario pase de ~200 piezas.

**Recomiendo A.** B cuando haya volumen.

### 5.8 Acceso, páginas informativas y formularios del panel

- **Acceso (A recomendada)**: la tarjeta de 430 px con el logo y el nombre de la marca en lugar del icono de herramientas, el correo conservado si hay error, enlace de volver como texto de 44 px. **B**: panel dividido (foto de marca a la izquierda desde 992 px). No lo recomiendo: añade una foto más que mantener.
- **Quiénes somos**: texto en una columna de 65ch + foto 4:3 con `cover` (decorativa, no es producto) + insignias de confianza; la banda final se mantiene. **Contacto**: añadir "Llamar" y "WhatsApp" como botones de 44 px.
- **Formularios del panel**: columna central de 720 px (`max-width`), campos de dos columnas desde 768 px, barra de acciones fija abajo en móvil, previsualización de la foto con la misma baldosa 1:1 que verá el cliente. **Detalle de pedido**: acción principal "Cambiar estado" en la cabecera (abre el modal de confirmación ya decidido) y una sola factura.

---

## 6. Observaciones tuyas resueltas

### 6.1 Marca del encabezado (observación 1)

**Causa medida.** El texto va en un `<div>` cuyo tamaño de letra es 16 px con interlineado 1,5; el lema es un `<span>` en línea de 9,28 px (`public_header.php:26`). La línea que lo contiene mide 24 px (la del `<div>`), no 11, y empuja el lema **13 px** por debajo del nombre. El bloque de texto mide 46 px y el logo 32 px (48×32): el logo es más pequeño que el texto y queda centrado, pero se lee como dos cosas. El lema (9,28 px, mayúsculas, espaciado de .12em) está por debajo del mínimo de DESIGN.md (12 px).

Además, el logo recibe un filtro CSS (`brightness(0) saturate… hue-rotate`) para teñirlo de dorado: solo funciona con logos oscuros de un solo color y arruina cualquier otro (la plantilla vendible no puede depender de eso).

**Especificación propuesta [E6]**

| Elemento | Medida |
|---|---|
| Barra | 64 px (56 en móvil) |
| Logo | caja de **40 px de alto** (36 en móvil), ancho automático, `max-width: 56 px` si va con texto y 160 px si el logo ya incluye el nombre; `object-fit: contain` |
| Separación logo–texto | **12 px** |
| Nombre | 16/700, interlineado 1,2 (19 px) |
| Lema | 12/500, interlineado 1,2 (14 px), 2 px bajo el nombre, sin mayúsculas forzadas; se oculta por debajo de 360 px |
| Alineación | `display:flex; align-items:center` del bloque completo; el alto del texto (35 px) y el del logo (40 px) quedan a ≤ 5 px de diferencia: se lee como una pieza |
| Zona de clic | todo el conjunto es un solo enlace de ≥ 44 px de alto |

**Logos de distinta proporción.** `marca.php` ya tiene `logo`; propongo dos claves nuevas (a confirmar al implementar): `logo_modo` = `simbolo+texto` | `solo_logo` (cuando el logo ya trae el nombre, no se repite el texto) y `logo_claro` (variante opcional para el modo claro).

**SVG.** Preferible: vectorial, sin texto sin convertir a trazos, `viewBox` definido, ≤ 10 KB, un color con `fill="currentColor"`. Con eso el logo toma el color del tema (texto principal, o amarillo si es el símbolo) y desaparece el filtro CSS. Para que `currentColor` funcione, el SVG debe ir **en línea** (PHP lo lee de `assets/img/` y lo imprime tras sanearlo: solo etiquetas `svg`, `path`, `g`, `circle`, `rect`, `polygon`; sin `script` ni atributos `on*`); como `<img>` no hereda color. Respaldo: PNG de 192×128 como hoy, sin filtro. Misma pieza en el pie (32 px), acceso (48 px) y panel (32 px; hoy 22).

### 6.2 Portada y dashboard (observación 2)

La composición nueva está en 5.1 y 5.6. La razón de fondo: hoy ambas pantallas siguen el esquema "tarjeta de cifra + lista", que muestra datos pero no ayuda a decidir ni a actuar. La portada nueva responde "¿qué venden y tienen mi pieza?" (hero con buscador por vehículo, categorías, novedades). El dashboard nuevo responde "¿qué tengo que hacer hoy?" (pedidos por atender, stock bajo, ventas del mes).

### 6.3 Fondo de la portada y derechos de imagen (observación 3)

**Capa según el modo.**

| Modo | Capa | Color del texto del hero |
|---|---|---|
| Oscuro | `canvas-dark` al **α** que pase la prueba en el píxel más claro | `text-strong` (blanco) |
| Claro | `surface-soft-light` (#F7F3E8, cálido) al α que pase la prueba | `ink` (#181A20) |

Hoy el hero se queda oscuro en los dos modos (decisión de la base visual) y la capa es `canvas-dark` al 65 %. Para aplicar tu idea hay que (1) cambiar la foto por una sin oscurecer, (2) poner el α en una variable por modo en `tema.css` y (3) hacer que el color del texto del hero siga el modo.

**Cuánto α hace falta (cálculo mío, a confirmar con `tests/Support/contraste_hero.js`).** Para texto blanco (4,5:1) el fondo no puede pasar de ~0,18 de luminancia relativa. Con una foto que contenga blanco puro, la capa oscura debe ser de ~82 % (≈ 86 % para el texto de cuerpo #EAECEF). Con ese α la foto casi no se ve. Por eso la foto actual viene oscurecida (brillo medio 0,15, máx. 0,49; `hero-bgg.jpg`, sin uso, tiene 0,28 y 1,0). En claro la exigencia es menor: con texto #181A20 basta un α de ~25 % sobre un píxel negro, así que ahí se puede dejar ver más la foto (60–75 %).

Dos formas de salir del problema:
1. **Foto elegida por exposición**: hacer o elegir una foto con pocas zonas blancas (máx. < 0,6) y α de 70–75 % en oscuro.
2. **Texto fuera de la foto** (hero B de la sección 5.1): no hay capa ni problema de contraste.

**Opciones de imagen sin problemas de derechos**

| Opción | Derechos | Riesgo | Cuándo |
|---|---|---|---|
| **A. Fotos propias** (del local y de las piezas) | Tuyos | Ninguno; es lo más creíble (NN/g recomienda fotos reales y no de banco de imágenes) | Rastro del Istmo, siempre |
| **B. Unsplash / Pexels / CC0** | Uso comercial sin permiso ni crédito | No se puede revender la foto sin modificarla, ni usar marcas (un logo de coche en la foto), ni personas reconocibles sin permiso; Pexels no permite redistribuirla en otras plataformas; Unsplash no permite compilarlas para un servicio similar. **Una plantilla que se vende y trae la foto dentro entra en zona gris** | Para ti, como imagen de fondo de tu sitio, sí; **no** dentro de la plantilla |
| **C. Hero sin foto** (tipografía y color de la marca) | Ninguno | Ninguno; carga más rápido; funciona con cualquier rubro | **Valor por defecto de la plantilla** |
| D. Foto de un banco de pago | Licencia | Cuesta dinero y suele limitar la reventa | No recomendado |

**Recomendación.** Plantilla = hero tipográfico (C), con la foto como opción que el comprador sube. Rastro del Istmo = fotos propias (A). Reemplazar la actual antes de vender: no conozco su origen (la descargaste de internet) ni su licencia, y `hero-bgg.jpg` y `carro1.jpg` (sin uso en las vistas) tampoco; quedan en el historial de git.

**Las fotos de producto también.** En tu captura de la portada, la pieza "Defensa Lateral Delantera" lleva un recuadro rojo dibujado encima: parece sacada de un anuncio. Y las de bujías y batería parecen de fabricantes. Para una tienda real hace falta tener derecho a cada una. En la plantilla, `demo.sql` no debe traer fotos (marcador neutro), como ya hace el marcador de foto ausente.

**Formato para las fotos propias del hero**: 3:2 apaisada, ≥ 1.600 px de ancho, JPEG q 75 de ≤ 150 KB más una copia de 800 px (≤ 50 KB) para móvil, como hoy.

### 6.4 Carrito (observación 4)

**Causa.** En escritorio, la columna derecha es una sola caja pegajosa (`top: 80px`) con resumen (~230 px) más formulario de invitado (seis campos y ~620 px). Mide más que la ventana de 800 px, así que el final del formulario solo se alcanza bajando la página entera; la columna izquierda tiene una tarjeta de producto de ~130 px y se acaba. El comprador pierde de vista lo que está comprando justo cuando escribe su dirección.

**Composición A (recomendada): dos pasos**

```
Paso 1 · Carrito                         Paso 2 · Tus datos
┌────────────────────────┬────────────┐  ┌────────────────────────┬────────────┐
│ Mi carrito (1 pieza)   │ Resumen    │  │ ‹ Volver al carrito    │ Tu pedido  │
│ ┌────┐ Caja de fusibles│ Subtotal   │  │ Nombre*     Teléfono*  │ [foto] Caja│
│ │80px│ Hyundai Elantra │ $45.00     │  │ Correo*                │ de fusibles│
│ └────┘ $45.00 [−1+] 🗑 │ ITBMS 7%   │  │ Dirección de entrega*  │ ×1  $45.00 │
│        Deshacer…       │ $3.15      │  │ Mensaje (opcional)     │ Subtotal   │
│                        │ Total      │  │ Contraseña* (nota)     │ ITBMS      │
│ ¿Sigues comprando? →   │ $48.15     │  │                        │ Total      │
│                        │ [Continuar │  │ [Confirmar pedido      │ $48.15     │
│                        │  con datos]│  │  · $48.15]             │            │
└────────────────────────┴────────────┘  └────────────────────────┴────────────┘
```
- Paso 1: lista de líneas (foto 80×80 `contain` sobre baldosa, nombre, compatibilidad, precio, cantidad con − / + de 44 px que actualiza al instante, quitar con "Deshacer" en lugar de `confirm()`). Resumen de ≤ 260 px, pegajoso (`top: 88 px`) y **siempre más bajo que la ventana**. Un solo botón principal: "Continuar con tus datos".
- Paso 2: el formulario a la izquierda con campos de una columna (≤ 560 px), y a la derecha un resumen compacto (foto pequeña por línea, subtotal, ITBMS, total) que nunca crece más de 360 px. Al cliente con sesión se le salta lo que ya se conoce: solo dirección y mensaje.
- **Móvil**: el resumen pasa a una barra inferior fija de 64 px ("Total $48.15 · Ver resumen") que abre una hoja con el detalle; el botón principal va debajo del formulario.
- Si hay un error, la pantalla vuelve **con lo escrito** y el campo con el error marcado.

Respaldo: Baymard (mostrar el costo completo antes de pedir; pocos campos; no esconder el resumen), Fitts (botón grande y cercano), Hick/Miller (cada paso tiene 3–4 decisiones, no 9), proximidad (qué compras / quién eres / cuánto pagas, cada cosa en su lugar).

Efecto lateral útil: el paso 2 es exactamente la pantalla donde después entra la **compra como invitado** (función futura): hoy pide contraseña y entonces la pedirá al final de forma opcional. Y las cuatro entradas de compra se reducen a dos (carrito y "Comprar ahora" lleva al paso 2 con la pieza ya en el carrito).

**Composición B: una sola página con acordeón.** Tres bloques apilados (1 Carrito, 2 Datos, 3 Revisar y confirmar); al completar uno, se pliega en un resumen y se abre el siguiente. Resumen pegajoso a la derecha sin formulario dentro. Más moderna, pero implica JavaScript para el acordeón, un estado intermedio en el servidor y más pruebas. Baymard indica que los pasos completados deben plegarse en resúmenes. **Después de la compra como invitado, B es la evolución natural de A.**

### 6.5 Fotos de producto (observación 5)

**Datos.** 50 miniaturas: 18 son 315×315; el resto, 315 de alto con anchos de 324 a 420 (y un caso de 420×259); proporciones de 0,8 a 2,0, mediana 1,07. Originales de 400×300 hasta 2.560×2.011 (un total de 4,6 MB; la mayor, 565 KB). Se generan "dentro de" 420×315 sin recortar (`ImageHelper.php:135`, `THUMB_W/H`).

**Qué se ve mal y por qué.**
1. La caja cambia de proporción según la pantalla (0,87 en 390, 1,33 en 1280, 1,60 en 1024; ver 4.1), porque `height:200px` (style.css:281) manda sobre `aspect-ratio:4/3` (extra.css:11). La foto se recorta de formas distintas en cada ancho.
2. `cover` recorta: una foto cuadrada en una caja 4:3 pierde un 25 % por arriba y abajo; una moldura larga pierde sus extremos.
3. Las miniaturas de 315 px se amplían en cajas de 321–361 px: se ven borrosas ("estiradas").
4. El detalle carga la original (hasta 565 KB) en una caja de 437 px y no coincide con el recorte de la tarjeta.
5. Más "bordes": las fotos de fondo blanco sobre una tarjeta oscura muestran un rectángulo blanco irregular.

**Estándar propuesto**

| Contexto | Proporción | Tamaño en pantalla | Archivo | Comportamiento |
|---|---|---|---|---|
| Tarjeta de catálogo | **1:1** | 286 px (escritorio), 96 px (móvil lista), 173 px (móvil 2 col.) | miniatura de **640 px** en el lado mayor, JPEG q82 (~40 KB) | `contain` sobre baldosa clara |
| Detalle, imagen principal | **1:1** | 560 px (escritorio), 358 px (móvil) | **1.200 px**, q85 (~120 KB) | `contain`; clic o botón "Ampliar" abre la original |
| Original | la que subió | solo al ampliar | máx. 2.400 px de lado (hoy 6.000) re-codificado | Para el zoom (Baymard) |
| Miniaturas de galería | 1:1 | **64 px** (objetivo ≥ 44 + 8 de separación) | la de 640 recortada a 128 | `contain` |
| Carrito y resumen | 1:1 | 80 / 48 px | la de 640 | `contain` |
| Panel (tabla) | 1:1 | 48 px | la de 640 | `contain` |
| Fotos decorativas (hero, Quiénes somos) | libre | — | JPEG optimizado | `cover` (aquí sí se recorta: no es mercancía) |

**Mostrar completa, no recortar.** Para repuestos, el comprador necesita ver la pieza entera; recortar una moldura o un faro es esconder el producto (Baymard: miniaturas homogéneas, pero sin esconder el artículo). Por eso `contain` sobre una baldosa de color fijo [E4]: blanco `canvas-light` en los dos modos. Las fotos de fondo blanco se funden con la baldosa; las demás quedan con bordes limpios y uniformes, y la rejilla se ve ordenada. (Si prefieres baldosa oscura en modo oscuro, las fotos de fondo blanco muestran el rectángulo: pregunta 2.)

**Qué pasa al subir una imagen de otra proporción.** Nunca se estira ni se recorta sin avisar:
- Se acepta; se re-codifica (como hoy) y se generan las tres derivadas (640, 1.200, 2.400).
- El formulario muestra la **vista previa exacta** de la baldosa 1:1 (hoy muestra 90×68 recortada).
- Avisos (no bloqueos): "Proporción muy alargada: se mostrará con bordes" si la proporción sale de 0,67–1,5; "Resolución baja: se verá borrosa al ampliar" si el lado mayor < 800 px; rechazo solo por las reglas de seguridad actuales (tipo, tamaño, píxeles).
- Para las 50 piezas actuales: regenerar miniaturas desde las originales (script de línea de comandos, con respaldo de `uploads/` antes). No cambia nada en la base: los nombres de archivo no cambian.

### 6.6 Galería con zoom (observación 6): espacio y patrón reservados

No se diseña en detalle. En la ficha se reserva este esqueleto (sin JavaScript hasta esa función):

```
<figure class="galeria" data-galeria>
  <div class="galeria-principal">  <!-- 1:1, baldosa, imagen principal -->
     <img … alt="Nombre de la pieza">
     <button type="button" class="galeria-ampliar" aria-label="Ampliar la imagen">  <!-- 44×44, esquina inferior derecha -->
  </div>
  <ul class="galeria-miniaturas" role="list" hidden>   <!-- 64 px; se muestra con 2 o más imágenes -->
     <li><button aria-label="Ver imagen 1 de N" aria-current="true"><img …></button></li>
  </ul>
</figure>
```

Patrón reservado: miniaturas como **botones** (no puntos; Baymard), `<dialog>` modal para la vista ampliada (el foco queda atrapado y se cierra con Esc), flechas ← → entre imágenes, pellizco y doble toque en móvil, aviso "Toca dos veces para ampliar" que se desvanece, y carga de la original solo al ampliar. En la base haría falta una tabla de imágenes por pieza (cambio de esquema, sesión aparte).

### 6.7 Navegación e interacciones (observación 7)

**Hallazgos**

| # | Dónde | Qué pasa | Arreglo |
|---|---|---|---|
| N1 | `admin_header.php:48` | "Tienda" con `target="_blank"`: cada ida y vuelta acumula pestañas | Misma pestaña; en la tienda, "Ir al panel" también misma pestaña |
| N2 | `partes/index.php:56` | "Ver público" con `target="_blank"` | Misma pestaña; el aviso accesible "(se abre en una pestaña nueva)" se quita |
| N3 | `pedidos/detalle.php:11 y :81`, `mis_pedidos.php:23`, `compra_exitosa.php:40` | Facturas PDF en pestaña nueva | **Correcto** (PDF). Añadir `rel="noopener"` y el aviso accesible en los que faltan; en el pedido hay dos enlaces a la misma factura: dejar uno |
| N4 | `contacto.php:51` | Redes sociales externas en pestaña nueva con `rel="noopener noreferrer"` | **Correcto** (externo) |
| N5 | `public_header.php:63`, `mi_perfil.php:23`, `admin_header.php:49` | "Salir" es un enlace GET a `logout.php` | POST con token (ver abajo); en el panel, dentro del menú de cuenta en lugar de un botón rojo permanente |
| N6 | `PedidoController::agregarCarrito` (:115) | Añadir desde el catálogo o la portada **redirige al detalle de la pieza**: el usuario cambia de página sin pedirlo y, al pulsar Atrás, vuelve a una copia anterior de la misma página | "Añadir" con `fetch`: el contador del carrito cambia y sale un aviso con "Ver carrito"; sin JavaScript, queda la redirección actual pero a la página de origen |
| N7 | Errores de compra/registro/inicio de sesión (`PedidoController` :37 y :213, `AuthController` :37 y :77) | Redirigen con un aviso y **sin lo escrito**; en el login se pierde el correo | Guardar los campos no secretos en sesión de un solo uso (`old`) y devolverlos al formulario; la contraseña nunca se devuelve |
| N8 | `ParteController::crear` | El formulario nuevo no recibe `$parte`: con un error, se pierde todo | Pasar lo escrito a la vista (editar ya lo hace) |
| N9 | `CsrfToken::verify` | El token es de **un solo uso** (`unset` tras verificar) y los errores muestran una página blanca con `javascript:history.back()`. Con dos pestañas abiertas (justo lo que hoy provoca "Tienda") la primera pestaña falla al enviar | Token **por sesión**, no de un solo uso (patrón estándar "synchronizer token"), y en un fallo redirigir al formulario con "La página caducó, inténtalo de nuevo" conservando lo escrito. Es un cambio de seguridad: se plantea en modo Plan |
| N10 | Doble clic en "Confirmar" | El segundo envío falla por el token de un solo uso y muestra la página blanca | Desactivar el botón al enviar (`disabled` + "Enviando…") |
| N11 | Filtros del catálogo y del panel | `onchange` envía el formulario al cambiar (WCAG 3.2.2) | Botón "Aplicar" explícito; los chips activos se quitan con un enlace |
| N12 | Tras iniciar sesión | No vuelve a donde estaba (ya anotado: enlace de factura del correo) | `?volver=` firmado a rutas internas |

**"Salir" por POST.** Un enlace GET que cierra sesión lo puede disparar una imagen en otra página, la precarga del navegador o un clic accidental. La práctica de los frameworks (Symfony, Spring, Trac) es POST con token. Como las cookies ya son `SameSite=Lax`, un envío desde otro sitio no llevaría la sesión; aun así se exige el token y, si falla, se redirige al inicio de sesión (sin dejar la sesión abierta y sin página de error). Sin "¿seguro?" para salir: es reversible; la regla es que **ningún enlace cierre sesión**, no que se pregunte.

**Atrás.** Navegación entre pantallas por GET (portada, catálogo con filtros y página, detalle, carrito paso 1 y 2) se queda así: cada estado tiene su URL y Atrás funciona. Los cambios de estado son POST con redirección (patrón PRG) y no deben crear entradas de historial que obliguen a pulsar Atrás dos veces: N6 lo resuelve para el carrito.

**Prueba que falle si un enlace interno vuelve a usar `target="_blank"`** (se implementa en la sesión P0, aquí solo se especifica):

1. `tests/Unit/EnlacesPestanaNuevaTest.php` — estática. Recorre `app/Views/**`, `assets/js/*.js` y los controladores que arman HTML. Para cada `<a … target="_blank" …>` extrae el `href` y exige **una** de estas dos: (a) es una ruta de PDF permitida (`action=factura` o `mod=factura`), o (b) es una URL absoluta `http(s)://` que **no** empieza con `APP_URL`. Además exige `rel` con `noopener`. Prohíbe `window.open(` y `target="_blank"` en `<form>`. Mensaje de fallo: archivo, línea y el `href`.
2. `tests/Integration/EnlacesRenderizadosTest.php` — dinámica. Carga las 47 pantallas reales de `tests/Support/Pantallas.php`, parsea cada una con `DOMDocument` y aplica la misma regla a todos los `a[target]` ya con los `href` resueltos (esto cubre las redes que salen de `marca.php`).
3. Pruebas de "Salir": ninguna vista contiene `href` a `logout.php`; `logout.php` por GET responde 405 o redirige **sin** destruir la sesión; por POST con token válido la destruye; con token inválido no deja la sesión abierta y redirige al acceso.
4. Mutaciones (copia temporal, como pide `CLAUDE.md`): añadir `target="_blank"` al botón "Tienda", a un enlace del menú y a un `href` relativo del carrito, y quitar el `rel` a un PDF; las cuatro deben ser detectadas.

---

## 7. Backlog priorizado, por sesiones

Reglas de todas las sesiones (de `CLAUDE.md`): `composer test` verde antes y después, pruebas nuevas por función, mutaciones en copia temporal, un commit por sesión solo cuando lo ordenes, base real solo con SELECT hasta tu OK, medición en el navegador al final. Las **maquetas visuales** se hacen antes de cada sesión de pantalla, solo de las opciones que elijas.

Las estimaciones de pruebas nuevas son aproximadas: unas 220 en las sesiones públicas y unas 125 en las del panel (≈ 345), sin contar las funciones futuras.

### 7.1 Público

| Sesión | Qué se hace | Pruebas nuevas | Riesgos |
|---|---|---|---|
| **P0 — Navegación y formularios** (obs. 7) | N1–N5, N7, N8, N10; prueba de pestañas nuevas; "Salir" por POST; datos conservados en errores; botón desactivado al enviar. **N9 (token por sesión) en modo Plan** | ~30 | Toca seguridad (CSRF, sesión) y el flujo de compra. Las pruebas de acceso que usan `logout.php` por GET cambian |
| **P1 — Sistema de imágenes** (obs. 5) | `ImageHelper`: derivadas de 640 / 1.200 / 2.400 px; regeneración de las 50 existentes con respaldo; vista previa y avisos en el formulario; `ImagenesPesoTest` actualizado | ~20 | Reescribe archivos de `uploads/` (respaldo previo en `C:\wamp64\respaldo_rastro\`); sin cambio de esquema |
| **P2 — Marca y encabezado/pie** (obs. 1) | Bloque de marca (6.1), logo SVG en línea y saneado, `logo_modo` y `logo_claro` en la marca, barra de 64 px, buscador en fila propia en móvil, menú de cuenta, pie de 4 columnas sin cuadritos; el logo también en acceso y panel | ~25 | Hay que actualizar `MarcaPantallasTest`, `MenuPublicoTest` y `EstructuraVistasTest`; saneado del SVG (seguridad) |
| **P3 — Catálogo y tarjeta** | Contenedor 1280, 4/3/2 columnas, tarjeta 1:1, lista horizontal en móvil, filtros plegados y chips, conteo real, 24 por página, "Añadir" sin recarga (N6, N11) | ~35 | Enmiendas E1, E2, E4 y E5 a DESIGN.md; `Paginador::CATALOGO` cambia de 12 a 24 y hay pruebas que lo suponen |
| **P4 — Detalle de pieza** | Estructura A de 5.3, esqueleto de galería reservado (6.6), cantidad con − / +, barra inferior en móvil, relacionadas | ~25 | Consulta nueva de relacionadas |
| **P5 — Carrito en dos pasos** (obs. 4) | 6.4 A: paso 1, paso 2, resumen compacto, cantidades inmediatas, quitar con deshacer, resumen inferior en móvil, datos conservados | ~40 | **Toca dinero y stock**: modo Plan, pruebas de flujo completo con `php -S` contra `rastroinventario_test`; correos de prueba solo `@example.invalid` |
| **P6 — Portada** (obs. 2 y 3) | Bloques de 5.1 (A, o B con selector de vehículo), "Recién llegadas", compra por marca, banda; fondo con capa por modo (variables en `tema.css` y prueba de contraste en dos modos); hero tipográfico por defecto | ~30 | Depende de P3 (tarjeta) y de P1 (fotos); la foto nueva y el α se miden con `contraste_hero.js`; el selector de vehículo (B) agrega consultas |
| **P7 — Acceso, informativas y errores** | Acceso con marca, Quiénes somos y Contacto con acciones (Llamar, WhatsApp), 404 y 500 temáticas (ya anotado en la auditoría) | ~15 | `.htaccess`/`ErrorDocument` solo en el servidor |

### 7.2 Panel

| Sesión | Qué se hace | Pruebas nuevas | Riesgos |
|---|---|---|---|
| **A1 — Estructura del panel** | Contenedor de 1440, encabezado con menú de cuenta (Cambiar contraseña, Cerrar sesión por POST), patrón de cabecera de página (título + acción principal), avisos tipo "toast" | ~15 | Choca con P0 si no se hace después de ella |
| **A2 — Modal de confirmación** (decidido) | Modal con el nombre del objeto y el verbo; sustituye los `confirm()`; añade desactivar usuario | ~20 | Los `confirm()` no se pueden probar en el navegador integrado: se cubre por script y se dice qué no se cubrió |
| **A3 — Listas como tabla y tarjeta** (decidido) | Partes primero como modelo (5.7 A) y luego pedidos, ventas, usuarios, comentarios, autos, secciones, roles; `data-label` por celda, filtros por estado y fecha en pedidos | ~45 | Es el cambio más grande en vistas; 10 listas |
| **A4 — Dashboard** | 5.6 A: KPI con periodo, "por atender" y "stock bajo" con lista, bitácora reducida; consultas nuevas | ~25 | Consultas por fecha; confirmar qué cuenta como "pendiente" |
| **A5 — Formularios y detalle de pedido** | Columna de 720, barra de acciones fija en móvil, vista previa de foto igual a la de la tienda, una sola factura, "Cambiar estado" en la cabecera | ~20 | Toca el cambio de estado del pedido (stock y ventas): pruebas de las transiciones |

### 7.3 Orden recomendado y dependencias

`P0 → P1 → P2 → P3 → P4 → P5 → P6 → P7` y, en paralelo con P3 en adelante (sesiones distintas y sin tocar los mismos archivos): `A1 → A2 → A3 → A4 → A5`.
Razones: P0 arregla errores que hoy ya molestan y reduce pestañas y fallos de token en todas las pruebas manuales siguientes; P1 deja la base de fotos para tarjeta, detalle, carrito y portada; P2 pone la marca antes de rehacer pantallas que la muestran; P3 define la tarjeta que reusan portada, detalle y estantes.

### 7.4 Sesiones aparte (funciones futuras, no incluidas arriba)

| Sesión | Qué | Pruebas estimadas | Riesgos |
|---|---|---|---|
| **F1 — Galería con zoom** | Tabla de imágenes por pieza, formulario de varias fotos, `<dialog>` con teclado, pellizco y doble toque, carga de la original solo al ampliar | ~35 | Cambio de esquema (migración con `aplicar.ps1`, respaldo, ensayo en test); accesibilidad con teclado y móvil |
| **F2 — Compra como invitado** | Cuenta opcional después; enlace de seguimiento firmado; sin dar acceso a cuentas existentes (no reabrir el hallazgo 1) | ~50 | **Seguridad y dinero**: modo Plan; decisión ya tomada y registrada en `ESTADO.md` |

---

## 8. Preguntas para ti (8)

1. **Fondo de la portada.** ¿Rastro del Istmo usa fotos propias (A), una foto de banco de imágenes solo en tu sitio (B) o el hero tipográfico (C)? Y, ¿la plantilla vendible lleva el hero tipográfico por defecto, como recomiendo? (Esto decide también si retiramos del proyecto la foto actual y `hero-bgg.jpg`.)
2. **Baldosa de las fotos de producto.** ¿Fondo blanco fijo en los dos modos (recomendado: las fotos de pieza suelen tener fondo blanco) o fondo oscuro en el modo oscuro (las fotos de fondo blanco mostrarán un rectángulo)?
3. **Catálogo en teléfono.** ¿Filas horizontales de una columna (B, recomendada para piezas con especificaciones) o dos columnas compactas (A, como hoy)?
4. **Carrito.** ¿Dos pasos (A, recomendada) o acordeón de una sola página (B)? Y en la ficha, ¿"Añadir al carrito" como botón principal y "Comprar ahora" como secundario (recomendado), o al revés porque casi todas las piezas son de una sola unidad?
5. **Logo.** ¿Tienes (o puedes conseguir) el logo en vector (SVG)? ¿Quieres dos variantes (oscuro y claro) o una sola de un color que tome el del tema? ¿El lema se muestra siempre o solo en escritorio?
6. **Contenido de la portada.** ¿Cuáles de estas afirmaciones de la franja de confianza son **ciertas** hoy: garantía (¿cuántos días?), retiro en tienda, devolución, atención por WhatsApp, envío (¿a dónde?)? No pondré ninguna que no confirmes. ¿Quieres "Ofertas"? Haría falta guardar un precio anterior (cambio de esquema).
7. **Dashboard.** ¿A (lo accionable primero, bitácora abajo) o B (con gráfico de ventas de 30 días)? ¿La bitácora de accesos pasa a su propia pantalla "Seguridad" visible solo para el administrador?
8. **Enmiendas a DESIGN.md** (3.7): 3 columnas en tableta; precio de tarjeta a 20 px **y su color** (DESIGN.md dice amarillo, el sitio usa verde); KPI a 32 px; relleno lateral 16/24/32; baldosa de foto; la especificación de la marca. ¿Las apruebas tal cual o cuáles cambias?

---

## 9. Fuentes

Las 12 fuentes de esta fase (leí los extractos que devuelve la búsqueda; no abrí cada artículo completo, y el contenido de pago no se pudo leer):

1. Laws of UX — https://lawsofux.com/ (Hick: /hicks-law/, Fitts: /fittss-law/; Jakob, Miller y Von Restorff leídos en el mismo resultado)
2. NN/g, UX guidelines for ecommerce homepages, category pages and product listing pages — https://www.nngroup.com/articles/ecommerce-homepages-listing-pages/
3. NN/g, Opening links in new browser windows and tabs — https://www.nngroup.com/articles/new-browser-windows-and-tabs/
4. NN/g, Error-message guidelines — https://www.nngroup.com/articles/error-message-guidelines/
5. Baymard, Product listing page (PLP) UX — https://baymard.com/blog/product-listing-page-plp-ux
6. Baymard, Mobile gestures: 40 % of sites don't support pinch or tap for product images — https://baymard.com/blog/mobile-image-gestures
7. Baymard, Ensure sufficient image resolution and zoom — https://baymard.com/blog/ensure-sufficient-image-resolution-and-zoom
8. Carbon Design System, Dashboards — https://carbondesignsystem.com/data-visualization/dashboards/
9. GitLab Pajamas, Dashboards — https://design.gitlab.com/patterns/dashboards/
10. Unsplash, License — https://unsplash.com/license
11. Pexels, Free stock photo and video license — https://www.pexels.com/license/
12. Symfony UX Toolkit, Logout link (logout por POST con CSRF) — https://ux.symfony.com/toolkit/kits/common/components/logout-link.md

No leído (de pago o sin texto accesible): la guía de auditoría de autopartes de Baymard (https://baymard.com/audits/automotive-parts), los informes de ecommerce de NN/g y el texto completo de las licencias de Unsplash y Pexels (solo sus resúmenes). La licencia de cada foto concreta de un banco de imágenes se revisa en su propia página antes de usarla.

Criterios WCAG usados (de `docs/auditoria-diseno.md`, ya citados allí): 1.4.3, 1.4.10, 1.4.11, 2.4.7, 2.5.8, 3.3.1, 4.1.2. El único criterio nuevo que se menciona, **3.2.2 (cambio de contexto al introducir datos)**, es de conocimiento general: no abrí su página en esta sesión.

---

## 10. Decisiones de Tony (2026-10-25)

Respuestas a las 8 preguntas de la sección 8. Donde chocan con lo recomendado antes, mandan estas.

### 10.1 Respuestas

| # | Tema | Decisión | Efecto en el plan |
|---|---|---|---|
| 1 | Fondo de la portada | **Hero tipográfico** (sin foto) por defecto en la plantilla **y, por ahora, también en Rastro del Istmo**. Se retiran del proyecto la foto de portada actual (`hero-bg.jpg` y su copia `hero-bg-800.jpg`) y `hero-bgg.jpg` (origen desconocido). Si más adelante Tony confirma una foto propia o con licencia libre, se añade en la sesión de la portada | Sin foto no hay capa por modo ni problema de contraste: el hero usa fondos de token y **por fin sigue el modo** (oscuro: `canvas-dark`/`surface-card-dark`; claro: `hero-band-light`). Ver 10.3 |
| 2 | Baldosa de la foto de producto | **Blanca fija** (`canvas-light`) en los dos modos | Como en 3.3 y 6.5 |
| 3 | Catálogo en móvil | **Filas horizontales** (B) | Foto de 96 px a la izquierda; desde 576 px, rejilla |
| 4 | Carrito y ficha | **Carrito en dos pasos (A)**. En la ficha, **"Añadir al carrito" es el botón principal** y "Comprar ahora" el secundario (contorno) | Como en 6.4 A y 5.3 A |
| 5 | Logo | Sin SVG de origen: **rehacerlo como SVG de un solo color a partir del PNG actual**; si no se puede con calidad, avisar y proponer alternativa. **Una sola versión** que toma el color del tema. **Lema visible en escritorio y oculto en móvil** | Ver 10.2. Sustituye a `logo_claro` (ya no se necesita) y a "se oculta por debajo de 360 px" de 6.1: el lema se oculta por debajo de 768 px |
| 6 | Franja de confianza | **Ninguna afirmación confirmada** (garantía, retiro en tienda, devolución, WhatsApp, envíos): no se escribe ninguna. La franja se diseña para que sus textos salgan de `app/Config/marca.php` y **queda oculta** mientras no haya textos confirmados. **Sin "Ofertas"** por ahora | Ver 10.4 |
| 7 | Dashboard | **A** (lo accionable primero). La **bitácora de accesos pasa a una pantalla "Seguridad"**, solo para el Administrador | Ver 10.5 |
| 8 | Enmiendas a DESIGN.md | **Aprobadas todas las de 3.7** (E1 a E7). Sobre el precio (E2): **se sigue DESIGN.md: amarillo en oscuro y bronce en claro**; el verde queda **solo para éxito y stock disponible** | Ver 10.6 |

### 10.2 Logo: viabilidad medida

El PNG actual (`assets/img/logo.png`, 192×128, 5 KB) es una "R" plana con una raya de velocidad, de **un solo color** (~#FFD110; los 106 tonos distintos son el suavizado de los bordes) y con bordes casi todos rectos. Es un caso bueno para vectorizar: se calcula el contorno y se ajusta a unos pocos polígonos. **Viable con calidad**; no hace falta alternativa de entrada.

Cómo se hará en la sesión P2 (no se hace ahora):
1. Trazar el contorno del PNG ampliado y ajustarlo a polígonos; guardar `assets/img/logo.svg` (≤ 2 KB, `viewBox`, un solo `path` con `fill="currentColor"`).
2. **Prueba de fidelidad**: rasterizar el SVG al tamaño del PNG y compararlo píxel a píxel con el original (umbral acordado al hacerlo, por ejemplo diferencia < 1 % de la superficie). Si no pasa, se avisa.
3. Alternativa si no pasa (ya prevista): el mismo PNG como **máscara CSS** (`mask-image` con `background-color: currentColor`, PNG de 384×256) que también toma el color del tema sin filtro y sin SVG.
4. Color: el símbolo usa `var(--link)` (amarillo en oscuro, bronce en claro), el nombre `text-strong`. Se elimina el filtro CSS del encabezado.
5. El PHP lo imprime en línea tras sanearlo (solo `svg`, `path`, `g`; sin `script` ni atributos `on*`), con el PNG como respaldo si falta el SVG.

### 10.3 Portada con hero tipográfico

- **Composición elegida: B de 5.1, adaptada.** Como no hay foto, la columna derecha del hero deja de ser una foto de pieza y pasa a ser la **tarjeta "Busca por tu vehículo"** (Marca → Modelo → Año). Columna izquierda: título, una línea, búsqueda por nombre. Resto igual: franja de confianza (oculta, ver 10.4), categorías, "Recién llegadas", compra por marca, banda de llamada a la acción, pie.
- Hero de 400 px (escritorio) y 320 px (móvil); título a 64 px (36 en móvil), como pide DESIGN.md.
- **Archivos que salen del proyecto** (en la sesión P6, junto con el código que los usa): `assets/img/hero-bg.jpg`, `assets/img/hero-bg-800.jpg` y `assets/img/hero-bgg.jpg`.
  - **Copias ya hechas** el 2026-10-25 en `C:\wamp64\respaldo_rastro\imagenes_ux_20261025\` (SHA-256 verificado, idénticas a las originales).
  - **Por qué no se borran todavía**: `app/Views/public/home.php`, `assets/css/base.css`, `tests/Unit/HeroContrasteTest.php` y `tests/Unit/ImagenesPesoTest.php` (y `README.md`) los nombran; borrarlos ahora rompería la portada y pondría `composer test` en rojo, y esta tarea es solo de documentos. En P6 se borran a la vez que se quita el `background-image`, el scrim y `contraste_hero.js`/`HeroContrasteTest` (que pasan a medir el hero de color plano).
  - Siguen en el historial de git: no se reescribe. Si la foto es un problema de derechos, el archivo solo desaparece del árbol de trabajo.
- Pendiente de Tony (no decidido): `assets/img/carro1.jpg` (1448×1086, 192 KB) tampoco lo usa ninguna vista; igual que las otras, no se conoce su origen. No se tocó.
- Si más adelante hay foto propia o con licencia libre: se añade en P6 con la capa por modo de 6.3 (o con el hero B con foto), y se mide con `contraste_hero.js`.

### 10.4 Franja de confianza oculta

- Los textos salen de `marca.php` (clave nueva a crear en P6, por ejemplo `portada.confianza`, una lista de elementos con `icono`, `titulo` y `texto`). **Vacía hoy.**
- La vista no pinta nada (ni el contenedor ni el espacio de 88 px) si la lista está vacía o si ningún elemento tiene título. Se muestran como máximo 4.
- Pruebas: con la lista vacía la portada no contiene la franja; con 4 elementos sí y salen escapados; con 5, solo 4. Una prueba de la guarda de marca asegura que ninguna afirmación ("garantía", "devolución", "retiro", "envío", "WhatsApp") está escrita en las vistas.
- **Revisar a mano** (no la leí porque `app/Config/` está vedada): la vista **Quiénes somos** imprime `nosotros.destacados` de `marca.php` como insignias de confianza. Si ahí hay afirmaciones que todavía no están confirmadas (garantía, envío, etc.), se están publicando hoy. Tony debe mirar esa clave o autorizar que la lea.
- Sin "Ofertas": no se cambia el esquema para guardar un precio anterior.

### 10.5 Dashboard A y pantalla "Seguridad"

- **Dashboard (A de 5.6)**: KPI con periodo ("Pedidos por atender", "Stock bajo", "Vendido este mes", "Piezas activas"), listas cortas de pedidos por atender y de stock bajo, botones "Nueva parte" y "Registrar venta" en la cabecera. **La bitácora sale del dashboard.**
- **Seguridad** (`?mod=seguridad`): la bitácora completa de accesos con paginación de 20 filas, filtro por resultado (correcto / fallido), por correo y por fecha, y la hora en un solo formato. Solo la ve el **Administrador**.
  - Cómo se limita: por **nombre de rol "Administrador"**, igual que ya se hace con editar a otros administradores. Así no hace falta un cambio de esquema ni nuevos permisos en la base. Una persona sin ese rol que abra la URL recibe el mismo aviso que ante un módulo sin permiso.
  - Aparece en el menú del panel (en "Más") solo para el Administrador.
  - Riesgo ya anotado en `ESTADO.md`: las reglas buscan el rol por nombre y el módulo Roles permite renombrarlo. No se agrava; queda como parte de esa deuda.
- Sesión: se añade a **A4** (dashboard) unas 12 pruebas más.

### 10.6 Enmiendas a DESIGN.md aprobadas

| # | Enmienda | Estado |
|---|---|---|
| E1 | Catálogo en 3 columnas desde 768 px | Aprobada |
| E2 | Precio de tarjeta a 20/600 en la fuente numérica; **color: amarillo en oscuro (`primary-text-on-dark`) y bronce en claro (`link-on-light`, #7A5C00)**. El verde (`success`) queda solo para éxito y stock disponible | Aprobada, con el color decidido por Tony |
| E3 | Valor de las tarjetas KPI a 32 px | Aprobada |
| E4 | Baldosa blanca fija para la foto de producto | Aprobada |
| E5 | Relleno lateral del contenedor 16 / 24 / 32 | Aprobada |
| E6 | Especificación de la marca del encabezado (6.1, con el lema oculto bajo 768 px y sin variante clara del logo) | Aprobada |
| E7 | Barra superior de 64 px (hoy 81 en la tienda) | Aprobada |

- **Cómo se aplica el precio.** Un token nuevo `--precio` en `tema.css` (oscuro: `primary-text-on-dark`; claro: `link-on-light`) que sustituye a todo `text-success` o `--success` usado en precios y totales: tarjeta, detalle, carrito, pedidos, ventas, dashboard y reportes en pantalla (hoy ~29 precios). `TemaCssTest` suma los pares de contraste del precio en los dos modos. En claro, el bronce como texto da 6,25:1 sobre blanco (dato de DESIGN.md).
- **Verde que se queda**: avisos de éxito, "Disponible" / "En stock" (con texto), estado "entregado" y firma verificada. El "Quedan 3" y el stock bajo siguen en aviso (`warning`), y "Agotado" en error.
- **DESIGN.md hay que editarlo** para reflejar E1 a E7 (tabla de columnas, `product-price`, KPI, baldosa, contenedor, marca). Es una tarea de documentos: se hace como primer paso de P3 o, si se prefiere, en una sesión corta antes.

### 10.7 Composición elegida por pantalla

| Pantalla | Elegida | Nota |
|---|---|---|
| Portada | **B adaptada** (10.3) | Sin foto; tarjeta de vehículo a la derecha; franja de confianza oculta; sin ofertas |
| Catálogo y tarjeta | **A** en escritorio y tableta; **filas horizontales (B)** en teléfono | Barra de filtros a la izquierda, chips, "Aplicar", 24 por página |
| Detalle | **A + piezas relacionadas** | "Añadir al carrito" principal, "Comprar ahora" secundario; galería reservada |
| Carrito | **A, dos pasos** | |
| Encabezado y pie | **A** | Lema oculto bajo 768 px; pie de 4 columnas compacto, con iconos de función y botones de redes (corrección de Tony, 2026-10-25) |
| Dashboard | **A** | Con pantalla "Seguridad" |
| Lista del panel | **A** | Partes como modelo; tarjetas en móvil |
| Acceso, informativas, formularios del panel | Recomendadas de 5.8 | Acceso con marca; Contacto con "Llamar" (WhatsApp solo si Tony lo confirma, ver 10.4) |

### 10.8 Backlog actualizado

Cambios sobre la sección 7 (el resto no cambia):

| Sesión | Cambio |
|---|---|
| **P0b — Precio** (nueva, tras P0) | Token `--precio` y sustitución del verde en todos los precios. ~15 pruebas. Riesgo: toca muchas vistas y pruebas existentes de cifras (`MenusYCifrasTest`, `BaseVisualTest`) |
| P2 — Marca | SVG de un color por trazado + prueba de fidelidad (10.2); sin `logo_claro`; lema oculto bajo 768 px |
| P3 — Catálogo | Empieza con la edición de DESIGN.md (10.6) si no se hizo antes |
| P6 — Portada | Hero tipográfico y tarjeta de vehículo; retira las 3 fotos y su código/pruebas (10.3); franja de confianza oculta (10.4); sin foto ya no hay prueba de capa por modo sino una de contraste del hero en los dos modos; ~30 pruebas |
| P7 — Acceso e informativas | Contacto sin botón de WhatsApp hasta que Tony lo confirme |
| A4 — Dashboard | Más la pantalla "Seguridad" (10.5): ~37 pruebas en total |
| Total estimado | ≈ 370 pruebas nuevas, sin contar las funciones futuras (galería y compra como invitado) |

Orden: `P0 → P0b → P1 → P2 → P3 → P4 → P5 → P6 → P7`, y en paralelo `A1 → A2 → A3 → A4 → A5` (sesiones distintas que no toquen los mismos archivos).

### 10.9 Queda por decidir

1. Revisión de `nosotros.destacados` en `marca.php` (10.4): ¿lo miras tú o autorizas que lo lea?
2. `carro1.jpg`: ¿se queda o se retira como las otras?
3. Fotos de producto con posibles problemas de derechos (la defensa del Sentra, con un recuadro rojo): antes de publicar.
4. Siguiente paso: **maquetas visuales** de lo elegido (portada B adaptada, catálogo con tarjeta, detalle, carrito en dos pasos, encabezado y dashboard), o ir directo a P0.
