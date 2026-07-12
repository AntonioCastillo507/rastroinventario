# RastroinventariO — Sistema de Inventario de Partes de Autos

> **Proyecto Final** — Desarrollo de Software VII
> Prof. Ing. Irina Fong | Universidad Tecnológica de Panamá

---

## Instalación rápida

1. Copia la carpeta `rastroinventario/` en `C:\wamp64\www\`
2. Inicia WampServer (ícono verde en bandeja)
3. Abre: http://localhost/rastroinventario/setup.php
4. **Elimina `setup.php`** cuando termine

**Admin:** admin@rastroinventario.com / root2514

---

## Imágenes propias (assets/img/)

| Archivo       | Uso                              | Tamaño recomendado |
|---------------|----------------------------------|--------------------|
| `logo.png`    | Logo en navbar y footer          | ~200×60 px         |
| `hero-bg.jpg` | Fondo de la portada pública      | 1920×800 px        |
| `no-image.jpg`| Partes sin foto                  | 400×400 px         |
| `carro1.jpg`  | Imagen decorativa (opcional)     | 800×500 px         |
| `carro2.jpg`  | Imagen decorativa (opcional)     | 800×500 px         |

---

## Arquitectura MVC

```
rastroinventario/
├── app/
│   ├── Config/        Database.php, config.php
│   ├── Interfaces/    CrudInterface, ErrorHandlerInterface
│   ├── Utils/         Sanitizer, Validator, CsrfToken, DigitalSignature, ImageHelper
│   ├── Models/        BaseModel + 8 modelos específicos
│   ├── Controllers/   BaseController + 10 controladores
│   └── Views/         layouts/, auth/, admin/*, public/
├── assets/css/        style.css (tema oscuro/oro)
├── assets/js/         main.js
├── assets/img/        ← PON AQUÍ TUS IMÁGENES PROPIAS
├── uploads/           parts/ + thumbnails/ (subidas por admin)
├── admin/index.php    Dispatcher del panel admin
├── bootstrap.php      Autoloader manual
├── index.php          Portada pública
├── login.php / logout.php
└── setup.php          (eliminar después de instalar)
```

---

## Requisitos cumplidos

| # | Requisito | Implementación |
|---|-----------|----------------|
| RNF-01 | CSRF en todos los POST | `CsrfToken::verify()` |
| RNF-02 | Conexión mediante clase | `Database` Singleton PDO |
| RNF-03 | Sanitizar y validar | `Sanitizer` + `Validator` |
| RNF-04 | Bloqueo al 3.er intento + bitácora IPs | `AuthController` + `login_logs` |
| RNF-05 | Contraseña bcrypt 8-12 chars | `Validator::password()` |
| RNF-06 | Firma digital HMAC-SHA256 | `DigitalSignature` en ventas y partes |
| — | OWASP / PDO prepared | Todos los modelos |
| — | MVC + SOLID + DRY | Arquitectura completa |
| — | Interfaces como contratos | `CrudInterface`, `ErrorHandlerInterface` |
| — | Roles y permisos por módulo | Tabla `permisos` + `UserModel::hasPermission()` |
| — | Thumbnails automáticos | `ImageHelper` con GD |
| — | Página pública con comentarios | `PublicController` + moderación admin |

---

**Autor:** Antonio Agustín Castillo Pérez
**GitHub:** AntonioCastillo507
