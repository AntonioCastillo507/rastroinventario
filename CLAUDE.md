# Rastroinventario (Rastro del Istmo)

Inventario y tienda de repuestos de autos usados. Al empezar, lee ESTADO.md (es la memoria del proyecto). Al terminar cada tarea, actualízalo y avísame.

## Proyecto
- PHP con estructura MVC en WampServer (Apache + MySQL 8.4 en el puerto 3306) sobre Windows. URL local: http://localhost/rastroinventario
- Entrada: bootstrap.php; configuración en app/Config/config.php. Las clases de app/ las carga el autoload de Composer (classmap): tras añadir una clase, `composer dump-autoload`; tras clonar, `composer install`.
- Carpetas: app/ (Controllers, Models, Utils, Views, Config), admin/ (panel), raíz con las entradas públicas (index, login, registro, olvide, restablecer, logout), database/, tests/, logs/, uploads/.
- Ya no existen setup.php ni setupfix4.php. La base se crea con database/ (ver abajo).
- database/: schema.sql (solo estructura), seed_basico.sql (roles y permisos), demo.sql (datos de ejemplo), crear_usuario.php (crea el administrador; la clave la teclea quien lo usa), instalar.ps1 (crea una base desde cero) y migraciones/ (cambios de esquema con aplicar.ps1).
- Bases: `rastroinventario` (la real) y `rastroinventario_test` (solo para pruebas). Cliente MySQL: C:\wamp64\bin\mysql\mysql8.4.7\bin\mysql.exe -u root -h 127.0.0.1 -P 3306.

## Pruebas
- Las pruebas las hace Claude, de forma automatizada. Única excepción: la contraseña del administrador la teclea solo el usuario; Claude nunca la escribe y, si hace falta sesión de administrador y no la hay, para y avisa.
- `composer test` corre PHPUnit (Unit + Integration) contra rastroinventario_test; también `composer test:unit`, `composer test:integration`, `composer test:volumen` (datos de volumen para ver paginación) y `composer test:reset` (recrea rastroinventario_test; hace falta si cambia schema.sql).
- Antes y después de cada cambio de código, `composer test` tiene que salir verde. Si se pone en rojo, parar y avisar.
- Toda función nueva lleva pruebas nuevas; para comprobar que detectan fallos se hacen mutaciones sobre una copia temporal (sin rsa_secret.php ni ssl/), nunca sobre el código real.
- Lo que un navegador no puede cubrir (por ejemplo los `confirm()`, o formularios cuyo `action` apunta a APP_URL) se resuelve por script o con un servidor temporal `php -S` contra rastroinventario_test, y se dice exactamente qué no se cubrió.
- Pruebas con correos @example.invalid; EmailJS no envía a ese dominio.

## Base de datos
- La real (`rastroinventario`) solo recibe SELECT hasta que el usuario dé un OK explícito. Antes de cualquier cambio de datos o de esquema: respaldo en C:\wamp64\respaldo_rastro\ (nunca dentro del proyecto) y ensayo en rastroinventario_test.
- Cambio de esquema: editar database/schema.sql, añadir la migración en database/migraciones/ y aplicarla con aplicar.ps1 -Confirmar (hace el respaldo antes). Después, `composer test:reset`.

## Seguridad
- Nunca leer, mostrar ni hacer grep en app/Config/config.php, app/Config/rsa_secret.php ni ssl/rastro.key. Si se copian carpetas, excluir rsa_secret.php y ssl/. No escribir contraseñas, llaves ni tokens en ningún archivo ni respuesta.
- SQL siempre con parámetros; salida siempre con htmlspecialchars; acciones que cambian datos solo por POST con CsrfToken; errores internos solo a logs/error.log.

## Forma de trabajar
- Trabajar en la rama mejoras, nunca directo en main.
- Un commit por tarea, solo cuando el usuario lo ordene (o lo autorice de forma explícita), con `git add` por ruta. Nunca `git push` sin que el usuario lo pida.
- Cambios pequeños y explicar qué archivos se tocaron. Código completo y listo para usar, comentarios cortos y naturales.
- Responder siempre en español, de forma directa.
