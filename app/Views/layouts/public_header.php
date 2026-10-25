<?php
if(empty($title))$title="Rastro del Istmo";
// Opción activa del menú: la decide cada vista con $navActivo ("inicio" o "catalogo") antes de incluir
// este encabezado. Secciones, Quiénes somos y Contacto son anclas de la portada y no se marcan.
$navActivo = $navActivo ?? "";
$cartCount = count($_SESSION["cart"]??[]);
?>
<!DOCTYPE html><html lang="es" data-theme="dark" data-bs-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($title)?></title>
<?php require __DIR__ . "/recursos_head.php"; ?>
<link rel="stylesheet" href="<?=APP_URL?>/assets/css/style.css">
<link rel="stylesheet" href="<?=APP_URL?>/assets/css/extra.css">
<link rel="stylesheet" href="<?=APP_URL?>/assets/css/base.css">
</head>
<body class="public-body">
<a class="visually-hidden-focusable skip-link" href="#contenido">Saltar al contenido</a>
<nav class="navbar navbar-expand-lg public-nav sticky-top" aria-label="Principal">
  <div class="container-xl">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?=APP_URL?>/">
      <img src="<?=APP_URL?>/assets/img/logo.png" height="32" alt="" onerror="this.style.display='none'" style="filter:brightness(0)saturate(100%)invert(78%)sepia(67%)saturate(700%)hue-rotate(2deg)brightness(105%)">
      <div>
        <span class="fw-bold text-white d-block lh-1" style="font-size:1rem">Rastro del Istmo</span>
        <span class="text-warning" style="font-size:.58rem;letter-spacing:.12em">RASTRO &amp; PARTES</span>
      </div>
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navP" aria-controls="navP" aria-expanded="false" aria-label="Abrir o cerrar el menú"><span class="navbar-toggler-icon" aria-hidden="true"></span></button>
    <div class="collapse navbar-collapse" id="navP">
      <ul class="navbar-nav me-auto ms-3 gap-1">
        <?php foreach([["inicio","Inicio","/"],["catalogo","Catálogo","/?ver=catalogo"],["secciones","Secciones","/#secciones"],["nosotros","Quiénes somos","/#nosotros"],["contacto","Contacto","/#contacto"]] as [$clave,$texto,$ruta]): $esActiva=$navActivo===$clave; ?>
        <li class="nav-item"><a class="nav-link <?=$esActiva?"active":"text-secondary"?>" href="<?=APP_URL.htmlspecialchars($ruta)?>"<?=$esActiva?' aria-current="page"':""?>><?=htmlspecialchars($texto)?></a></li>
        <?php endforeach; ?>
      </ul>
      <div class="d-flex align-items-center flex-wrap gap-2 mt-2 mt-lg-0">
        <form class="d-flex" method="GET" action="<?=APP_URL?>/" role="search">
          <input type="hidden" name="ver" value="catalogo">
          <div class="input-group input-group-sm">
            <input type="search" name="q" class="form-control dark-input" style="min-width:160px" placeholder="Buscar pieza..." aria-label="Buscar pieza" value="<?=htmlspecialchars($q??"")?>">
            <button class="btn btn-gold" type="submit" aria-label="Buscar"><i aria-hidden="true" class="bi bi-search"></i></button>
          </div>
        </form>
        <button type="button" class="btn btn-sm btn-outline-secondary tema-toggle" data-tema-toggle aria-label="Cambiar a modo claro" title="Cambiar a modo claro"><i aria-hidden="true" class="bi bi-sun"></i></button>
        <!-- Carrito -->
        <a href="<?=APP_URL?>/?action=carrito" class="btn btn-sm btn-outline-secondary cart-btn" aria-label="Carrito, <?=(int)$cartCount?> pieza<?=$cartCount!==1?"s":""?>">
          <i aria-hidden="true" class="bi bi-cart3"></i>
          <?php if($cartCount>0): ?><span class="cart-count" aria-hidden="true"><?=$cartCount?></span><?php endif; ?>
        </a>
        <?php if(!empty($_SESSION["user"])): ?>
          <?php $roles=$_SESSION["user_roles"]??[]; $isAdmin=array_intersect(["Administrador","Vendedor","Consultor"],$roles); ?>
          <?php if($isAdmin): ?>
            <a href="<?=APP_URL?>/admin/" class="btn btn-sm btn-gold"><i aria-hidden="true" class="bi bi-speedometer2 me-1"></i>Admin</a>
          <?php else: ?>
            <div class="dropdown">
              <button class="btn btn-sm btn-outline-warning dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i aria-hidden="true" class="bi bi-person-circle me-1"></i><?=htmlspecialchars(explode(" ",$_SESSION["user"]["nombre"])[0])?>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?=APP_URL?>/?action=mi_perfil"><i aria-hidden="true" class="bi bi-person-gear me-2"></i>Mi Perfil</a></li>
                <li><a class="dropdown-item" href="<?=APP_URL?>/?action=mis_pedidos"><i aria-hidden="true" class="bi bi-bag-check me-2"></i>Mis Pedidos</a></li>
                <li><hr class="dropdown-divider" style="border-color:var(--border)"></li>
                <li><a class="dropdown-item text-danger" href="<?=APP_URL?>/logout.php"><i aria-hidden="true" class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
              </ul>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <a href="<?=APP_URL?>/login.php" class="btn btn-sm btn-outline-warning"><i aria-hidden="true" class="bi bi-box-arrow-in-right me-1"></i>Iniciar sesión</a>
          <a href="<?=APP_URL?>/registro.php" class="btn btn-sm btn-outline-secondary btn-crear-cuenta"><i aria-hidden="true" class="bi bi-person-plus me-1"></i>Crear cuenta</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
<main id="contenido" tabindex="-1">
<?php if(!empty($_SESSION["flash"])){$f=$_SESSION["flash"];unset($_SESSION["flash"]);$rol=in_array($f["type"],["danger","warning"],true)?"alert":"status";echo"<div class=\"container-xl mt-3\"><div class=\"alert alert-{$f["type"]} alert-dismissible fade show\" role=\"{$rol}\">".htmlspecialchars($f["msg"])."<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"Cerrar aviso\"></button></div></div>";} ?>
