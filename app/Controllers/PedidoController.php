<?php
class PedidoController extends BaseController {
    private PedidoModel $pm;
    private ParteModel  $pam;
    public function __construct() { $this->pm=new PedidoModel(); $this->pam=new ParteModel(); }

    // ── Formulario de compra ──────────────────────────────────────
    public function formCompra(): void {
        $id    = Sanitizer::get("id","int");
        $parte = $this->pam->findById($id);
        if (!$parte||!$parte["activo"]||$parte["cantidad"]<=0)
            $this->redirect(APP_URL."/","Parte no disponible.","danger");
        if (!empty($_SESSION["user"])&&in_array("Cliente",$_SESSION["user_roles"]??[])) {
            // Cliente logueado → confirmación con sus datos
            $um=new UserModel();
            $userInfo=$um->findById($_SESSION["user"]["id"]);
            $viejo=FormViejo::tomar("compra_conf");
            $this->view("public/compra_confirmar",compact("parte","userInfo","viejo"));
        } else {
            $err=$_SESSION["compra_error"]??""; unset($_SESSION["compra_error"]);
            $viejo=FormViejo::tomar("compra");
            $this->view("public/compra",compact("parte","err","viejo"));
        }
    }

    // ── Procesar compra (invitado — cuenta OBLIGATORIA) ───────────
    public function procesarCompra(): void {
        CsrfToken::verify();
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
        session_regenerate_id(true);

        $_SESSION["pedido_exitoso"]=$pedidoId;
        $this->redirect(APP_URL."/?action=compra_exitosa");
    }

    // ── Confirmar compra (cliente ya logueado) ────────────────────
    public function confirmarCompra(): void {
        CsrfToken::verify();
        if (empty($_SESSION["user"])) $this->redirect(APP_URL."/login.php");
        $parteId  = Sanitizer::post("parte_id","int");
        $cantidad = Sanitizer::post("cantidad","int");
        $dir      = Sanitizer::post("direccion_entrega");
        $notas    = Sanitizer::post("cliente_notas");
        $conservar=fn()=>FormViejo::guardar("compra_conf",$_POST,["direccion_entrega","cliente_notas","cantidad"]);

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
        } else CsrfToken::verify();
        $parte=$this->pam->findById($id);
        if (!$parte||!$parte["activo"]||$parte["cantidad"]<=0){
            if ($ajax) $this->json(["ok"=>false,"msg"=>"Esa pieza ya no está disponible.","csrf"=>CsrfToken::generate()], 409);
            $this->flash("Parte no disponible.","danger"); $this->redirect(APP_URL."/?action=detalle&id=".$id);
        }
        if (!isset($_SESSION["cart"])) $_SESSION["cart"]=[];
        $yaEstaba = $_SESSION["cart"][$id]["cantidad"] ?? 0;
        if (isset($_SESSION["cart"][$id])){
            $nq=$_SESSION["cart"][$id]["cantidad"]+1;
            $_SESSION["cart"][$id]["cantidad"]=min($nq,$parte["cantidad"]);
        } else {
            $_SESSION["cart"][$id]=["parte_id"=>$id,"nombre"=>$parte["nombre"],"precio"=>(float)$parte["precio"],"cantidad"=>1,"max"=>$parte["cantidad"],"thumbnail"=>$parte["thumbnail"],"marca"=>($parte["marca"]??"")." ".($parte["modelo"]??"")];
        }
        $enTope = $yaEstaba >= $parte["cantidad"];
        $msg = $enTope ? "Ya tienes en el carrito todas las unidades disponibles de «".$parte["nombre"]."»." : "Añadida al carrito: «".$parte["nombre"]."».";
        if ($ajax) $this->json(["ok"=>true,"msg"=>$msg,"alerta"=>$enTope,"count"=>count($_SESSION["cart"]),"cantidad"=>(int)$_SESSION["cart"][$id]["cantidad"],"carrito"=>APP_URL."/?action=carrito","csrf"=>CsrfToken::generate()]);
        $this->flash($enTope ? $msg : "¡Pieza añadida al carrito!", $enTope ? "warning" : "success");
        $this->redirect(self::rutaDeRegreso($_POST["volver"] ?? null, $id));
    }

    public function quitarCarrito(): void {
        if (!$this->isPost()) $this->redirect(APP_URL."/?action=carrito"); // cambia el carrito: solo POST
        CsrfToken::verify();
        $id=Sanitizer::get("id","int"); unset($_SESSION["cart"][$id]);
        $this->redirect(APP_URL."/?action=carrito");
    }

    public function actualizarCarrito(): void {
        if ($this->isPost()) {
            CsrfToken::verify();
            $cantidades=$_POST["cantidad"]??[];
            foreach ($cantidades as $id=>$q) {
                $id=(int)$id; $q=max(1,(int)$q);
                if (isset($_SESSION["cart"][$id])) $_SESSION["cart"][$id]["cantidad"]=min($q,$_SESSION["cart"][$id]["max"]);
            }
        }
        $this->redirect(APP_URL."/?action=carrito");
    }

    public function verCarrito(): void {
        $cart=$_SESSION["cart"]??[];
        foreach ($cart as $id=>&$item){
            $p=$this->pam->findById((int)$id);
            if (!$p||!$p["activo"]||$p["cantidad"]<=0) unset($_SESSION["cart"][$id]);
            else $item["max"]=$p["cantidad"];
        }
        $cart=$_SESSION["cart"]??[];
        $monto=Itbms::calcular(array_sum(array_map(fn($i)=>$i["precio"]*$i["cantidad"],$cart)));
        $subtotal=$monto["subtotal"]; $itbms=$monto["itbms"]; $total=$monto["total"];
        $this->view("public/carrito",compact("cart","subtotal","itbms","total"));
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
        CsrfToken::verify();
        $cart=$_SESSION["cart"]??[];
        if (empty($cart)){ $this->redirect(APP_URL."/?action=carrito"); }

        $notas=Sanitizer::post("cliente_notas");
        $dir  =Sanitizer::post("cliente_direccion");
        // Si vuelve con error, la persona recupera lo escrito (la contraseña nunca se guarda)
        $conservar=fn()=>FormViejo::guardar("carrito",$_POST,["cliente_nombre","cliente_telefono","cliente_email","cliente_direccion","cliente_notas"]);

        // Verificar las piezas ANTES de tocar cuentas: nada se crea ni se descuenta si el carrito no vale
        // El precio sale de la BD, no de la sesión
        $items=[];
        foreach ($cart as $id=>$item){
            $p=$this->pam->findById((int)$id);
            if (!$p||!$p["activo"]){ $conservar(); $_SESSION["carrito_error"]="La pieza ".$item["nombre"]." ya no está disponible. Quítala del carrito."; $this->redirect(APP_URL."/?action=carrito"); }
            if ($p["cantidad"]<$item["cantidad"]){ $conservar(); $_SESSION["carrito_error"]="Stock insuficiente: ".$item["nombre"]; $this->redirect(APP_URL."/?action=carrito"); }
            if ((int)$item["cantidad"]<1||(float)$p["precio"]<0.01){ $conservar(); $_SESSION["carrito_error"]="Cantidad o precio no válido: ".$item["nombre"]; $this->redirect(APP_URL."/?action=carrito"); }
            $items[]=["parte_id"=>$id,"parte_nombre"=>$item["nombre"],"precio_unitario"=>(float)$p["precio"],"cantidad"=>(int)$item["cantidad"]];
        }

        // ── Cliente ya logueado ───────────────────────────────────
        $cuentaNueva=false;
        if (!empty($_SESSION["user"])&&in_array("Cliente",$_SESSION["user_roles"]??[])) {
            $um=new UserModel(); $u=$um->findById($_SESSION["user"]["id"]);
            $nombre=$u["nombre"]; $tel=$u["telefono"]??""; $email=$u["email"]; $usuarioId=$u["id"];
            $dir=$dir?:($u["direccion"]??"");
            if (!$dir){ $conservar(); $this->flash("Escribe una dirección de entrega.","danger"); $this->redirect(APP_URL."/?action=carrito"); }
        } else {
            // ── Invitado — cuenta obligatoria ─────────────────────
            $nombre=Sanitizer::post("cliente_nombre"); $tel=Sanitizer::post("cliente_telefono");
            $email=Sanitizer::email(Sanitizer::post("cliente_email")); $pass=$_POST["nueva_pass"]??"";
            $v=(new Validator())->required("nombre",$nombre,"Nombre")->required("telefono",$tel,"Teléfono")->required("email",$email,"Correo")->email("email",$email)->required("dir",$dir,"Dirección")->password("pass",$pass,$email,$nombre);
            if (!$v->isValid()){ $conservar(); $_SESSION["carrito_error"]=$v->first(); $this->redirect(APP_URL."/?action=carrito"); }
            $um=new UserModel(); $existe=$um->findByEmail($email);
            if ($existe){
                // No se asigna el pedido a una cuenta ajena sin autenticarse.
                $conservar(); $_SESSION["carrito_error"]="Ya existe una cuenta con ese correo. Inicia sesión para finalizar tu pedido.";
                $this->redirect(APP_URL."/?action=carrito");
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
            $_SESSION["user_roles"]=["Cliente"]; session_regenerate_id(true);
            EmailJsService::get()->enviarBienvenida($email,$nombre);
        }
        EmailJsService::get()->enviarConfirmacionCompra($email,$nombre,$pedidoId,$this->totalPedido($pedidoId));
        unset($_SESSION["cart"]);
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
                    if ($vp->isValid()) { $um->updatePassword($_SESSION["user"]["id"],password_hash($nueva,PASSWORD_BCRYPT)); $success="Contraseña actualizada correctamente."; }
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
