# Rastro del Istmo — Pasos para activar Excel y PDF

## 1. Instalar dependencias con Composer

Abre **CMD** (no PowerShell) como Administrador y ejecuta:

```
cd C:\wamp64\www\rastroinventario
composer install
```

Si no tienes Composer instalado:
1. Descarga de https://getcomposer.org/Composer-Setup.exe
2. Instala (detecta PHP automáticamente desde WampServer)
3. Reinicia CMD y ejecuta el comando de arriba

## 2. Verificar instalación

Debes ver una carpeta `vendor/` en `C:\wamp64\www\rastroinventario\`
con subcarpetas `phpoffice/` y `tecnickcom/`.

## 3. Lo que queda habilitado después

| Función | Ruta |
|---|---|
| Excel — Inventario por categoría | Admin > Reportes |
| Excel — Ventas por mes | Admin > Reportes |
| Excel — Total por categoría | Admin > Reportes |
| Excel — Piezas más vendidas | Admin > Reportes |
| Factura PDF (admin) | Admin > Pedidos > Ver > Descargar Factura |
| Factura PDF (cliente) | Página de compra exitosa > Descargar Factura |

## 4. Firma digital del PDF

El setup ya generó `ssl/rastro.crt` y `ssl/rastro.key` automáticamente.
TCPDF los usa para firmar digitalmente las facturas (PDF/A con OpenSSL).

## 5. Limpiar datos de prueba

El setupfix4 ya limpió pedidos/ventas/comentarios de prueba y borró
las cuentas basura. Solo quedan los usuarios del sistema:
- admin@rastroinventario.com / root2514
- vendedor1,2,3@rastroinventario.com / vend2025
- consultor1,2,3@rastroinventario.com / cons2025
