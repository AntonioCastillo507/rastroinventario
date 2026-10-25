<?php
if(empty($title))$title="Rastro del Istmo";
// Determinar nav activo.
// "Catalogo" es la unica pestana que depende del servidor (es una pagina real, ?ver=catalogo).
// Inicio / Secciones / Quienes somos / Contacto viven dentro de la home y se resaltan
// solos con Bootstrap Scrollspy (ver data-bs-spy mas abajo), asi nunca se marcan mal.
$_qs  = $_GET;
$_act = $_qs["action"]??"";
$_ver = $_qs["ver"]??"";
$navActivo = ($_act==="detalle"||$_act==="comprar"||$_ver==="catalogo") ? "catalogo" : "";
$esHome = empty($_qs);
$cartCount = count($_SESSION["cart"]??[]);
?>
<!DOCTYPE html><html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($title)?></title>
<?php require __DIR__ . "/recursos_head.php"; ?>
<link rel="stylesheet" href="<?=APP_URL?>/assets/css/style.css">
<link rel="stylesheet" href="<?=APP_URL?>/assets/css/extra.css">
</head>
<body class="public-body"<?php if($esHome): ?> data-bs-spy="scroll" data-bs-target="#navP" data-bs-offset="90" data-bs-smooth-scroll="true" tabindex="0"<?php endif; ?>>
<nav class="navbar navbar-expand-lg public-nav sticky-top">
  <div class="container-xl">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?=APP_URL?>/">
      <img src="<?=APP_URL?>/assets/img/logo.png" height="32" alt="Logo" onerror="this.style.display='none'" style="filter:brightness(0)saturate(100%)invert(78%)sepia(67%)saturate(700%)hue-rotate(2deg)brightness(105%)">
      <div>
        <span class="fw-bold text-white d-block lh-1" style="font-size:1rem">Rastro del Istmo</span>
        <span class="text-warning" style="font-size:.58rem;letter-spacing:.12em">RASTRO &amp; PARTES</span>
      </div>
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navP"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="navP">
      <ul class="navbar-nav me-auto ms-3 gap-1">
        <li class="nav-item"><a class="nav-link text-secondary" href="<?=APP_URL?>/#inicio">Inicio</a></li>
        <li class="nav-item"><a class="nav-link <?=$navActivo==="catalogo"?"active":"text-secondary"?>" href="<?=APP_URL?>/?ver=catalogo">Catálogo</a></li>
        <li class="nav-item"><a class="nav-link text-secondary" href="<?=APP_URL?>/#secciones">Secciones</a></li>
        <li class="nav-item"><a class="nav-link text-secondary" href="<?=APP_URL?>/#nosotros">Quiénes somos</a></li>
        <li class="nav-item"><a class="nav-link text-secondary" href="<?=APP_URL?>/#contacto">Contacto</a></li>
      </ul>
      <div class="d-flex align-items-center gap-2 mt-2 mt-lg-0">
        <form class="d-flex" method="GET" action="<?=APP_URL?>/">
          <input type="hidden" name="ver" value="catalogo">
          <div class="input-group input-group-sm">
            <input type="text" name="q" class="form-control dark-input" style="min-width:160px" placeholder="Buscar pieza..." value="<?=htmlspecialchars($q??"")?>">
            <button class="btn btn-gold" type="submit"><i class="bi bi-search"></i></button>
          </div>
        </form>
        <!-- Carrito -->
        <a href="<?=APP_URL?>/?action=carrito" class="btn btn-sm btn-outline-secondary cart-btn">
          <i class="bi bi-cart3"></i>
          <?php if($cartCount>0): ?><span class="cart-count"><?=$cartCount?></span><?php endif; ?>
        </a>
        <?php if(!empty($_SESSION["user"])): ?>
          <?php $roles=$_SESSION["user_roles"]??[]; $isAdmin=array_intersect(["Administrador","Vendedor","Consultor"],$roles); ?>
          <?php if($isAdmin): ?>
            <a href="<?=APP_URL?>/admin/" class="btn btn-sm btn-gold"><i class="bi bi-speedometer2 me-1"></i>Admin</a>
          <?php else: ?>
            <div class="dropdown">
              <button class="btn btn-sm btn-outline-warning dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-person-circle me-1"></i><?=htmlspecialchars(explode(" ",$_SESSION["user"]["nombre"])[0])?>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?=APP_URL?>/?action=mi_perfil"><i class="bi bi-person-gear me-2"></i>Mi Perfil</a></li>
                <li><a class="dropdown-item" href="<?=APP_URL?>/?action=mis_pedidos"><i class="bi bi-bag-check me-2"></i>Mis Pedidos</a></li>
                <li><hr class="dropdown-divider" style="border-color:var(--border)"></li>
                <li><a class="dropdown-item text-danger" href="<?=APP_URL?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
              </ul>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <a href="<?=APP_URL?>/login.php" class="btn btn-sm btn-outline-warning"><i class="bi bi-box-arrow-in-right me-1"></i>Iniciar sesión</a>
          <a href="<?=APP_URL?>/registro.php" class="btn btn-sm btn-outline-secondary btn-crear-cuenta"><i class="bi bi-person-plus me-1"></i>Crear cuenta</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
<?php if(!empty($_SESSION["flash"])){$f=$_SESSION["flash"];unset($_SESSION["flash"]);echo"<div class=\"container-xl mt-3\"><div class=\"alert alert-{$f["type"]} alert-dismissible fade show\">".htmlspecialchars($f["msg"])."<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button></div></div>";} ?>
