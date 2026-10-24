# Rastro del Istmo — Sistema de Inventario y Venta de Repuestos de Autos Usados

> Universidad Tecnológica de Panamá — Facultad de Ingeniería en Sistemas Computacionales
> Desarrollo de Software VII — Grupo 1GS131 — I Semestre 2026
> Proyecto asignado: *Sistema de Inventario o Rastro*

---

## 1. Información General y Evidencia Práctica

### 1.1. Nombre del Proyecto
**Rastro del Istmo** — Sistema de Inventario y Venta de Repuestos de Autos Usados.

### 1.2. Integrantes del Equipo

| Nombre | Cédula | Rol en el desarrollo |
|---|---|---|
| Antonio Castillo | 🔲 *8-1038-2499* | 🔲 *Líder de Proyecto — Arquitectura backend (MVC, modelos, controladores), seguridad (firmas digitales, CSRF, control de acceso) y base de datos. Frontend — Vistas, CSS y diseño de interfaz (panel admin + tienda pública)* |
| Ian Torres | 🔲 *20-53-8265* | 🔲 *Carga de Datos de Prueba — ayudar a poblar el inventario semilla (las partes de autos, imágenes de prueba)* |
| Jamir Montenegro | 🔲 *8-1017-1078* | 🔲 *Documentación — README, diagramas UML, manual de usuario* |
| Samuel Elzaghir | 🔲 *20-70-8278* | 🔲 *Pruebas y QA — Casos de prueba, validación de formularios, revisión de bugs* |

### 1.3. Fecha del Sistema y Versión
- **Versión actual:** v1.6.0
- **Última actualización:** Julio 2026

### 1.4. 🎥 Demostración en Video
🎥 [Ver demostración en video](https://utpac-my.sharepoint.com/:v:/g/personal/antonio_castillo2_utp_ac_pa/IQB2OR8cAfv0QqIYSp6T83uPAXF06pk2A8d2YFLlOKJPgs8?e=f7uTyi)

---

## 2. Requisitos de Infraestructura

### 2.1. Entorno de Ejecución
- **PHP:** 8.1 o superior
- **Motor de base de datos:** MySQL 5.7+ / MariaDB 10.4+
- **Servidor local sugerido:** WampServer
- **Dependencias (Composer):** `phpoffice/phpspreadsheet` ^3.0 (reportes Excel), `tecnickcom/tcpdf` ^6.6 (facturas PDF firmadas)

### 2.2. Guía de Despliegue Rápido

**1. Clonar el repositorio:**
```bash
git clone https://github.com/AntonioCastillo507/rastroinventario.git
```

**2. Colocarlo en el servidor local:**
Copia la carpeta dentro de `C:\wamp64\www\` e inicia WampServer.

**3. Configurar la base de datos:**
La conexión (host, usuario, contraseña y nombre de la base) está en `app/Config/config.php`; por defecto trae la de WampServer (`root` sin contraseña, base `rastroinventario`). Si tu MySQL usa otros datos, cámbialos ahí.

**4. Instalar dependencias y crear la base:**
```bash
composer install
```
Sigue la sección "Instalación desde cero" de abajo para crear la base y el administrador.

### 2.3. Instalación desde cero (Windows / WampServer)

Todo está en la carpeta [`database/`](./database): `schema.sql` (solo estructura), `seed_basico.sql` (roles y permisos, lo mínimo para arrancar), `demo.sql` (datos inventados, opcional), `crear_usuario.php` y `instalar.ps1`. Ninguno lleva usuarios, contraseñas ni datos reales.

El MySQL 8.4 de Wamp escucha en el puerto 3306 (MariaDB, en el 3307, no sirve para esto). Abre PowerShell en la carpeta del proyecto (`C:\wamp64\www\rastroinventario`).

**1. Crear la base con el script** (crea la base, carga `schema.sql` y `seed_basico.sql`):
```powershell
.\database\instalar.ps1 -Base rastroinventario
```
Si PowerShell bloquea el script: `powershell -ExecutionPolicy Bypass -File .\database\instalar.ps1 -Base rastroinventario`.

**1 (alternativa a mano), sin el script:**
```powershell
C:\wamp64\bin\mysql\mysql8.4.7\bin\mysql.exe -u root -h 127.0.0.1 -P 3306 -e "CREATE DATABASE rastroinventario CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```
```powershell
C:\wamp64\bin\mysql\mysql8.4.7\bin\mysql.exe -u root -h 127.0.0.1 -P 3306 --default-character-set=utf8mb4 rastroinventario -e "source database/schema.sql"
```
```powershell
C:\wamp64\bin\mysql\mysql8.4.7\bin\mysql.exe -u root -h 127.0.0.1 -P 3306 --default-character-set=utf8mb4 rastroinventario -e "source database/seed_basico.sql"
```

**2. Crear el administrador** (pide correo, nombre y la contraseña dos veces; la contraseña no se muestra ni se guarda en ningún archivo, y debe cumplir la política: mínimo 8 caracteres, máximo 72 bytes, no común, distinta del correo y del nombre):
```powershell
C:\wamp64\bin\php\php8.3.28\php.exe database\crear_usuario.php
```
Para otro rol: `--rol=Vendedor` (o `Consultor`). Para cambiar la clave de un usuario que ya existe: `--actualizar`.

**3. Datos de demostración (opcional, solo en una base recién creada):** cargan autos, secciones, 12 partes sin imagen, 3 usuarios `@example.invalid` (sin contraseña utilizable) y 3 pedidos falsos. Con el script, añade `-Demo` al paso 1; a mano:
```powershell
C:\wamp64\bin\mysql\mysql8.4.7\bin\mysql.exe -u root -h 127.0.0.1 -P 3306 --default-character-set=utf8mb4 rastroinventario -e "source database/demo.sql"
```

**4. Base de pruebas para tests automáticos** (`rastroinventario_test`, sin datos de demostración; el script puede borrarla y recrearla con `-Recrear`, y solo con ese nombre o `rastroinventario_check`):
```powershell
.\database\instalar.ps1 -Base rastroinventario_test -Recrear
```
Para que el código use esa base, define la variable de entorno antes de lanzar PHP por consola o con `php -S` (Apache la ignora, y solo se aceptan esos dos nombres):
```powershell
$env:RASTRO_DB_NAME = "rastroinventario_test"
```

Los secretos de producción (`config.php`, llave RSA, `HMAC_KEY`, credenciales de MySQL) no se crean aquí; ver `ESTADO.md`.

### 2.4. Cómo correr las pruebas (Windows / WampServer)

Las pruebas son PHPUnit y viven en `tests/` (`Unit` no escribe datos; `Integration` usa la base `rastroinventario_test`). Necesitan PHP 8.2 o más (el de Wamp, 8.3), MySQL encendido en el puerto 3306 y que exista la base de pruebas. Abre PowerShell en `C:\wamp64\www\rastroinventario`.

**Una sola vez:** instala las dependencias (incluye PHPUnit) y crea la base de pruebas.
```powershell
composer install
```
```powershell
.\database\instalar.ps1 -Base rastroinventario_test
```

**Correr todo:**
```powershell
composer test
```

Solo una parte:
```powershell
composer test:unit
```
```powershell
composer test:integration
```
Una sola prueba o clase (todo lo que va después de `--` se le pasa a PHPUnit):
```powershell
composer test -- --filter ItbmsTest
```

**Dejar la base de pruebas como nueva** (la borra y la recrea; solo funciona con `rastroinventario_test`):
```powershell
composer test:reset
```

Cómo se protege la base real: `tests/bootstrap.php` aborta, antes de conectarse, si `RASTRO_DB_NAME` no vale exactamente `rastroinventario_test` (por eso conviene usar siempre `composer test`, que la define), y vuelve a comprobar con `SELECT DATABASE()` que la conexión es esa. También se niega si la base tiene usuarios que no sean `@example.invalid` o si no está instalada. Cada prueba de integración corre dentro de una transacción que se deshace al terminar; las imágenes se generan al vuelo en una carpeta temporal y no se guarda nada en `uploads/`.

Notas: si cambia el esquema, regenera `database/schema.sql` y corre `composer test:reset`. Las pruebas de `LoginFlujoTest` ejecutan el controlador real en un proceso aparte y leen la cabecera `Location` solo si Xdebug está instalado (el `php.ini` de Wamp lo trae).

---

## 3. Matriz de Roles y Credenciales de Prueba

| Rol de Usuario | Usuario de Acceso | Contraseña de Prueba | Permisos / Qué puede hacer |
|---|---|---|---|
| Administrador | `admin@rastroinventario.com` | se define al instalar; pídela al administrador | Control total: usuarios, roles, inventario, ventas, secciones, reportes, auditoría de firmas |
| Vendedor | `vendedor1@rastroinventario.com` | se define al instalar; pídela al administrador | Ver inventario y registrar ventas |
| Consultor | `consultor1@rastroinventario.com` | se define al instalar; pídela al administrador | Solo consulta de inventario, sin editar ni vender |
| Cliente (tienda pública) | *(crear una cuenta nueva desde el registro público)* | — | Comprar, comentar y ver su historial de pedidos — **sin acceso al panel `/admin/`** (bloqueado a nivel de rol) |

---

## 4. Directrices Técnicas y Reglas del Backend

### 4.1. Control de Acceso Seguro
- Contraseñas de 8 caracteres como mínimo y 72 bytes como máximo, sin contraseñas comunes ni iguales al correo o al nombre → `app/Utils/Validator.php` (`password()`) y `app/Utils/contrasenas_comunes.php`
- Bloqueo automático al 3er intento fallido → `app/Models/UserModel.php` (`incrementIntentos`, `bloquear`)
- Auditoría de accesos (IP, fecha, éxito/fallo) → tabla `login_logs`, registrada desde `app/Controllers/AuthController.php` (`doLogin`)

### 4.2. Mitigación OWASP y DRY
- Token CSRF en todo formulario POST → `app/Utils/CsrfToken.php`
- Consultas 100% con PDO preparado (sin concatenar SQL) → `app/Config/Database.php` + todos los `Model`
- Sanitización y validación centralizadas → `app/Utils/Sanitizer.php`, `app/Utils/Validator.php`
- Manejo de errores centralizado por contrato → `app/Interfaces/ErrorHandlerInterface.php`, implementado en `app/Controllers/BaseController.php`, invocado desde `index.php` y `admin/index.php` (try/catch global)
- **Control de acceso roto (OWASP A01) mitigado**: `admin/index.php` valida, antes de despachar a cualquier módulo, que (1) haya sesión iniciada y (2) el usuario tenga al menos un permiso de staff en la tabla `permisos` — así una cuenta Cliente autenticada no puede ni siquiera cargar el Dashboard, y cada módulo sigue verificando su propio permiso puntual (ver/crear/editar/borrar) vía `UserModel::hasPermission()`

### 4.3. Sello de Integridad (Firma Digital)
Al registrar una venta (`vendido_parte`) o una parte de inventario, el backend calcula un HMAC-SHA256 sobre los datos críticos (precio, cantidad, usuario, fecha) con una clave secreta del servidor → `app/Utils/HmacCrypto.php` (implementa `CryptoInterface`), invocado desde `app/Utils/DigitalSignature.php`, y guardado en la columna `firma_digital`. Si alguien altera esos datos directo en la base de datos, la firma deja de coincidir y la manipulación queda expuesta.

---

## 5. Manual de Usuario Operativo (Flujo de Pantallas)

🎥 [Ver video de uso del sistema](https://utpac-my.sharepoint.com/:v:/g/personal/antonio_castillo2_utp_ac_pa/IQB2OR8cAfv0QqIYSp6T83uPAXF06pk2A8d2YFLlOKJPgs8?e=f7uTyi)

### 5.1. Guía Visual (resumen del flujo)
1. **Inicio** (`/rastroinventario/`) — la primera impresion del usuario al entrar

   ![Login](docs/screenshots/01-login.png)

2. **Dashboard** — resumen del inventario, menú horizontal con acceso a todos los módulos

   ![Dashboard](docs/screenshots/02-dashboard.png)

3. **Agregar** — Inventario → Nueva Parte → marca/modelo/año del auto, sección, precio, thumbnail + imagen grande

   ![Agregar parte](docs/screenshots/03-agregar-parte.png)

4. **Buscar** — Inventario → barra de búsqueda y filtros por marca, precio, sección

   ![Buscar](docs/screenshots/04-buscar.png)

5. **Modificar** — ícono de editar sobre cualquier parte o auto

   ![Modificar](docs/screenshots/05-modificar.png)

6. **Vender** — Inventario → Vender → registra en `vendido_parte` con firma digital

   ![Vender](docs/screenshots/06-vender.png)

7. **Catálogo público** (`/`) — listado con thumbnails → clic en una parte → detalle con costo, unidades y comentarios de visitantes

   ![Catálogo público](docs/screenshots/07-catalogo-publico.png)
