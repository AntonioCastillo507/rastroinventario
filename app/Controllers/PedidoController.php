<?php
class PedidoController extends BaseController {
    private PedidoModel $pm;
    private ParteModel  $pam;
    public function __construct() { $this->pm=new PedidoModel(); $this->pam=new ParteModel(); }

    // ── Formulario de compra ──────────────────────────────────────
    /** "Comprar ahora" del detalle manda ?cantidad=N: es el valor inicial del campo si no hay datos guardados de un intento anterior */
    private static function conCantidadInicial(array $viejo, array $parte): array {
        $c = $_GET["cantidad"] ?? null;
        if (!isset($viejo["cantidad"]) && is_string($c) && ctype_digit($c)) $viejo["cantidad"] = max(1, min((int)$c, (int)$parte["cantidad"]));
        return $viejo;
    }

    public function formCompra(): void {
        $id    = Sanitizer::get("id","int");
        $parte = $this->pam->findById($id);
        if (!$parte||!$parte["activo"]||$parte["cantidad"]<=0)
            $this->redirect(APP_URL."/","Parte no disponible.","danger");
        if (!empty($_SESSION["user"])&&in_array("Cliente",$_SESSION["user_roles"]??[])) {
            // Cliente logueado → confirmación con sus datos
            $um=new UserModel();
            $userInfo=$um->findById($_SESSION["user"]["id"]);
            $viejo=self::conCantidadInicial(FormViejo::tomar("compra_conf"),$parte);
            $this->view("public/compra_confirmar",compact("parte","userInfo","viejo"));
        } else {
            $err=$_SESSION["compra_error"]??""; unset($_SESSION["compra_error"]);
            $viejo=self::conCantidadInicial(FormViejo::tomar("compra"),$parte);
            $this->view("public/compra",compact("parte","err","viejo"));
        }
    }

    // ── Procesar compra (invitado — cuenta OBLIGATORIA) ───────────
    public function procesarCompra(): void {
        $parteId  = Sanitizer::post("parte_id","int");
        $cantidad = Sanitizer::post("cantidad","int");
        $nombre   = Sanitizer::post("cliente_nombre");
        $tel      = Sanitizer::post("cliente_telefono");
        $email    = Sanitizer::email(Sanitizer::post("cliente_email"));
        $dir      = Sanitizer::post("cliente_direccion");
        $pass     = $_POST["nueva_pass"]??"";
        $notas    = Sanitizer::post("cliente_notas");
        // Si vuelve con error, la persona recupera lo escrito (la contraseña nunca se guarda)
        $conservar=fn()=>FormViejo::guardar("compra",$_POST,["cliente_nombre","cliente_telefono","cliente_email","cliente_direccion","cliente_notas","cantidad"]);
        CsrfToken::verify(APP_URL."/?action=comprar&id=".$parteId,$conservar); // sin token válido vuelve al formulario con lo escrito

        $v=(new Validator())->required("nombre",$nombre,"Nombre")->required("telefono",$tel,"Teléfono")->required("email",$email,"Correo")->email("email",$email)->required("dir",$dir,"Dirección")->password("pass",$pass,$email,$nombre)->numeric("cantidad",$cantidad,1);
        if (!$v->isValid()){ $conservar(); $_SESSION["compra_error"]=$v->first(); $this->redirect(APP_URL."/?action=comprar&id=".$parteId); }

        $parte=$this->pam->findById($parteId);
        if (!$parte||!$parte["activo"]){ $conservar(); $_SESSION["compra_error"]="Esta pieza ya no está disponible."; $this->redirect(APP_URL."/?action=comprar&id=".$parteId); }
        if ($parte["cantidad"]<$cantidad){ $conservar(); $_SESSION["compra_error"]="Stock insuficiente."; $this->redirect(APP_URL."/?action=comprar&id=".$parteId); }
        if ((float)$parte["precio"]<0.01){ $conservar(); $_SESSION["compra_error"]="Esta pieza no tiene un precio válido."; $this->redirect(APP_URL."/?action=comprar&id=".$parteId); }

        // Crear cuenta (obligatoria)
        $um=new UserModel();
        $existe=$um->findByEmail($email);
        if ($existe) {
            // Correo ya registrado: nunca se abre sesión sin contraseña, hay que iniciar sesión primero.
            $conservar(); $_SESSION["compra_error"]="Ya existe una cuenta con ese correo. Inicia sesión para continuar con tu compra.";
            $this->redirect(APP_URL."/?action=comprar&id=".$parteId);
        } else {
            $uid=$um->create(["nombre"=>$nombre,"email"=>$email,"password"=>password_hash($pass,PASSWORD_BCRYPT),"telefono"=>$tel,"direccion"=>$dir]);
            if (!$uid){ $conservar(); $_SESSION["compra_error"]="Error al crear la cuenta."; $this->redirect(APP_URL."/?action=comprar&id=".$parteId); }
            $um->assignClienteRole($uid);
            $usuarioId=$uid;
        }

        // Stock y pedido en una sola transacción: si algo falla, el stock vuelve y la cuenta recién creada se retira
        $pedidoId=$this->apartarYRegistrar(["cliente_nombre"=>$nombre,"cliente_telefono"=>$tel,"cliente_email"=>$email,"cliente_direccion"=>$dir,"cliente_notas"=>$notas,"usuario_id"=>$usuarioId],[["parte_id"=>$parteId,"parte_nombre"=>$parte["nombre"],"precio_unitario"=>$parte["precio"],"cantidad"=>$cantidad]],$errCompra);
        if (!$pedidoId){ $um->hardDelete($usuarioId); $conservar(); $_SESSION["compra_error"]=$errCompra; $this->redirect(APP_URL."/?action=comprar&id=".$parteId); }
        EmailJsService::get()->enviarBienvenida($email,$nombre);
        EmailJsService::get()->enviarConfirmacionCompra($email,$nombre,$pedidoId,$this->totalPedido($pedidoId));

        // Auto-login y mantener sesión
        $u2=$um->findById($usuarioId);
        $_SESSION["user"]=["id"=>$usuarioId,"nombre"=>$u2["nombre"],"email"=>$u2["email"]];
        $_SESSION["user_roles"]=["Cliente"];
        session_regenerate_id(true); CsrfToken::rotar();

        $_SESSION["pedido_exitoso"]=$pedidoId;
        $this->redirect(APP_URL."/?action=compra_exitosa");
    }

    // ── Confirmar compra (cliente ya logueado) ────────────────────
    public function confirmarCompra(): void {
        if (empty($_SESSION["user"])) $this->redirect(APP_URL."/login.php");
        $parteId  = Sanitizer::post("parte_id","int");
        $cantidad = Sanitizer::post("cantidad","int");
        $dir      = Sanitizer::post("direccion_entrega");
        $notas    = Sanitizer::post("cliente_notas");
        $conservar=fn()=>FormViejo::guardar("compra_conf",$_POST,["direccion_entrega","cliente_notas","cantidad"]);
        CsrfToken::verify(APP_URL."/?action=comprar&id=".$parteId,$conservar);

        $parte=$this->pam->findById($parteId);
        if (!(new Validator())->numeric("cantidad",$cantidad,1)->isValid()){ $conservar(); $this->flash("La cantidad debe ser al menos 1.","danger"); $this->redirect(APP_URL."/?action=comprar&id=".$parteId); }
        if (!$parte||!$parte["activo"]){ $this->flash("Esta pieza ya no está disponible.","danger"); $this->redirect(APP_URL."/"); }
        if ($parte["cantidad"]<$cantidad){ $this->flash("Stock insuficiente.","danger"); $this->redirect(APP_URL."/?action=detalle&id=".$parteId); }
        if ((float)$parte["precio"]<0.01){ $this->flash("Esta pieza no tiene un precio válido.","danger"); $this->redirect(APP_URL."/?action=detalle&id=".$parteId); }
        if (!$dir){ $conservar(); $this->flash("Escribe una dirección de entrega.","danger"); $this->redirect(APP_URL."/?action=comprar&id=".$parteId); }

        $um=new UserModel(); $u=$um->findById($_SESSION["user"]["id"]);
        // Stock y pedido en una sola transacción: si registrar() falla, el stock vuelve
        $pedidoId=$this->apartarYRegistrar(["cliente_nombre"=>$u["nombre"],"cliente_telefono"=>$u["telefono"]??"","cliente_email"=>$u["email"],"cliente_direccion"=>$dir,"cliente_notas"=>$notas,"usuario_id"=>$_SESSION["user"]["id"]],[["parte_id"=>$parteId,"parte_nombre"=>$parte["nombre"],"precio_unitario"=>$parte["precio"],"cantidad"=>$cantidad]],$errCompra);
        if (!$pedidoId){ $this->flash($errCompra,"danger"); $this->redirect(APP_URL."/?action=detalle&id=".$parteId); }
        EmailJsService::get()->enviarConfirmacionCompra($u["email"],$u["nombre"],$pedidoId,$this->totalPedido($pedidoId));

        $_SESSION["pedido_exitoso"]=$pedidoId;
        $this->redirect(APP_URL."/?action=compra_exitosa");
    }

    // ── Carrito ───────────────────────────────────────────────────
    /** Responde JSON y termina (solo para "Añadir al carrito" hecho con fetch) */
    private function json(array $datos, int $codigo = 200): never {
        http_response_code($codigo);
        header("Content-Type: application/json; charset=utf-8");
        header("Cache-Control: no-store");
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Dirección interna a la que volver tras añadir sin JavaScript: debe ser una ruta de esta misma tienda (empieza con el
     * camino de APP_URL), sin esquema, sin dobles barras ni saltos de línea. Si no, se vuelve al detalle de la pieza.
     */
    public static function rutaDeRegreso(?string $volver, int $id): string {
        $detalle = APP_URL."/?action=detalle&id=".$id;
        $volver = (string)$volver;
        $base = rtrim((string)parse_url(APP_URL, PHP_URL_PATH), "/");
        if ($volver === "" || $volver[0] !== "/" || str_starts_with($volver, "//") || preg_match('/[\x00-\x1f\x7f\\\\]/', $volver)) return $detalle;
        if ($volver !== $base && !str_starts_with($volver, $base."/") && !str_starts_with($volver, $base."?")) return $detalle;
        $partes = parse_url($volver);
        if ($partes === false || isset($partes["scheme"]) || isset($partes["host"])) return $detalle;
        $origen = parse_url(APP_URL, PHP_URL_SCHEME)."://".parse_url(APP_URL, PHP_URL_HOST).(parse_url(APP_URL, PHP_URL_PORT) ? ":".parse_url(APP_URL, PHP_URL_PORT) : "");
        return $origen.preg_replace('/#.*/', "", $volver)."#parte-".$id; // vuelve a la misma tarjeta
    }

    public function agregarCarrito(): void {
        $id=$pId=Sanitizer::get("id","int");
        $ajax = ($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "") === "fetch";
        if (!$this->isPost()) $this->redirect(APP_URL."/?action=detalle&id=".$id); // cambia el carrito: solo POST
        if ($ajax) {
            if (!CsrfToken::check()) $this->json(["ok"=>false,"msg"=>"La página caducó. Recárgala e inténtalo de nuevo.","csrf"=>CsrfToken::generate()], 403);
        } else CsrfToken::verify(self::rutaDeRegreso($_POST["volver"] ?? null, $id));
        $parte=$this->pam->findById($id);
        if (!$parte||!$parte["activo"]||$parte["cantidad"]<=0){
            if ($ajax) $this->json(["ok"=>false,"msg"=>"Esa pieza ya no está disponible.","csrf"=>CsrfToken::generate()], 409);
            $this->flash("Parte no disponible.","danger"); $this->redirect(APP_URL."/?action=detalle&id=".$id);
        }
        // cantidad pedida (el detalle la manda; las tarjetas no): entre 1 y el stock; lo que no cabe no se añade
        $pedida = $_POST["cantidad"] ?? 1;
        $cant = is_string($pedida) && ctype_digit($pedida) ? max(1, min((int)$pedida, (int)$parte["cantidad"])) : 1;
        $yaEstaba = Carrito::cantidad($id);
        $nueva = min($yaEstaba + $cant, (int)$parte["cantidad"]);
        $anadidas = $nueva - $yaEstaba;
        Carrito::poner($id, $nueva); // la sesión guarda solo pieza y cantidad; el precio se lee de la base cuando hace falta
        $enTope = $anadidas < $cant;
        if ($anadidas <= 0)  $msg = "Ya tienes en el carrito todas las unidades disponibles de «".$parte["nombre"]."».";
        elseif ($enTope)     $msg = "Solo se añadieron ".$anadidas." de «".$parte["nombre"]."»: no hay más unidades disponibles.";
        elseif ($anadidas>1) $msg = "Añadidas ".$anadidas." al carrito: «".$parte["nombre"]."».";
        else                 $msg = "Añadida al carrito: «".$parte["nombre"]."».";
        if ($ajax) $this->json(["ok"=>true,"msg"=>$msg,"alerta"=>$enTope,"count"=>count(Carrito::leer()),"cantidad"=>$nueva,"carrito"=>APP_URL."/?action=carrito","csrf"=>CsrfToken::generate()]);
        $this->flash($enTope ? $msg : "¡Pieza añadida al carrito!", $enTope ? "warning" : "success");
        $this->redirect(self::rutaDeRegreso($_POST["volver"] ?? null, $id));
    }

    // ── Carrito, paso 1: las acciones de la lista responden JSON con fetch y redirigen sin JavaScript ──

    private static function dinero(float $x): string { return number_format($x, 2); }

    /** Token vigente o corte: con fetch, JSON 403; sin JavaScript, vuelve al carrito con aviso */
    private function csrfCarrito(bool $ajax): void {
        if ($ajax) {
            if (!CsrfToken::check()) $this->json(["ok"=>false,"msg"=>"La página caducó. Recárgala e inténtalo de nuevo.","csrf"=>CsrfToken::generate()], 403);
        } else CsrfToken::verify(APP_URL."/?action=carrito");
    }

    /**
     * El carrito tal como se muestra y se cobra: nombre, precio y stock de CADA línea salen de la base (una consulta),
     * nunca de la sesión. Lo que ya no se puede vender (inactiva, agotada, sin precio) se quita y lo que pasa del stock se
     * recorta, y cada caso queda en "avisos" para que la persona lo vea. Los totales salen de Itbms, igual que el pedido.
     * Con $ajustar = false calcula lo mismo pero NO toca la sesión (para comparar lo que se vio con lo que hay ahora).
     * @return array{lineas:list<array>,avisos:list<string>,subtotal:float,itbms:float,total:float,piezas:int}
     */
    public function detallarCarrito(bool $ajustar = true): array {
        $cart = Carrito::leer();
        $partes = $cart ? $this->pam->findByIds(array_keys($cart)) : [];
        $lineas = []; $avisos = [];
        foreach ($cart as $id => $cant) {
            $p = $partes[$id] ?? null;
            if (!$p || !$p["activo"] || (int)$p["cantidad"] <= 0 || (float)$p["precio"] < 0.01) {
                if ($ajustar) Carrito::quitar($id);
                $avisos[] = ($p ? "«".$p["nombre"]."»" : "Una pieza")." ya no está disponible y se quitó de tu carrito.";
                continue;
            }
            $max = (int)$p["cantidad"];
            if ($cant > $max) {
                $cant = $max; if ($ajustar) Carrito::poner($id, $cant);
                $avisos[] = "Solo quedan ".$max." de «".$p["nombre"]."»: ajustamos la cantidad.";
            }
            $precio = (float)$p["precio"];
            $lineas[] = ["id"=>$id,"nombre"=>$p["nombre"],"compat"=>trim(($p["marca"]??"")." ".($p["modelo"]??"")),"precio"=>$precio,"cantidad"=>$cant,"max"=>$max,
                "subtotal"=>round($precio*$cant, 2),"imagen_grande"=>$p["imagen_grande"]??null,"thumbnail"=>$p["thumbnail"]??null];
        }
        $monto = Itbms::calcular(array_sum(array_map(fn($l)=>$l["precio"]*$l["cantidad"], $lineas)));
        return ["lineas"=>$lineas,"avisos"=>$avisos,"piezas"=>array_sum(array_column($lineas,"cantidad"))] + $monto;
    }

    /** Respuesta JSON con TODO el estado del carrito ya formateado por el servidor (el JS no suma ni calcula dinero) */
    private function estadoCarrito(array $d, string $msg = "", bool $alerta = false): array {
        $lineas = [];
        foreach ($d["lineas"] as $l) $lineas[] = ["id"=>$l["id"],"cantidad"=>$l["cantidad"],"max"=>$l["max"],"subtotal"=>self::dinero($l["subtotal"])];
        $q = Carrito::quitadaReciente();
        return ["ok"=>true,"msg"=>$msg,"alerta"=>$alerta,"count"=>count($d["lineas"]),"piezas"=>$d["piezas"],"lineas"=>$lineas,
            "resumen"=>["subtotal"=>self::dinero($d["subtotal"]),"itbms"=>self::dinero($d["itbms"]),"total"=>self::dinero($d["total"])],
            "avisos"=>$d["avisos"],"deshacer"=>$q ? ["id"=>$q["id"],"nombre"=>$q["nombre"]] : null,
            "carrito"=>APP_URL."/?action=carrito","csrf"=>CsrfToken::generate()];
    }

    /** Termina la acción: JSON con el estado completo (fetch) o aviso y vuelta al carrito (sin JavaScript) */
    private function terminarCarrito(bool $ajax, string $msg, bool $alerta, int $codigo = 200, string $tipo = "success", ?array $d = null): never {
        $d ??= $this->detallarCarrito();
        if ($ajax) {
            $r = $this->estadoCarrito($d, $msg, $alerta);
            if ($codigo >= 400) $r["ok"] = false;
            $this->json($r, $codigo);
        }
        $avisos = $d["avisos"];
        if ($msg !== "" || $avisos) $this->flash(implode(" ", array_filter([$msg, ...$avisos])), $alerta || $avisos ? "warning" : $tipo);
        $this->redirect(APP_URL."/?action=carrito");
    }

    public function quitarCarrito(): void {
        if (!$this->isPost()) $this->redirect(APP_URL."/?action=carrito"); // cambia el carrito: solo POST
        $ajax = ($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "") === "fetch";
        $this->csrfCarrito($ajax);
        $id = Sanitizer::get("id","int");
        $cant = Carrito::cantidad($id);
        if ($cant < 1) $this->terminarCarrito($ajax, "Esa pieza ya no estaba en tu carrito.", true, 409, "warning");
        $p = $this->pam->findById($id);
        $nombre = $p ? (string)$p["nombre"] : "la pieza";
        Carrito::recordarQuitada($id, $cant, $nombre);
        Carrito::quitar($id);
        // sin JavaScript, el aviso con "Deshacer" lo pinta el carrito desde la sesión; con fetch viene en el JSON
        $this->terminarCarrito($ajax, $ajax ? "Quitaste «".$nombre."» de tu carrito." : "", false);
    }

    public function restaurarCarrito(): void {
        if (!$this->isPost()) $this->redirect(APP_URL."/?action=carrito");
        $ajax = ($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "") === "fetch";
        $this->csrfCarrito($ajax);
        $q = Carrito::quitadaReciente();
        $id = Sanitizer::get("id","int");
        if (!$q || ($id && $id !== $q["id"])) $this->terminarCarrito($ajax, "No hay nada que deshacer.", true, 409, "warning");
        $p = $this->pam->findById($q["id"]);
        Carrito::olvidarQuitada();
        if (!$p || !$p["activo"] || (int)$p["cantidad"] <= 0) $this->terminarCarrito($ajax, "«".$q["nombre"]."» ya no está disponible.", true, 409, "warning");
        $nueva = min(Carrito::cantidad($q["id"]) + $q["cantidad"], (int)$p["cantidad"]);
        Carrito::poner($q["id"], $nueva);
        $this->terminarCarrito($ajax, "Volvió a tu carrito: «".$p["nombre"]."».", false);
    }

    /** Fija la cantidad de UNA línea (valor absoluto, así repetirla o hacerla desde dos pestañas da el mismo resultado) */
    public function actualizarCarrito(): void {
        if (!$this->isPost()) $this->redirect(APP_URL."/?action=carrito");
        $ajax = ($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "") === "fetch";
        $this->csrfCarrito($ajax);
        $id = Sanitizer::get("id","int");
        $q = $_POST["cantidad"] ?? null;
        if (Carrito::cantidad($id) < 1) $this->terminarCarrito($ajax, "Esa pieza ya no está en tu carrito.", true, 409, "warning");
        if (!is_string($q) || !ctype_digit($q)) $this->terminarCarrito($ajax, "La cantidad no es válida.", true, 422, "danger");
        Carrito::poner($id, max(1, (int)$q)); // el stock real lo aplica detallarCarrito()
        $d = $this->detallarCarrito();
        $linea = null; foreach ($d["lineas"] as $l) if ($l["id"] === $id) $linea = $l;
        if (!$linea) $this->terminarCarrito($ajax, "", true, 200, "warning", $d);
        $pidio = (int)$q;
        $msg = $linea["cantidad"] < $pidio ? "" /* el aviso de ajuste ya lo dice */ : "«".$linea["nombre"]."»: ".$linea["cantidad"]." ".($linea["cantidad"]===1?"unidad":"unidades").".";
        $this->terminarCarrito($ajax, $msg, $linea["cantidad"] < $pidio, 200, "success", $d);
    }

    /** Paso 1: la lista del carrito con cantidades, deshacer y resumen */
    public function verCarrito(): void {
        $d = $this->detallarCarrito();
        $deshacer = Carrito::quitadaReciente();
        $errCarro = $_SESSION["carrito_error"] ?? ""; unset($_SESSION["carrito_error"]);
        $this->view("public/carrito", compact("d","deshacer","errCarro"));
    }

    /** Paso 2: tus datos (entrega y cuenta). Con el carrito vacío no hay nada que pedir */
    public function datosCarrito(): void {
        $d = $this->detallarCarrito();
        if (!$d["lineas"]) $this->redirect(APP_URL."/?action=carrito", $d["avisos"] ? implode(" ", $d["avisos"]) : "Tu carrito está vacío.", "warning");
        $viejo = FormViejo::tomar("carrito");
        $err = $_SESSION["carrito_error"] ?? ""; unset($_SESSION["carrito_error"]);
        $isCliente = !empty($_SESSION["user"]) && in_array("Cliente", $_SESSION["user_roles"] ?? []);
        $userInfo = $isCliente ? (new UserModel())->findById((int)$_SESSION["user"]["id"]) : null;
        $huella = Carrito::huella($d["lineas"]); // lo que se ve en esta pantalla; viaja en el formulario solo para detectar cambios
        $this->view("public/carrito_datos", compact("d","viejo","err","isCliente","userInfo","huella"));
    }

    /** Total con ITBMS tal como quedó guardado en el pedido (el que ve el cliente en todas partes) */
    private function totalPedido(int $pedidoId): float {
        $p=$this->pm->findById($pedidoId);
        return (float)($p["total"]??0);
    }

    /**
     * Todo o nada: bloquea cada pieza, descuenta su stock y registra el pedido en UNA transacción.
     * Si una pieza se desactivó o ya no alcanza, o registrar() falla (devuelve false o lanza), se deshace
     * todo y $error trae el motivo para mostrarlo. Devuelve el id del pedido o false.
     */
    private function apartarYRegistrar(array $cabecera, array $items, ?string &$error = null): int|false {
        $db=Database::get();
        $db->beginTransaction();
        try {
            foreach ($items as $it){
                $p=$this->pam->bloquear((int)$it["parte_id"]);
                if (!$p||!$p["activo"]){ $db->rollBack(); $error="La pieza ".$it["parte_nombre"]." ya no está disponible."; return false; }
                if (!$this->pam->decrementarStock((int)$it["parte_id"],(int)$it["cantidad"])){ $db->rollBack(); $error="Stock insuficiente: ".$it["parte_nombre"]; return false; }
            }
            $id=$this->pm->registrar($cabecera,$items);
            if (!$id){ $db->rollBack(); $error="Error al registrar el pedido."; return false; }
            $db->commit();
            return (int)$id;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $ref=registrarError($e,"Compra: ");
            $error="No se pudo registrar el pedido. Intenta de nuevo; si sigue, indica el código de referencia $ref.";
            return false;
        }
    }

    public function procesarCarrito(): void {
        $cart=Carrito::leer();
        if (empty($cart)){ $this->redirect(APP_URL."/?action=carrito","Tu carrito está vacío.","warning"); }
        ksort($cart); // siempre se bloquean las piezas en el mismo orden (por id): dos pedidos con las mismas piezas no se interbloquean

        $notas=Sanitizer::post("cliente_notas");
        $dir  =Sanitizer::post("cliente_direccion");
        // Si vuelve con error, la persona recupera lo escrito (la contraseña nunca se guarda)
        $conservar=fn()=>FormViejo::guardar("carrito",$_POST,["cliente_nombre","cliente_telefono","cliente_email","cliente_direccion","cliente_notas"]);
        $datos=APP_URL."/?action=carrito_datos"; // paso 2: errores del formulario
        CsrfToken::verify($datos,$conservar);

        // Lo que la persona vio en el paso 2 tiene que ser lo que se va a cobrar: si cambió algo (precio, stock, otra pestaña) o falta
        // la huella, no se crea nada y vuelve al paso 2 con el resumen al día. La huella solo detecta cambios; el pedido se calcula de la base.
        $vista=$_POST["resumen_visto"]??"";
        if (!is_string($vista)||!hash_equals(Carrito::huella($this->detallarCarrito(false)["lineas"]),$vista)){
            $conservar(); $this->flash("Tu pedido cambió mientras llenabas tus datos. Revisa el resumen y confirma de nuevo.","warning"); $this->redirect($datos);
        }

        // Verificar las piezas ANTES de tocar cuentas: nada se crea ni se descuenta si el carrito no vale
        // Nombre y precio salen de la BD, no de la sesión
        $partes=$this->pam->findByIds(array_keys($cart));
        $items=[];
        foreach ($cart as $id=>$cantidad){
            $p=$partes[$id]??false; $nombre=$p?$p["nombre"]:"Una pieza";
            if (!$p||!$p["activo"]){ $conservar(); $_SESSION["carrito_error"]="La pieza ".$nombre." ya no está disponible. Quítala del carrito."; $this->redirect(APP_URL."/?action=carrito"); }
            if ($p["cantidad"]<$cantidad){ $conservar(); $_SESSION["carrito_error"]="Stock insuficiente: ".$nombre; $this->redirect(APP_URL."/?action=carrito"); }
            if ((int)$cantidad<1||(float)$p["precio"]<0.01){ $conservar(); $_SESSION["carrito_error"]="Cantidad o precio no válido: ".$nombre; $this->redirect(APP_URL."/?action=carrito"); }
            $items[]=["parte_id"=>$id,"parte_nombre"=>$nombre,"precio_unitario"=>(float)$p["precio"],"cantidad"=>(int)$cantidad];
        }

        // ── Cliente ya logueado ───────────────────────────────────
        $cuentaNueva=false;
        if (!empty($_SESSION["user"])&&in_array("Cliente",$_SESSION["user_roles"]??[])) {
            $um=new UserModel(); $u=$um->findById($_SESSION["user"]["id"]);
            $nombre=$u["nombre"]; $tel=$u["telefono"]??""; $email=$u["email"]; $usuarioId=$u["id"];
            $dir=$dir?:($u["direccion"]??"");
            if (!$dir){ $conservar(); $this->flash("Escribe una dirección de entrega.","danger"); $this->redirect($datos); }
        } else {
            // ── Invitado — cuenta obligatoria ─────────────────────
            $nombre=Sanitizer::post("cliente_nombre"); $tel=Sanitizer::post("cliente_telefono");
            $email=Sanitizer::email(Sanitizer::post("cliente_email")); $pass=$_POST["nueva_pass"]??"";
            $v=(new Validator())->required("nombre",$nombre,"Nombre")->required("telefono",$tel,"Teléfono")->required("email",$email,"Correo")->email("email",$email)->required("dir",$dir,"Dirección")->password("pass",$pass,$email,$nombre);
            if (!$v->isValid()){ $conservar(); $_SESSION["carrito_error"]=$v->first(); $this->redirect($datos); }
            $um=new UserModel(); $existe=$um->findByEmail($email);
            if ($existe){
                // No se asigna el pedido a una cuenta ajena sin autenticarse.
                $conservar(); $_SESSION["carrito_error"]="Ya existe una cuenta con ese correo. Inicia sesión para finalizar tu pedido.";
                $this->redirect($datos);
            }
            else {
                $uid=$um->create(["nombre"=>$nombre,"email"=>$email,"password"=>password_hash($pass,PASSWORD_BCRYPT),"telefono"=>$tel,"direccion"=>$dir]);
                $um->assignClienteRole($uid); $usuarioId=$uid; $cuentaNueva=true;
            }
        }

        // Todo o nada: si una pieza ya no alcanza o registrar() falla, se deshace todo (y la cuenta recién creada se retira)
        $pedidoId=$this->apartarYRegistrar(["cliente_nombre"=>$nombre,"cliente_telefono"=>$tel,"cliente_email"=>$email,"cliente_direccion"=>$dir,"cliente_notas"=>$notas,"usuario_id"=>$usuarioId],$items,$errCompra);
        if (!$pedidoId){
            if ($cuentaNueva) $um->hardDelete($usuarioId);
            $conservar(); $_SESSION["carrito_error"]=$errCompra; $this->redirect(APP_URL."/?action=carrito");
        }
        if ($cuentaNueva){
            $_SESSION["user"]=["id"=>$usuarioId,"nombre"=>$nombre,"email"=>$email];
            $_SESSION["user_roles"]=["Cliente"]; session_regenerate_id(true); CsrfToken::rotar();
            EmailJsService::get()->enviarBienvenida($email,$nombre);
        }
        EmailJsService::get()->enviarConfirmacionCompra($email,$nombre,$pedidoId,$this->totalPedido($pedidoId));
        Carrito::vaciar();
        $_SESSION["pedido_exitoso"]=$pedidoId;
        $this->redirect(APP_URL."/?action=compra_exitosa");
    }

    public function exitosa(): void {
        $pid=$_SESSION["pedido_exitoso"]??0;
        if (!$pid) $this->redirect(APP_URL."/");
        $pedido=(new PedidoModel())->findWithItems($pid);
        unset($_SESSION["pedido_exitoso"]);
        $this->view("public/compra_exitosa",compact("pedido"));
    }

    public function misPedidos(): void {
        if (empty($_SESSION["user"])) $this->redirect(APP_URL."/login.php");
        $pedidos=(new PedidoModel())->findByUsuario($_SESSION["user"]["id"]);
        $this->view("public/mis_pedidos",compact("pedidos"));
    }

    public function miPerfil(): void {
        if (empty($_SESSION["user"])) $this->redirect(APP_URL."/login.php");
        $um=new UserModel(); $user=$um->findById($_SESSION["user"]["id"]); $error=""; $success="";
        if ($this->isPost()){
            CsrfToken::verify();
            if (isset($_POST["nueva"])) {
                $actual=$_POST["actual"]??""; $nueva=$_POST["nueva"]??""; $conf=$_POST["confirmar"]??"";
                if (!password_verify($actual,$user["password"])) { $error="La contraseña actual no es correcta."; }
                elseif ($nueva!==$conf) { $error="La nueva contraseña y su confirmación no coinciden."; }
                else {
                    $vp=(new Validator())->password("nueva",$nueva,$user["email"],$user["nombre"]);
                    if ($vp->isValid()) { $um->updatePassword($_SESSION["user"]["id"],password_hash($nueva,PASSWORD_BCRYPT)); $success="Contraseña actualizada correctamente."; CsrfToken::rotar(); }
                    else { $error=$vp->first(); }
                }
                $this->view("public/mi_perfil",compact("user","error","success")); return;
            }
            $upd=["nombre"=>Sanitizer::post("nombre"),"telefono"=>Sanitizer::post("telefono"),"direccion"=>Sanitizer::post("direccion")];
            $v=(new Validator())->required("nombre",$upd["nombre"],"Nombre");
            if ($v->isValid()){ $um->updateProfile($_SESSION["user"]["id"],$upd); $_SESSION["user"]["nombre"]=$upd["nombre"]; $success="Perfil actualizado."; $user=$um->findById($_SESSION["user"]["id"]); }
            else $error=$v->first();
        }
        $this->view("public/mi_perfil",compact("user","error","success"));
    }
}
