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
            $this->view("public/compra_confirmar",compact("parte","userInfo"));
        } else {
            $err=$_SESSION["compra_error"]??""; unset($_SESSION["compra_error"]);
            $this->view("public/compra",compact("parte","err"));
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

        $v=(new Validator())->required("nombre",$nombre,"Nombre")->required("telefono",$tel,"Teléfono")->required("email",$email,"Correo")->email("email",$email)->required("dir",$dir,"Dirección")->password("pass",$pass)->numeric("cantidad",$cantidad,1);
        if (!$v->isValid()){ $_SESSION["compra_error"]=$v->first(); $this->redirect(APP_URL."/?action=comprar&id=".$parteId); }

        $parte=$this->pam->findById($parteId);
        if (!$parte||$parte["cantidad"]<$cantidad){ $_SESSION["compra_error"]="Stock insuficiente."; $this->redirect(APP_URL."/?action=comprar&id=".$parteId); }
        if ((float)$parte["precio"]<0.01){ $_SESSION["compra_error"]="Esta pieza no tiene un precio válido."; $this->redirect(APP_URL."/?action=comprar&id=".$parteId); }

        // Crear cuenta (obligatoria)
        $um=new UserModel();
        $existe=$um->findByEmail($email);
        if ($existe) {
            // Correo ya registrado: nunca se abre sesión sin contraseña, hay que iniciar sesión primero.
            $_SESSION["compra_error"]="Ya existe una cuenta con ese correo. Inicia sesión para continuar con tu compra.";
            $this->redirect(APP_URL."/?action=comprar&id=".$parteId);
        } else {
            $uid=$um->create(["nombre"=>$nombre,"email"=>$email,"password"=>password_hash($pass,PASSWORD_BCRYPT),"telefono"=>$tel,"direccion"=>$dir]);
            if (!$uid){ $_SESSION["compra_error"]="Error al crear la cuenta."; $this->redirect(APP_URL."/?action=comprar&id=".$parteId); }
            $um->assignClienteRole($uid);
            $usuarioId=$uid;
            EmailJsService::get()->enviarBienvenida($email,$nombre);
        }

        if (!$this->pam->decrementarStock($parteId,$cantidad)){ $_SESSION["compra_error"]="Error de inventario."; $this->redirect(APP_URL."/?action=comprar&id=".$parteId); }

        $pedidoId=$this->pm->registrar(["cliente_nombre"=>$nombre,"cliente_telefono"=>$tel,"cliente_email"=>$email,"cliente_direccion"=>$dir,"cliente_notas"=>$notas,"usuario_id"=>$usuarioId],[["parte_id"=>$parteId,"parte_nombre"=>$parte["nombre"],"precio_unitario"=>$parte["precio"],"cantidad"=>$cantidad]]);
        if (!$pedidoId){ $_SESSION["compra_error"]="Error al registrar pedido."; $this->redirect(APP_URL."/?action=comprar&id=".$parteId); }
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

        $parte=$this->pam->findById($parteId);
        if (!(new Validator())->numeric("cantidad",$cantidad,1)->isValid()){ $this->flash("La cantidad debe ser al menos 1.","danger"); $this->redirect(APP_URL."/?action=comprar&id=".$parteId); }
        if (!$parte||$parte["cantidad"]<$cantidad){ $this->flash("Stock insuficiente.","danger"); $this->redirect(APP_URL."/?action=detalle&id=".$parteId); }
        if ((float)$parte["precio"]<0.01){ $this->flash("Esta pieza no tiene un precio válido.","danger"); $this->redirect(APP_URL."/?action=detalle&id=".$parteId); }
        if (!$dir){ $this->flash("Escribe una dirección de entrega.","danger"); $this->redirect(APP_URL."/?action=comprar&id=".$parteId); }

        $um=new UserModel(); $u=$um->findById($_SESSION["user"]["id"]);
        if (!$this->pam->decrementarStock($parteId,$cantidad)){ $this->flash("Error de inventario.","danger"); $this->redirect(APP_URL."/?action=detalle&id=".$parteId); }

        $pedidoId=$this->pm->registrar(["cliente_nombre"=>$u["nombre"],"cliente_telefono"=>$u["telefono"]??"","cliente_email"=>$u["email"],"cliente_direccion"=>$dir,"cliente_notas"=>$notas,"usuario_id"=>$_SESSION["user"]["id"]],[["parte_id"=>$parteId,"parte_nombre"=>$parte["nombre"],"precio_unitario"=>$parte["precio"],"cantidad"=>$cantidad]]);
        if (!$pedidoId){ $this->flash("Error al registrar.","danger"); $this->redirect(APP_URL."/?action=detalle&id=".$parteId); }
        EmailJsService::get()->enviarConfirmacionCompra($u["email"],$u["nombre"],$pedidoId,$this->totalPedido($pedidoId));

        $_SESSION["pedido_exitoso"]=$pedidoId;
        $this->redirect(APP_URL."/?action=compra_exitosa");
    }

    // ── Carrito ───────────────────────────────────────────────────
    public function agregarCarrito(): void {
        $id=$pId=Sanitizer::get("id","int");
        if (!$this->isPost()) $this->redirect(APP_URL."/?action=detalle&id=".$id); // cambia el carrito: solo POST
        CsrfToken::verify();
        $parte=$this->pam->findById($id);
        if (!$parte||!$parte["activo"]||$parte["cantidad"]<=0){ $this->flash("Parte no disponible.","danger"); $this->redirect(APP_URL."/?action=detalle&id=".$id); }
        if (!isset($_SESSION["cart"])) $_SESSION["cart"]=[];
        if (isset($_SESSION["cart"][$id])){
            $nq=$_SESSION["cart"][$id]["cantidad"]+1;
            $_SESSION["cart"][$id]["cantidad"]=min($nq,$parte["cantidad"]);
        } else {
            $_SESSION["cart"][$id]=["parte_id"=>$id,"nombre"=>$parte["nombre"],"precio"=>(float)$parte["precio"],"cantidad"=>1,"max"=>$parte["cantidad"],"thumbnail"=>$parte["thumbnail"],"marca"=>($parte["marca"]??"")." ".($parte["modelo"]??"")];
        }
        $this->flash("¡Pieza añadida al carrito!","success");
        $this->redirect(APP_URL."/?action=detalle&id=".$id);
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

    public function procesarCarrito(): void {
        CsrfToken::verify();
        $cart=$_SESSION["cart"]??[];
        if (empty($cart)){ $this->redirect(APP_URL."/?action=carrito"); }

        $notas=Sanitizer::post("cliente_notas");
        $dir  =Sanitizer::post("cliente_direccion");

        // ── Cliente ya logueado ───────────────────────────────────
        if (!empty($_SESSION["user"])&&in_array("Cliente",$_SESSION["user_roles"]??[])) {
            $um=new UserModel(); $u=$um->findById($_SESSION["user"]["id"]);
            $nombre=$u["nombre"]; $tel=$u["telefono"]??""; $email=$u["email"]; $usuarioId=$u["id"];
            $dir=$dir?:($u["direccion"]??"");
            if (!$dir){ $this->flash("Escribe una dirección de entrega.","danger"); $this->redirect(APP_URL."/?action=carrito"); }
        } else {
            // ── Invitado — cuenta obligatoria ─────────────────────
            $nombre=Sanitizer::post("cliente_nombre"); $tel=Sanitizer::post("cliente_telefono");
            $email=Sanitizer::email(Sanitizer::post("cliente_email")); $pass=$_POST["nueva_pass"]??"";
            $v=(new Validator())->required("nombre",$nombre,"Nombre")->required("telefono",$tel,"Teléfono")->required("email",$email,"Correo")->email("email",$email)->required("dir",$dir,"Dirección")->password("pass",$pass);
            if (!$v->isValid()){ $_SESSION["carrito_error"]=$v->first(); $this->redirect(APP_URL."/?action=carrito"); }
            $um=new UserModel(); $existe=$um->findByEmail($email);
            if ($existe){
                // No se asigna el pedido a una cuenta ajena sin autenticarse.
                $_SESSION["carrito_error"]="Ya existe una cuenta con ese correo. Inicia sesión para finalizar tu pedido.";
                $this->redirect(APP_URL."/?action=carrito");
            }
            else {
                $uid=$um->create(["nombre"=>$nombre,"email"=>$email,"password"=>password_hash($pass,PASSWORD_BCRYPT),"telefono"=>$tel,"direccion"=>$dir]);
                $um->assignClienteRole($uid); $usuarioId=$uid;
                $_SESSION["user"]=["id"=>$uid,"nombre"=>$nombre,"email"=>$email];
                $_SESSION["user_roles"]=["Cliente"]; session_regenerate_id(true);
                EmailJsService::get()->enviarBienvenida($email,$nombre);
            }
        }

        // Verificar stock y construir items
        // El precio sale de la BD, no de la sesión
        $items=[];
        foreach ($cart as $id=>$item){
            $p=$this->pam->findById((int)$id);
            if (!$p||$p["cantidad"]<$item["cantidad"]){ $_SESSION["carrito_error"]="Stock insuficiente: ".$item["nombre"]; $this->redirect(APP_URL."/?action=carrito"); }
            if ((int)$item["cantidad"]<1||(float)$p["precio"]<0.01){ $_SESSION["carrito_error"]="Cantidad o precio no válido: ".$item["nombre"]; $this->redirect(APP_URL."/?action=carrito"); }
            $items[]=["parte_id"=>$id,"parte_nombre"=>$item["nombre"],"precio_unitario"=>(float)$p["precio"],"cantidad"=>(int)$item["cantidad"]];
        }
        // Todo o nada: si una pieza ya no alcanza (compra simultánea), se deshacen los descuentos
        $db=Database::get(); $db->beginTransaction();
        foreach ($items as $it){
            if (!$this->pam->decrementarStock((int)$it["parte_id"],$it["cantidad"])){
                $db->rollBack();
                $_SESSION["carrito_error"]="Stock insuficiente: ".$it["parte_nombre"];
                $this->redirect(APP_URL."/?action=carrito");
            }
        }
        $pedidoId=$this->pm->registrar(["cliente_nombre"=>$nombre,"cliente_telefono"=>$tel,"cliente_email"=>$email,"cliente_direccion"=>$dir,"cliente_notas"=>$notas,"usuario_id"=>$usuarioId],$items);
        if (!$pedidoId){ $db->rollBack(); $_SESSION["carrito_error"]="Error al registrar el pedido."; $this->redirect(APP_URL."/?action=carrito"); }
        $db->commit();
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
                    $vp=(new Validator())->password("nueva",$nueva);
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
