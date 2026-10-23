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
| fca464f | Hallazgo 13: se elimina el módulo Herramientas (`HerramientasController`, su vista, el `require_once` en `bootstrap.php` y la entrada en `admin/index.php`) | No |

## Pendiente inmediato
- [x] Limpieza de datos de prueba confirmada (2026-10-23). Línea base antes de las pruebas del navegador: 16 usuarios, 6 pedidos, 4 roles y 23 filas de `login_logs` (las 3 filas extra sobre las 20 esperadas son logins exitosos del administrador desde `::1`, ids 57 a 59).
- [x] Pruebas en el navegador (2026-10-23): carrito (añadir, cambiar cantidad con Enter, quitar), login del administrador, "Ver Factura PDF" del pedido 1 y compra normal. Todo OK.
- [x] `git push` de los commits 2B (cda46c5 a 4711641): hecho, `origin/mejoras` los contiene.
- [ ] Decidir qué hacer con los datos de prueba de la compra 2d: pedido 18 y usuario 36 (`prueba2d@example.invalid`). No borrar sin respaldo, ids exactos y OK de Tony.
- [ ] Hacer copia segura de `ssl/rastro.key` y `app/Config/rsa_secret.php` fuera del proyecto (gestor de contraseñas o USB). Sin la passphrase la llave no sirve.
- [ ] Borrar los volcados `rastro_pre2B_*.sql` y `rastro_pre2d_*.sql` de `C:\wamp64\respaldo_rastro\` cuando todo esté verificado.

## Conteos actuales de la base (2026-10-23, después de las pruebas)
| Tabla | Conteo |
|---|---|
| usuarios | 17 |
| pedidos | 7 |
| roles | 4 |
| login_logs | 24 |

- Diferencia contra la línea base (16, 6, 4, 23): +1 usuario y +1 pedido de la compra de prueba 2d, y +1 login del administrador (id 60).
- Datos de prueba creados en 2d: pedido id 18 (1 pieza, Bujías NGK, estado pendiente) y usuario id 36 (`prueba2d@example.invalid`). Los ids son altos porque el auto_increment siguió subiendo con las pruebas anteriores.
- Respaldo previo a 2d: `C:\wamp64\respaldo_rastro\rastro_pre2d_20261023_093549.sql`.
- Al cerrar esas pruebas se cerró la sesión del administrador en el navegador integrado.
- El navegador integrado cancela los diálogos `confirm()`. Para probar "quitar del carrito" hubo que aceptar el aviso desde la página; no es un fallo de la app.

## Hallazgos de la auditoría
| # | Tema | Estado |
|---|---|---|
| 1 | Compra de invitado inicia sesión en cuentas existentes | Hecho |
| 2 | Contraseñas de prueba escritas en README.md e INSTRUCCIONES.md | Pendiente |
| 3 | Archivos expuestos por web y en git | Hecho |
| 4 | IDOR en facturas | Hecho |
| 5 | Fijación de sesión, cookies, expiración por inactividad | Hecho |
| 6 | CSRF en acciones por GET | Hecho (queda `logout` por GET) |
| 7 | Cantidades negativas y sobreventa | Hecho |
| 8 | Secretos en `config.php` (MySQL root sin contraseña, HMAC_KEY, clave de EmailJS) | Pendiente |
| 9 | Bloqueo permanente de cuentas y enumeración de usuarios | Hecho en el login |
| 10 | Escalada de privilegios por roles | Hecho |
| 11 | Errores visibles en pantalla (`display_errors`, `die()` con mensaje) | Pendiente |
| 12 | Subida de archivos (`ImageHelper.php`) | Pendiente |
| 13 | `HerramientasController::limpiarPrueba` borra todo; no debe existir en producción | Hecho (módulo Herramientas eliminado) |
| 14 | Política de contraseñas (8 a 12 caracteres, sin recuperación) | Pendiente |
| 15 | Inyección de fórmulas en Excel (`ReporteController`) | Pendiente |
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

## Llave RSA y secretos
- La llave y el certificado se regeneraron. La passphrase vive solo en `rsa_secret.php`, que no está en git.
- Las facturas firmadas antes de la regeneración no se verifican con el certificado nuevo.
- El historial de git todavía contiene la llave anterior, el volcado `backup.sql`, `logs/emailjs.log` y los valores de `config.php`.
- Para producción hay que generar todo nuevo, sin reutilizar nada del historial: contraseñas de usuarios, HMAC_KEY, clave de EmailJS, llave RSA y credenciales de MySQL.

## Antes de publicar el sistema de verdad
- [ ] Hallazgos pendientes (2, 8, 11, 12, 14 al 17) y las notas de la sección anterior.
- [ ] Mover los secretos a `config.local.php`, ignorado por git.
- [ ] Usuario de MySQL propio con contraseña (hoy es `root` sin contraseña).
- [ ] HTTPS y `APP_URL` con `https://`. Cabeceras de seguridad.
- [ ] `display_errors` apagado y `expose_php` apagado.
- [x] Quitar o proteger `limpiarPrueba` (módulo Herramientas eliminado, 2026-10-23).
- [ ] Recuperación de contraseña y política más sólida.
- [ ] Revisar `REMOTE_ADDR` si hay proxy.
- [ ] Mejorar el diseño visual (colores, tipografía, formularios, móvil). Se decidió hacerlo después de la seguridad.

## Registro de sesiones
- 2026-10-22 y 2026-10-23: auditoría de seguridad, etapas 1, 2A y 2B completas (ver tabla de commits).
- 2026-10-23: verificación. Conteos de base revisados (solo `login_logs` difería, por 3 logins legítimos del administrador). Pruebas en el navegador integrado todas OK: carrito, factura PDF del pedido 1, login del administrador y compra normal (pedido 18, usuario 36, factura PDF descargable). Los commits 2B ya están en GitHub. Un solo commit de ESTADO.md, sin push.
- 2026-10-23: hallazgo 13. Se eliminó el módulo Herramientas completo (opción A): borrados `HerramientasController.php` y `app/Views/admin/herramientas/index.php`, quitados el `require_once` de `bootstrap.php` y la entrada del mapa en `admin/index.php`. `php -l` OK, 0 referencias restantes en el código, conteos de la base sin cambios (17, 7, 4, 24). Navegador con el administrador (sesión iniciada por Tony): `/admin/` carga el dashboard y `?mod=herramientas` redirige a `/admin/`. No se hizo POST a `limpiarPrueba`. Commit fca464f, sin push.
- 2026-10-23: segunda verificación del hallazgo 13 (después de los commits fca464f y a67ad9f). Navegador con la sesión del administrador: `/admin/` carga el dashboard y `?mod=herramientas` redirige a `/admin/`. Conteos con `SELECT COUNT(*)`: usuarios 17, pedidos 7, roles 4 (iguales a antes) y `login_logs` 26 (+2 frente a los 24 anteriores; solo cuenta de inicios de sesión, no se revisó el detalle de las filas). No se hizo POST a `limpiarPrueba`.
