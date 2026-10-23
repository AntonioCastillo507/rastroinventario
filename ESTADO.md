# ESTADO DEL PROYECTO: Rastroinventario (Rastro del Istmo)

Última actualización: 2026-10-23

## Para Claude: cómo usar este archivo
- Lee este archivo al empezar cualquier sesión. Es la memoria del proyecto.
- Al terminar una tarea, actualiza las secciones "Hecho" y "Pendiente" y haz commit.
- Nunca escribas aquí contraseñas, llaves, tokens ni el contenido de `app/Config/rsa_secret.php`.

## Cómo trabaja Tony (reglas)
- Tony es el capitán: Claude edita los archivos directamente. Tony no quiere copiar y pegar código a mano.
- Código completo y listo para usar, no diffs parciales. Comentarios cortos y naturales, sin separadores decorativos.
- Comandos de terminal en bloques individuales, listos para copiar (PowerShell en Windows).
- Arreglos directos y específicos, sin reescribir todo ni crear scripts nuevos si no hace falta.
- Responder en español, de forma directa.
- Un commit por tarea, solo cuando Tony confirme. No hacer `git push` sin que Tony lo pida.
- Pruebas: TODAS las pruebas de la página las hace Claude, de forma automatizada, en el navegador integrado (y por script cuando haga falta). Tony no prueba a mano ni se le pide que lo haga.
- Única excepción: la contraseña del administrador la teclea solo Tony; Claude nunca la escribe. Si hace falta sesión de administrador y no la hay, Claude para y avisa.
- Si algo no se puede probar por un límite del navegador integrado (por ejemplo los `confirm()`, que se cancelan solos), se resuelve por script y se dice exactamente qué no se pudo cubrir.
- Al terminar una tarea, Claude entrega una tabla de pruebas con el resultado de cada una.

## El proyecto
- App PHP con estructura MVC. Corre en WampServer (Apache + MySQL) en Windows.
- Carpeta local: `C:\wamp64\www\rastroinventario` (URL: `http://localhost/rastroinventario`).
- Repo en GitHub: `AntonioCastillo507/rastroinventario`, **privado**.
- Rama de trabajo: `mejoras`. La rama `main` no tiene los cambios de seguridad todavía.
- Hoy es un proyecto personal de práctica. A futuro Tony quiere convertirlo en una página real.
- Entrada: `bootstrap.php`. Configuración: `app/Config/config.php`.
- Los instaladores `setup.php` y `setupfix*` se eliminaron del proyecto a propósito.
- `backup.sql` sigue en disco, pero ya no está en git.

## Cómo está configurada la herramienta
- Claude Desktop, pestaña Code. Sesión local, carpeta `rastroinventario`, modelo Sonnet 5.5.
- Modo Plan para auditar. Para trabajar: el modo que edita y pide permiso para comandos.
- No usar "Bypass permissions". No usar worktree (Wamp solo sirve la carpeta original).
- `.claude/settings.json` bloquea la lectura de `rsa_secret.php` y `.env`.
- `CLAUDE.md` en la raíz resume el proyecto.
- Una sesión nueva por tarea grande.

## Reglas de seguridad
- Nunca abrir ni mostrar `app/Config/rsa_secret.php` ni `ssl/rastro.key`.
- `rsa_secret.php` y `ssl/*.key` están en `.gitignore` y no deben subirse nunca.
- Antes de crear datos de prueba: respaldo de la base solo en `C:\wamp64\respaldo_rastro\`, nunca dentro del proyecto.
- Borrar datos de prueba solo con respaldo previo, ids exactos y OK de Tony.
- Pruebas con correos `@example.invalid`. EmailJS está encendido en Apache: las compras reales por navegador mandan correos de verdad.

## Hecho (commits en la rama `mejoras`)
| Commit | Qué | En GitHub |
|---|---|---|
| c8c4a44 | Etapa 1: compra de invitado ya no inicia sesión en cuentas existentes; archivos sensibles bloqueados por `.htaccess` y fuera de git | Sí |
| 5ed1f59 | Etapa 2A: facturas (IDOR), sesiones y cookies seguras, roles y cuentas desactivadas | Sí |
| 9c3342b | Llave RSA y certificado regenerados; aviso si falta la passphrase | Sí |
| cda46c5 | 2B-1: el módulo `pedidos` ya está en roles | Sí |
| 52ed90b | 2B-2: acciones que cambiaban datos por GET pasan a POST con CSRF; corregido `CsrfToken::verify()` | Sí |
| b214f54 | 2B-3: validación de cantidad y precio, sin sobreventa, carrito transaccional | Sí |
| 4711641 | 2B-4: bloqueo temporal de login (3 intentos / 15 min por correo + IP) y mensajes genéricos | Sí |
| 4c0f54f | ESTADO.md como memoria del proyecto y línea en CLAUDE.md | Sí |
| fca464f | Hallazgo 13: se elimina el módulo Herramientas (`HerramientasController`, su vista, el `require_once` en `bootstrap.php` y la entrada en `admin/index.php`) | Sí |
| eca6f1b | Error 2: stock y ventas al cambiar el estado de un pedido | Sí |
| f00201a | Error 1: ITBMS 7 % sumado y guardado en el pedido | Sí |
| 121afda | Hallazgo 11: errores internos solo al registro `logs/error.log`, mensaje genérico con código de referencia (ver "Registro de sesiones") | No |

## Pendiente inmediato
- [x] Limpieza de datos de prueba confirmada (2026-10-23). Línea base antes de las pruebas del navegador: 16 usuarios, 6 pedidos, 4 roles y 23 filas de `login_logs` (las 3 filas extra sobre las 20 esperadas son logins exitosos del administrador desde `::1`, ids 57 a 59).
- [x] Pruebas en el navegador (2026-10-23): carrito (añadir, cambiar cantidad con Enter, quitar), login del administrador, "Ver Factura PDF" del pedido 1 y compra normal. Todo OK.
- [x] `git push` de los commits 2B (cda46c5 a 4711641): hecho, `origin/mejoras` los contiene.
- [ ] Decidir qué hacer con los datos de prueba: pedidos 18 y 19 y usuarios 36 (`prueba2d@example.invalid`) y 37 (`prueba-itbms@example.invalid`). No borrar sin respaldo, ids exactos y OK de Tony. Ojo con el stock: el pedido 18 apartó 1 pieza (pieza 39) y el 19 apartó 2 de la pieza 34 y 1 de la 39 (hoy 53 y 37); cancelar un pedido desde el admin devuelve el stock.
- [ ] Hacer copia segura de `ssl/rastro.key` y `app/Config/rsa_secret.php` fuera del proyecto (gestor de contraseñas o USB). Sin la passphrase la llave no sirve.
- [ ] Borrar los volcados `rastro_pre2B_*.sql` y `rastro_pre2d_*.sql` de `C:\wamp64\respaldo_rastro\` cuando todo esté verificado.
- [ ] Cambiar desde el panel las contraseñas de las 7 cuentas de personal (admin, vendedor1 a 3, consultor1 a 3): todas siguen siendo las que decía el README (comprobado el 2026-10-23). Las contraseñas viejas siguen en el historial de git.

- [x] Error 2 (stock y ventas al cambiar de estado): hecho, commit `eca6f1b`, ya en GitHub. Ver "Registro de sesiones".
- [x] Error 1 (ITBMS), opción (b): hecho, commit `f00201a`, ya en GitHub (backfill de los pedidos 1 a 6 y 18 incluido). Ver "Registro de sesiones".
- Datos del pedido 19 (de las pruebas del Error 1): subtotal 65.00, total 69.55; 2 Faros de Posición Lateral + 1 Bujías NGK, estado pendiente. Su limpieza va en el punto de datos de prueba de arriba.

## Conteos actuales de la base (2026-10-23, con `SELECT COUNT(*)` sobre `rastroinventario`)
| Tabla | Conteo |
|---|---|
| usuarios | 18 |
| pedidos | 8 |
| roles | 4 |
| login_logs | 30 |
| vendido_parte | 6 |
| partes | 50 (49 + la parte de prueba 54, inactiva) |

- Parte de prueba **id 54** ("PRUEBA_SUBIDA hallazgo 12 (borrar con OK)", cantidad 0): con OK de Tony se eliminó desde el panel el 2026-10-23; sus 2 imágenes ya no están en `uploads/` (50 originales y 50 miniaturas, como antes). **La fila sigue en la base con `activo = 0`** (el panel solo desactiva): quitarla en la limpieza de datos de prueba, con respaldo, id exacto y OK de Tony. Respaldo previo a las pruebas: `C:\wamp64\respaldo_rastro\rastro_pre_subida_20261023_141327.sql`.

- Diferencia contra la línea base (16, 6, 4, 23): +2 usuarios (36 y 37) y +2 pedidos (18 y 19) de las compras de prueba 2d y del Error 1, y +6 logins en `login_logs`, todos del administrador desde `::1`.
- Datos de prueba creados: pedido 18 (1 pieza, Bujías NGK, pendiente) y usuario 36 (`prueba2d@example.invalid`) en 2d; pedido 19 y usuario 37 (`prueba-itbms@example.invalid`) en el Error 1. Los ids son altos porque el auto_increment siguió subiendo con las pruebas anteriores.
- Dato práctico: el MySQL 8.4.7 de Wamp escucha en el puerto 3306 (ahí está `rastroinventario`) y MariaDB en el 3307. El cliente de MariaDB no puede conectar al 3306; usar `C:\wamp64\bin\mysql\mysql8.4.7\bin\mysql.exe -u root -h 127.0.0.1 -P 3306 rastroinventario`.
- Respaldo previo a 2d: `C:\wamp64\respaldo_rastro\rastro_pre2d_20261023_093549.sql`.
- Al cerrar esas pruebas se cerró la sesión del administrador en el navegador integrado.
- El navegador integrado cancela los diálogos `confirm()`. Para probar "quitar del carrito" hubo que aceptar el aviso desde la página; no es un fallo de la app.

## Hallazgos de la auditoría
| # | Tema | Estado |
|---|---|---|
| 1 | Compra de invitado inicia sesión en cuentas existentes | Hecho |
| 2 | Contraseñas de prueba escritas en README.md e INSTRUCCIONES.md | Hecho en los archivos. Falta que Tony cambie las 7 contraseñas desde el panel (ver "Registro de sesiones") |
| 3 | Archivos expuestos por web y en git | Hecho |
| 4 | IDOR en facturas | Hecho |
| 5 | Fijación de sesión, cookies, expiración por inactividad | Hecho |
| 6 | CSRF en acciones por GET | Hecho (queda `logout` por GET) |
| 7 | Cantidades negativas y sobreventa | Hecho |
| 8 | Secretos en `config.php` (MySQL root sin contraseña, HMAC_KEY, clave de EmailJS) | Pendiente |
| 9 | Bloqueo permanente de cuentas y enumeración de usuarios | Hecho en el login |
| 10 | Escalada de privilegios por roles | Hecho |
| 11 | Errores visibles en pantalla (`display_errors`, `die()` con mensaje) | Hecho en el código (121afda). Falta el ajuste de despliegue en `php.ini` y Apache (ver "Antes de publicar") |
| 12 | Subida de archivos (`ImageHelper.php`) | Hecho (ver "Registro de sesiones"). Queda la fila inactiva de la parte de prueba 54 para la limpieza de datos de prueba |
| 13 | `HerramientasController::limpiarPrueba` borra todo; no debe existir en producción | Hecho (módulo Herramientas eliminado) |
| 14 | Política de contraseñas (8 a 12 caracteres, sin recuperación) | Pendiente |
| 15 | Inyección de fórmulas en Excel (`ReporteController`) | Hecho (ver "Registro de sesiones") |
| 16 | Cabeceras de seguridad (CSP, X-Frame-Options, nosniff, HSTS), HTTPS, SRI en CDN | Pendiente |
| 17 | Escapado en la entrada (`Sanitizer`) en vez de en la salida | Pendiente |

## Cosas que Claude anotó y no tocó
- Impedir que alguien sin ser Administrador edite a un Administrador.
- Impedir que un administrador se quite su propio rol o se desactive.
- `doLogin()` solo manda a `/admin/` a quien tenga rol Administrador, Vendedor o Consultor; un rol personalizado va a `/`.
- El registro y la compra de invitado siguen diciendo "Ya existe una cuenta con ese correo" (enumeración).
- Si `registrar()` falla después de descontar stock en compra simple, el stock se pierde.
- Ni `confirmarCompra` ni `procesarCarrito` revisan si la pieza está activa.
- El token CSRF es de un solo uso: volver atrás y repetir una acción da "Token CSRF inválido".
- El enlace de factura del correo ahora exige sesión y después del login no vuelve a la factura.
- Desactivar usuarios nunca tuvo diálogo de confirmación (podría añadirse).
- `BaseController::ip()` usa solo `REMOTE_ADDR`. Detrás de un proxy o Cloudflare, todos compartirían contador de intentos.
- SQL dinámico latente en `BaseModel` y `UserModel` (hoy no explotable), despacho dinámico de métodos en `admin/index.php`, reseñas públicas sin límite, precio congelado en el carrito, firmas que no se pueden reverificar.
- Ejecutar `composer audit` (tcpdf 6.11.3, phpspreadsheet 3.10.6).
- `app/Utils/objImagenes.php` es código muerto (nadie hace `new objImagenes`; solo se carga en `bootstrap.php`) y su comentario dice falsamente que lo usa `ParteController`. Se puede borrar aparte.
- `ParteModel::softDelete` ("eliminar" parte) borra las imágenes del disco pero la fila conserva los nombres: los pedidos antiguos (`PedidoModel` une `pa.thumbnail`) muestran imagen rota.
- Par de imágenes huérfano en `uploads/` (ninguna parte lo usa): `parts/parte_6a4a000fe47d65.17283908.jpg` y `thumbnails/parte_6a4a000fe47d65.17283908_th.jpg`. No se borró; solo con OK de Tony.
- `logs/error.log` crece sin límite (no se rota solo): revisarlo, rotarlo o vaciarlo de vez en cuando. Para seguirlo en vivo: `Get-Content C:\wamp64\www\rastroinventario\logs\error.log -Wait -Tail 20`. Para buscar un código de referencia: `Select-String -Path logs\error.log -Pattern "A3F9C2"`.
- Xdebug (de Wamp) añade líneas "Stack trace" al registro de errores. Solo van al archivo, no a pantalla.
- `Sanitizer::get()` con un parámetro tipo arreglo (`?action[]=x`) emite el warning "Array to string conversion" (solo al registro, no a pantalla). Conviene que el `Sanitizer` ignore los arreglos.

## Llave RSA y secretos
- La llave y el certificado se regeneraron. La passphrase vive solo en `rsa_secret.php`, que no está en git.
- Las facturas firmadas antes de la regeneración no se verifican con el certificado nuevo.
- El historial de git todavía contiene la llave anterior, el volcado `backup.sql`, `logs/emailjs.log` y los valores de `config.php`.
- Para producción hay que generar todo nuevo, sin reutilizar nada del historial: contraseñas de usuarios, HMAC_KEY, clave de EmailJS, llave RSA y credenciales de MySQL.

## Antes de publicar el sistema de verdad
- [ ] Hallazgos pendientes (8, 14, 16, 17; el 11 solo falta en el despliegue; el 2 solo falta cambiar las contraseñas) y las notas de la sección anterior.
- [ ] `upload_max_filesize = 5M` ya está en `phpForApache.ini` (cambiado por Tony el 2026-10-23); en producción hay que repetirlo, con `post_max_size` mayor o igual. El formulario y `ImageHelper::MAX_SIZE` dicen 5 MB; el mensaje de error usa el menor entre ese valor y el de PHP.
- [ ] Mover los secretos a `config.local.php`, ignorado por git.
- [ ] Usuario de MySQL propio con contraseña (hoy es `root` sin contraseña).
- [ ] HTTPS y `APP_URL` con `https://`. Cabeceras de seguridad.
- [ ] Despliegue de errores y versiones (el código ya apaga `display_errors` y quita `X-Powered-By`; esto cubre lo que solo se cambia en el servidor). No se ha tocado nada de esto:
  - `phpForApache.ini` (el que usa Apache de Wamp; `bin\apache\apache2.4.65\bin\php.ini` es un enlace a `C:\wamp64\bin\php\php8.3.28\phpForApache.ini`): `display_errors=Off`, `display_startup_errors=Off`, `expose_php=Off`, `zend.exception_ignore_args=On`, `log_errors=On`, `error_log` a una ruta fuera de la raíz web. Hoy están en `On`, `On`, `On`, `Off`, `On` y `c:/wamp64/logs/php_error.log`. El `php.ini` de CLI (mismo directorio) tiene los mismos valores.
  - `expose_php` solo se cambia en `php.ini`; los errores de sintaxis en `bootstrap.php` o en el archivo de entrada y los errores de arranque de PHP también dependen de él.
  - `httpd.conf` líneas 26 y 27: `ServerTokens Prod` y `ServerSignature Off` (hoy `Full` y `On`; `extra/httpd-default.conf` línea 55 también dice `Full`, pero su `Include` está comentado en la línea 582).
  - Reiniciar Apache y comprobar con `curl -I`: sin `X-Powered-By` y `Server: Apache` sin versiones. Una página 403 o 404 de Apache no debe llevar la firma "Apache/... Server at...".
- [x] Quitar o proteger `limpiarPrueba` (módulo Herramientas eliminado, 2026-10-23).
- [ ] Recuperación de contraseña y política más sólida.
- [ ] Revisar `REMOTE_ADDR` si hay proxy.
- [ ] Mejorar el diseño visual (colores, tipografía, formularios, móvil). Se decidió hacerlo después de la seguridad.

## Registro de sesiones
- 2026-10-22 y 2026-10-23: auditoría de seguridad, etapas 1, 2A y 2B completas (ver tabla de commits).
- 2026-10-23: verificación. Conteos de base revisados (solo `login_logs` difería, por 3 logins legítimos del administrador). Pruebas en el navegador integrado todas OK: carrito, factura PDF del pedido 1, login del administrador y compra normal (pedido 18, usuario 36, factura PDF descargable). Los commits 2B ya están en GitHub. Un solo commit de ESTADO.md, sin push.
- 2026-10-23: hallazgo 13. Se eliminó el módulo Herramientas completo (opción A): borrados `HerramientasController.php` y `app/Views/admin/herramientas/index.php`, quitados el `require_once` de `bootstrap.php` y la entrada del mapa en `admin/index.php`. `php -l` OK, 0 referencias restantes en el código, conteos de la base sin cambios (17, 7, 4, 24). Navegador con el administrador (sesión iniciada por Tony): `/admin/` carga el dashboard y `?mod=herramientas` redirige a `/admin/`. No se hizo POST a `limpiarPrueba`. Commit fca464f, sin push.
- 2026-10-23: Error 2 (stock y ventas por estado). Reglas: el stock queda apartado mientras el pedido no esté cancelado; cancelar lo devuelve, reabrir (cancelado a pendiente o procesando) lo descuenta de nuevo y se rechaza si no alcanza; Entregado es final y registra las ventas una sola vez; cancelado a entregado bloqueado. Todo en una transacción con bloqueo de fila (`SELECT ... FOR UPDATE`) y `UPDATE ... WHERE estado=anterior`. Archivos: `ParteModel.php` (`incrementarStock`), `PedidoModel.php` (`cambiarEstado`, `transicionesPermitidas`; se quitó `updateEstado`), `PedidoAdminController.php`, `admin/pedidos/detalle.php` (solo ofrece estados permitidos y pide confirmación). Sin cambio de esquema. SELECT previos (solo lectura): 0 pedidos cancelados y 0 ventas duplicadas en `vendido_parte` (6 filas). Respaldo: `C:\wamp64\respaldo_rastro\rastro_pre_estado_20261023_112417.sql`. Pruebas con arnés CLI dentro de una transacción con ROLLBACK sobre el pedido 18: 13 de 13 OK; la base quedó igual (usuarios 17, pedidos 7, vendido_parte 6). El arnés encontró un fallo en los mensajes (`«$nuevo»` se leía como variable) y se corrigió. Navegador (administrador, pedido 18, nunca Entregado): pendiente a procesando (stock 38), a cancelado (stock 39, "Stock devuelto de 1 pieza(s)"), a pendiente (stock 38, "El stock se descontó de nuevo"). El selector de un cancelado no ofrece Entregado. Estado final igual al inicial: pedido 18 pendiente, stock de la pieza 39 en 38, usuarios 17, pedidos 7, vendido_parte 6. El navegador integrado cancela `confirm()`, así que se aceptó el aviso desde la página. Después: el `catch` de `cambiarEstado` ahora registra el fallo con `error_log("[RASTRO] cambiarEstado: ...")` (`php -l` OK). Detalle del pedido 1 (entregado) solo mirado en el navegador: carga sin errores ni mensajes en consola, sin formulario ni selector de estado, y muestra "Este pedido ya fue entregado y no se puede cambiar."
- 2026-10-23: Error 1 (ITBMS), opción (b). Decisiones de Tony: precios publicados SIN impuesto; ITBMS 7 % sumado en el carrito y guardado en el pedido, sin columna nueva (itbms = total - subtotal); redondeo una sola vez sobre el subtotal del pedido; "Total vendido" del dashboard en neto (`SUM(subtotal)`); correo solo con el total corregido, sin tocar la plantilla de EmailJS. Archivos: nuevo `app/Utils/Itbms.php` (`calcular` y `dePedido`) y su `require_once` en `bootstrap.php`; `PedidoModel.php` (`registrar` guarda total con ITBMS, `totalVendido` neto); `PedidoController.php` (carrito con subtotal/ITBMS/total y los 3 correos usan el total guardado del pedido); vistas `carrito.php`, `compra.php`, `compra_confirmar.php` (aviso "sin ITBMS"), `compra_exitosa.php`, `admin/pedidos/detalle.php`; `FacturaController.php` (lee subtotal y total del pedido). `mis_pedidos.php` y `admin/pedidos/index.php` no cambian: ya muestran `total`. Respaldo previo: `C:\wamp64\respaldo_rastro\rastro_pre_itbms_20261023_114035.sql`. Pruebas: `php -l` OK en todo lo tocado; `Itbms::calcular` por CLI 13/13 OK; compra en el navegador (pedido 19, usuario 37): carrito, compra exitosa, Mis pedidos y factura PDF coinciden en 65.00 / 4.55 / 69.55; el total que recibe el correo es 69.55 (EmailJS falla localmente con "SSL certificate problem", no se envió nada). Backfill aprobado por Tony y ejecutado: `UPDATE pedidos SET total = ROUND(subtotal + ROUND(subtotal * 0.07, 2), 2) WHERE id IN (1,2,3,4,5,6,18) AND total = subtotal;` con 7 filas afectadas (totales 16.05, 37.45, 278.20, 48.15, 117.70, 1926.00 y 37.45; el pedido 19 ya traía 69.55). Conteos sin cambio: usuarios 18, pedidos 8, vendido_parte 6. Admin revisado con la sesión del administrador: la lista muestra los totales con ITBMS (#19 69.55, #18 37.45, #6 1926.00, #5 117.70, #4 48.15, #3 278.20, #2 37.45, #1 16.05); el detalle de los pedidos 19, 18, 1 y 6 muestra subtotal, ITBMS y total correctos sin errores; la factura del admin de los pedidos 1 y 19 sale con 15.00 / 1.05 / 16.05 y 65.00 / 4.55 / 69.55; el dashboard muestra Total vendido $2,365.00 (neto). Error 1 listo, a la espera de la orden de commit de Tony. Sin commit, sin push.
- 2026-10-23: segunda verificación del hallazgo 13 (después de los commits fca464f y a67ad9f). Navegador con la sesión del administrador: `/admin/` carga el dashboard y `?mod=herramientas` redirige a `/admin/`. Conteos con `SELECT COUNT(*)`: usuarios 17, pedidos 7, roles 4 (iguales a antes) y `login_logs` 26 (+2 frente a los 24 anteriores: ids 61 y 62, ambos logins exitosos del administrador desde `::1`, a las 09:48 y 09:52). No se hizo POST a `limpiarPrueba`.
- 2026-10-23: hallazgo 11 (errores visibles en pantalla). Decisiones de Tony: seguir el registro en vez de un modo desarrollo (opción A), guardia anti bucle incluida, despliegue solo documentado (`php.ini` y `httpd.conf` no se tocaron). Archivos: `bootstrap.php` (apaga `display_errors`, `display_startup_errors` y `html_errors`; `log_errors` y `error_log` a `logs/error.log`; `zend.exception_ignore_args`; `header_remove("X-Powered-By")`; funciones `registrarError()` y `paginaError()`; `set_exception_handler` y cierre para fatales), `BaseController.php` (`handle()` muestra mensaje genérico con código de referencia de 6 caracteres hexadecimales), `Database.php` (el `die()` con el mensaje de MySQL pasa a página genérica 503), `index.php` y `admin/index.php` (si falla la portada o el dashboard muestran la página de error en vez de redirigir en bucle), `FacturaController.php` y `ReporteController.php` (el aviso de `composer install` va solo al registro). Sin cambio de `config.php`, de datos ni de esquema. Pruebas (todas automatizadas): CLI 18/18; servidor temporal con el `php.ini` de Apache (`display_errors=On`) con excepción, warning, fatal, base inexistente y POST al `login.php` real, sin texto interno en ninguna respuesta; copias del `index.php` y `admin/index.php` reales con un controlador que falla (acción distinta de home: redirige con aviso genérico; home y dashboard: página de error 500, sin bucle); Apache real con entradas malformadas (el warning real de `?action[]=x` solo llegó al registro); navegador con la sesión del administrador: 27 URLs (admin, públicas, 2 PDF, 4 Excel) sin fugas y sin `X-Powered-By`. Límite: no se puede provocar un fallo real dentro de Apache sin meter código de falla en el proyecto. Conteos con `SELECT COUNT(*)`: usuarios 18, pedidos 8, roles 4, login_logs 29, vendido_parte 6. Sin push.
- 2026-10-23: hallazgo 15 (inyección de fórmulas en Excel). Solo `ReporteController` genera Excel; no hay CSV en el proyecto. Se añadió el método privado `texto()` (escribe con `setCellValueExplicit` como cadena y pone `quotePrefix` si el texto empieza con `= + - @` tab o CR) y se usa en las 9 celdas de texto de la base: inventario B, C, D, E; ventas por mes B; categorías A; más vendidas B, C, D. Números, cabeceras y totales no cambian (no hay fórmulas legítimas del sistema). Hallazgo extra confirmado con PhpSpreadsheet 3.10.6: con `setCellValue`, un texto como `==` o `=SUM(` hacía fallar el `save()` y tumbaba el reporte entero; con el cambio ya no. Solo `=` llega a ser fórmula real en el .xlsx; `+ - @` quedan como texto, pero llevan comilla de texto por si se edita la celda o se pasa a CSV. Sin cambio de datos, esquema ni `bootstrap.php`. Pruebas: `php -l` OK; arnés CLI con filas falsas sobre el `ReporteController` real (19 textos peligrosos, nulos y vacíos), 4 de 4 OK: 0 elementos `<f>`, textos idénticos al leerlos de vuelta, comilla de texto en los peligrosos, números siguen siendo números y el total del inventario coincide; el mismo arnés contra el código anterior falla (fórmula en ventas por mes y excepción en los otros 3). Navegador con la sesión del administrador (descarga por `fetch`, sin guardar archivos): inventario (todas y sección 1), ventas por mes, categorías y más vendidas dan 200, tipo xlsx, firma `PK`, `[Content_Types].xml` y `sheet1.xml` presentes, 0 fórmulas, sin texto de error de PHP y sin errores en consola. Conteos con `SELECT COUNT(*)`: usuarios 18, pedidos 8, roles 4, login_logs 29, vendido_parte 6 (sin cambios). Límite: no se abrió con Excel real; la comprobación es sobre el XML y la lectura con PhpSpreadsheet. Sin commit, sin push.
- 2026-10-23: hallazgo 2 (contraseñas de prueba en la documentación). Se reemplazaron por el texto "se define al instalar; pídela al administrador" en `README.md` (líneas 74 a 76, tabla de roles) y `INSTRUCCIONES.md` (líneas 42 a 44); correos y roles intactos. La búsqueda por los 3 valores en todo el proyecto (sin `vendor/`, `uploads/`, `backup.sql`, `config.php`, `rsa_secret.php` ni `rastro.key`) da 0 coincidencias; tampoco hay contraseñas literales en el código. `README.md` línea 55 dice que la contraseña de MySQL es "(vacía)": es el dato de la base, ya anotado en el hallazgo 8. Capturas de `docs/screenshots/` (solo revisadas, no editadas): ninguna muestra contraseñas; sí muestran correos reales (`tonybusinesspty@gmail.com` en 02 y 05, `chapimaster507@gmail.com` y teléfono en el pie de 01 y 07), IP `::1`, y en 05 nombre, teléfono, dirección y notas de un cliente. Comprobación con un script fuera del proyecto (`password_verify`, solo SI/NO; el script se borró): las 7 cuentas documentadas (admin, vendedor1 a 3, consultor1 a 3) SIGUEN con la contraseña que decía el README. No se cambió ninguna contraseña. Sin push.
- 2026-10-23: hallazgo 12 (subida de archivos). Único punto de subida: `ParteController::crear/editar` (campo `imagen` de `admin/partes/form.php`); solo el rol Administrador tiene crear/editar/borrar en `partes`. Antes: se fiaba de la extensión si `mime_content_type` fallaba, guardaba el original tal cual, copiaba archivos inválidos como miniatura, sin límite de píxeles, `delete()` sin validar, y `uploads/.htaccess` no cubría `x.php.jpg` (el Apache de Wamp tiene `AddHandler ... .php`: se comprobó que ese nombre SÍ se ejecutaba). Ahora, archivos: `app/Utils/ImageHelper.php` (reescrito: `getimagesize` + `finfo` por contenido, lados máx. 6000 px y 16 MP, exige JPEG/GIF completos, decodifica con GD y re-codifica original y miniatura 420x315, respeta la orientación EXIF, nombre aleatorio del servidor, sin fallbacks, falla cerrado con `ImageHelper::$error`; `MAX_SIZE` 5 MB y límite real = menor entre eso y `upload_max_filesize`; `delete()` solo acepta nombres simples de imagen), `ParteController.php` (muestra el motivo del rechazo; en `editar` no guarda nada si la imagen falla y borra la anterior solo después de guardar), `form.php` (texto "máximo 5 MB") y `uploads/.htaccess` (sin ejecución, lista blanca de imágenes, `nosniff`). Sin cambio de esquema ni de las imágenes existentes. Auditoría de las 100 imágenes existentes: todas reales, ninguna se tocó (1 falso positivo de `<?=` en bytes comprimidos). Pruebas: script CLI con el `php.ini` de Apache, 26 de 26 OK (rechaza `.php`/HTML/SVG disfrazados, SVG real, vacío, JPEG/PNG/GIF/WebP truncados, 20000x20000, 25 MP, más de 5 MB; acepta jpg/png con alfa/gif/webp reales, 16 MP justo, foto de 12 MP con EXIF girada (pico 96 MB de 128 MB), 3,3 MB; PHP pegado al final o en chunk PNG se acepta como imagen pero la salida queda limpia; el nombre `../../x.php.jpg` no se usa; `delete()` con rutas deja intacto un archivo canario). Mismo script contra el código viejo: aceptaba 22 de 24, el 20000x20000 lo tumbaba por memoria y `delete("../..")` borraba el canario. Apache real: sonda por HTTP (GD, WebP, finfo y exif activos; `upload_max_filesize` 5M tras el cambio de Tony); `x.php.jpg`, `.php`, `.svg`, `.html` y el listado dan 403, las imágenes reales 200 con `X-Content-Type-Options: nosniff`. Navegador integrado (administrador): 8 rechazos por el formulario real (sin crear datos, mensajes correctos, incluido "supera el máximo de 5 MB" con 6 MB); alta de imagen real 800x600 (parte 54; `is_uploaded_file` funciona en este Wamp); editar con imágenes de 1,8 MB, 1,6 MB y 3,3 MB: cada reemplazo borró la imagen anterior y quedó un solo par de archivos; miniatura 315x315 y original 1000x1000 cargan, aparecen en la lista y en el detalle público. Conteos: usuarios 18, pedidos 8, roles 4, vendido_parte 6, partes 50 (la 54 es de prueba), login_logs 30 (ids 65 y 66, logins del administrador desde `::1`). Sin push. Límites: el GIF animado queda en su primer cuadro; fotos de más de 16 MP se rechazan; solo se probó con el navegador integrado. Sin commit todavía.
