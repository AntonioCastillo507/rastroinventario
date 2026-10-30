<?php
namespace Tests\Support;

use PDO;

/**
 * Renderiza cada pantalla real del sitio (tienda, acceso y panel) en el proceso de la prueba y devuelve
 * su HTML completo, con la cabecera, el pie y los avisos. Hay que estar dentro de una prueba de
 * BaseDatos (transacción con rollback): crea los datos de apoyo que cada pantalla necesita.
 * Sirve para las pruebas de estructura, accesibilidad y menú.
 */
final class Pantallas {
    /** @var array<string,array{0:string,1:callable(array):void}> */
    private static function definiciones(): array {
        $pub  = fn() => new \PublicController();
        $get  = function (array $g): void { $_GET = array_merge($_GET, $g); }; // las pantallas del panel ya llevan ?mod=
        return [
            // ── Tienda ──
            "home"              => ["invitado", fn($f) => $pub()->home()],
            "nosotros"          => ["invitado", fn($f) => $pub()->nosotros()],
            "contacto"          => ["invitado", fn($f) => $pub()->contacto()],
            "catalogo"          => ["invitado", function ($f) use ($get, $pub) { $get(["ver" => "catalogo"]); $pub()->home(); }],
            "catalogo_filtros"  => ["invitado", function ($f) use ($get, $pub) { $get(["ver" => "catalogo", "q" => "Pieza", "marca" => "Volumen Alfa", "precio_min" => "5", "orden" => "precio_asc"]); $pub()->home(); }],
            "catalogo_vacio"    => ["invitado", function ($f) use ($get, $pub) { $get(["ver" => "catalogo", "q" => "zzzz-no-existe"]); $pub()->home(); }],
            "detalle_invitado"  => ["invitado", function ($f) use ($get, $pub) { $get(["id" => (string)$f["parte"]]); $pub()->detalle(); }],
            "detalle_cliente"   => ["cliente", function ($f) use ($get, $pub) { $get(["id" => (string)$f["parte"]]); $pub()->detalle(); }],
            "comprar_invitado"  => ["invitado", function ($f) use ($get, $pub) { $get(["id" => (string)$f["parte"]]); $pub()->comprar(); }],
            "comprar_cliente"   => ["cliente", function ($f) use ($get, $pub) { $get(["id" => (string)$f["parte"]]); $pub()->comprar(); }],
            "carrito_vacio"     => ["invitado", fn($f) => $pub()->carrito()],
            "carrito_invitado"  => ["invitado+carrito", fn($f) => $pub()->carrito()],
            "carrito_cliente"   => ["cliente+carrito", fn($f) => $pub()->carrito()],
            "carrito_deshacer"  => ["invitado+deshacer", fn($f) => $pub()->carrito()],
            "carrito_avisos"    => ["invitado+carrito+pieza_muerta", fn($f) => $pub()->carrito()],
            "carrito_datos_invitado" => ["invitado+carrito", fn($f) => $pub()->datosCarrito()],
            "carrito_datos_cliente"  => ["cliente+carrito", fn($f) => $pub()->datosCarrito()],
            "carrito_datos_error"    => ["invitado+carrito+datos_error", fn($f) => $pub()->datosCarrito()],
            "compra_exitosa"    => ["cliente+pedido", fn($f) => $pub()->exitosa()],
            "mis_pedidos"       => ["cliente", fn($f) => $pub()->misPedidos()],
            "mis_pedidos_vacio" => ["cliente_sin_pedidos", fn($f) => $pub()->misPedidos()],
            "mi_perfil"         => ["cliente", fn($f) => $pub()->miPerfil()],
            "mi_direccion"      => ["cliente", fn($f) => $pub()->miDireccion()],
            "mi_clave"          => ["cliente", fn($f) => $pub()->miClave()],
            "con_aviso_exito"   => ["invitado+aviso_exito", fn($f) => $pub()->carrito()],
            "con_aviso_error"   => ["invitado+aviso_error", fn($f) => $pub()->carrito()],
            // ── Acceso ──
            "login"             => ["anonimo", fn($f) => (new \AuthController())->showLogin()],
            "login_error"       => ["anonimo+login_error", fn($f) => (new \AuthController())->showLogin()],
            "registro"          => ["anonimo", fn($f) => (new \AuthController())->registro()],
            "registro_error"    => ["anonimo+registro_error", fn($f) => (new \AuthController())->registro()],
            "olvide"            => ["anonimo", fn($f) => (new \PasswordResetController())->olvide()],
            "olvide_ok"         => ["anonimo+reset_ok", fn($f) => (new \PasswordResetController())->olvide()],
            "restablecer_valido"   => ["anonimo+reset_valido", fn($f) => (new \PasswordResetController())->restablecer()],
            "restablecer_invalido" => ["anonimo", fn($f) => (new \PasswordResetController())->restablecer()],
            // ── Panel ──
            "admin_dashboard"   => ["admin", fn($f) => (new \DashboardController())->index()],
            "admin_dashboard_r7"   => ["admin", function ($f) use ($get) { $get(["r" => "7"]); (new \DashboardController())->index(); }],
            "admin_dashboard_r30"  => ["admin", function ($f) use ($get) { $get(["r" => "30"]); (new \DashboardController())->index(); }],
            "admin_dashboard_rtodo" => ["admin", function ($f) use ($get) { $get(["r" => "todo"]); (new \DashboardController())->index(); }],
            "admin_dashboard_r999" => ["admin", function ($f) use ($get) { $get(["r" => "999"]); (new \DashboardController())->index(); }],
            "admin_partes"      => ["admin", fn($f) => (new \ParteController())->index()],
            "admin_partes_crear"  => ["admin", fn($f) => (new \ParteController())->crear()],
            "admin_partes_editar" => ["admin", function ($f) use ($get) { $get(["id" => (string)$f["parte"]]); (new \ParteController())->editar(); }],
            "admin_autos"       => ["admin", fn($f) => (new \AutoController())->index()],
            "admin_autos_crear" => ["admin", fn($f) => (new \AutoController())->crear()],
            "admin_secciones"   => ["admin", fn($f) => (new \SeccionController())->index()],
            "admin_secciones_crear" => ["admin", fn($f) => (new \SeccionController())->crear()],
            "admin_usuarios"    => ["admin", fn($f) => (new \UserController())->index()],
            "admin_usuarios_crear"  => ["admin", fn($f) => (new \UserController())->crear()],
            "admin_usuarios_editar" => ["admin", function ($f) use ($get) { $get(["id" => (string)$f["cliente"]]); (new \UserController())->editar(); }],
            "admin_usuarios_clave"  => ["admin", fn($f) => (new \UserController())->miClave()],
            "admin_roles"       => ["admin", fn($f) => (new \RolController())->index()],
            "admin_roles_crear" => ["admin", fn($f) => (new \RolController())->crear()],
            "admin_roles_editar" => ["admin", function ($f) use ($get) { $get(["id" => (string)$f["rol"]]); (new \RolController())->editar(); }],
            "admin_ventas"      => ["admin", fn($f) => (new \VentaController())->index()],
            "admin_seguridad"   => ["admin", fn($f) => (new \SeguridadController())->index()],
            "admin_seguridad_p2" => ["admin", function ($f) use ($get) { $get(["page" => "2"]); (new \SeguridadController())->index(); }],
            "admin_ventas_crear" => ["admin", fn($f) => (new \VentaController())->crear()],
            "admin_pedidos"     => ["admin", fn($f) => (new \PedidoAdminController())->index()],
            "admin_pedido_detalle" => ["admin", function ($f) use ($get) { $get(["id" => (string)$f["pedido"]]); (new \PedidoAdminController())->detalle(); }],
            "admin_comentarios" => ["admin", fn($f) => (new \ComentarioController())->index()],
            "admin_reportes"    => ["admin", fn($f) => (new \ReporteController())->index()],
            "admin_aviso_error" => ["admin+aviso_error", fn($f) => (new \DashboardController())->index()],
        ];
    }

    /** @return list<string> */
    public static function nombres(): array {
        return array_keys(self::definiciones());
    }

    /** Nombres de las pantallas de la tienda (incluye el menú público) */
    public static function publicas(): array {
        return array_values(array_filter(self::nombres(), fn($n) => !str_starts_with($n, "admin_") && !in_array($n, ["login", "login_error", "registro", "registro_error", "olvide", "olvide_ok", "restablecer_valido", "restablecer_invalido"], true)));
    }

    public static function delPanel(): array {
        return array_values(array_filter(self::nombres(), fn($n) => str_starts_with($n, "admin_")));
    }

    public static function deAcceso(): array {
        return ["login", "login_error", "registro", "registro_error", "olvide", "olvide_ok", "restablecer_valido", "restablecer_invalido"];
    }

    private static ?array $datos = null;

    /** Hay que llamarlo al empezar cada prueba: los datos viven dentro de su transacción */
    public static function reiniciar(): void {
        self::$datos = null;
    }

    /** @return array{parte:int,cliente:int,admin:int,pedido:int,rol:int,muerta:int} */
    private static function datos(PDO $db): array {
        if (self::$datos !== null) return self::$datos;
        $v = Volumen::crear($db);
        $admin = (int)$db->query("SELECT id FROM usuarios WHERE email = 'volumen.1@example.invalid'")->fetchColumn();
        $db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) SELECT ?, id FROM roles WHERE nombre = 'Administrador'")->execute([$admin]);
        $cliente = Datos::usuario($db);
        $db->prepare("UPDATE usuarios SET nombre = 'Cliente de prueba', telefono = '6000-0000', direccion = 'Calle inventada 1', dir_provincia = '08', dir_distrito = '0808', dir_corregimiento = (SELECT codigo FROM corregimientos WHERE nombre = 'Bella Vista' AND distrito_codigo = '0808'), dir_calle = 'Calle inventada 1', dir_casa = 'Casa 1', dir_barriada = 'Bella Vista' WHERE id = ?")->execute([$cliente]);
        $parte = Datos::parte($db, 25.50, 12, "Pieza de pantalla");
        $db->prepare("UPDATE partes SET auto_id = ?, seccion_id = ? WHERE id = ?")->execute([$v["autos"][0], $v["secciones"][0], $parte]);
        $db->prepare("INSERT INTO comentarios (parte_id, nombre_visitante, comentario, rating, publicado, destacado) VALUES (?, 'Visitante', 'Funciona muy bien', 4, 1, 1)")->execute([$parte]);
        $pedido = Datos::pedido($db, [[$parte, 1]], "pendiente", $cliente);
        $muerta = Datos::parte($db, 9.00, 3, "Pieza desactivada de pantalla");
        $db->prepare("UPDATE partes SET activo = 0 WHERE id = ?")->execute([$muerta]);
        $rol = (int)$db->query("SELECT id FROM roles WHERE nombre = 'Administrador'")->fetchColumn();
        return self::$datos = ["parte" => $parte, "cliente" => $cliente, "admin" => $admin, "pedido" => $pedido, "rol" => $rol, "muerta" => $muerta];
    }

    private static function sesion(PDO $db, string $contexto, array $f): void {
        $_SESSION = array_diff_key($_SESSION, array_flip(["user", "user_roles", "cart", "cart_deshacer", "carrito_error", "form_viejo", "flash", "pedido_exitoso", "login_error", "registro_error", "reset_ok", "reset_error", "reset_hash"]));
        foreach (explode("+", $contexto) as $parte) {
            switch ($parte) {
                case "cliente": case "cliente_sin_pedidos":
                    $id = $parte === "cliente" ? $f["cliente"] : Datos::usuario($db);
                    $_SESSION["user"] = ["id" => $id, "nombre" => "Cliente de prueba", "email" => "cliente@example.invalid"];
                    $_SESSION["user_roles"] = ["Cliente"];
                    break;
                case "admin":
                    $_SESSION["user"] = ["id" => $f["admin"], "nombre" => "Admin de prueba", "email" => "volumen.1@example.invalid"];
                    $_SESSION["user_roles"] = ["Administrador"];
                    break;
                case "carrito":
                    $_SESSION["cart"] = [$f["parte"] => 2];
                    break;
                case "pieza_muerta":
                    $_SESSION["cart"][$f["muerta"]] = 1; // una pieza desactivada: el carrito la quita y lo avisa
                    break;
                case "deshacer":
                    $_SESSION["cart_deshacer"] = ["id" => $f["parte"], "cantidad" => 1, "nombre" => "Pieza de pantalla", "hasta" => time() + 300];
                    break;
                case "datos_error":
                    $_SESSION["carrito_error"] = "La contraseña debe tener al menos 8 caracteres.";
                    \FormViejo::guardar("carrito", ["cliente_nombre" => "Rosa Escrita", "cliente_email" => "rosa@example.invalid", "calle" => "Calle de lo escrito 5"], ["cliente_nombre", "cliente_email", "calle"]);
                    break;
                case "pedido":
                    $_SESSION["pedido_exitoso"] = $f["pedido"];
                    break;
                case "aviso_exito": $_SESSION["flash"] = ["msg" => "Todo salió bien.", "type" => "success"]; break;
                case "aviso_error": $_SESSION["flash"] = ["msg" => "Algo salió mal.", "type" => "danger"]; break;
                case "login_error": $_SESSION["login_error"] = "Correo o contraseña incorrectos."; break;
                case "registro_error": $_SESSION["registro_error"] = "Ya existe una cuenta con ese correo."; break;
                case "reset_ok": $_SESSION["reset_ok"] = "Si el correo existe, te enviamos un enlace."; break;
                case "reset_valido":
                    $hash = hash("sha256", "token-de-prueba-" . bin2hex(random_bytes(4)));
                    (new \PasswordResetModel())->registrarSolicitud("cliente@example.invalid", "127.0.0.1", $f["cliente"], $hash);
                    $_SESSION["reset_hash"] = $hash;
                    break;
            }
        }
    }

    /** HTML completo de la pantalla $nombre */
    public static function render(PDO $db, string $nombre, array $get = []): string {
        $defs = self::definiciones();
        if (!isset($defs[$nombre])) throw new \InvalidArgumentException("Pantalla desconocida: $nombre");
        [$contexto, $accion] = $defs[$nombre];
        $f = self::datos($db);
        $_GET = [];
        $_POST = [];
        if (str_starts_with($nombre, "admin_") && !in_array($nombre, ["admin_dashboard", "admin_aviso_error"], true)) {
            // admin/index.php enruta con ?mod=; el menú del panel lo usa para marcar la opción activa
            $_GET["mod"] = ["pedido_detalle" => "pedidos"][substr($nombre, 6)] ?? explode("_", substr($nombre, 6))[0];
        }
        $_GET = array_merge($_GET, $get); // parámetros extra de la prueba (filtros, página)
        $_SERVER["REQUEST_METHOD"] = "GET";
        self::sesion($db, $contexto, $f);
        ob_start();
        try {
            $accion($f);
        } finally {
            $html = (string)ob_get_clean();
            $_GET = [];
            $_SESSION = array_diff_key($_SESSION, array_flip(["user", "user_roles", "cart", "flash", "pedido_exitoso", "reset_hash"]));
        }
        if (str_contains($html, "Warning:") || str_contains($html, "Fatal error") || str_contains($html, "Notice:") || str_contains($html, "Deprecated:")) {
            throw new \RuntimeException("La pantalla $nombre produjo errores de PHP: " . substr(strip_tags($html), 0, 300));
        }
        return $html;
    }
}
