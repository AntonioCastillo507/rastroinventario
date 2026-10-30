<?php
// $title es el nombre de la pantalla; aquí se le añade la marca
$tituloPagina = empty($title) ? Marca::get("nombre") : $title . " — " . Marca::get("nombre");
// Opción activa del menú: la decide cada vista con $navActivo ("inicio", "catalogo", "nosotros" o "contacto")
// antes de incluir este encabezado.
$navActivo = $navActivo ?? "";
$cartCount = count($_SESSION["cart"]??[]);
?>
<!DOCTYPE html><html lang="es" data-theme="dark" data-bs-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($tituloPagina)?></title>
<?php require __DIR__ . "/recursos_head.php"; ?>
<link rel="stylesheet" href="<?=Assets::url("css/style.css")?>">
<link rel="stylesheet" href="<?=Assets::url("css/extra.css")?>">
<link rel="stylesheet" href="<?=Assets::url("css/base.css")?>">
</head>
<body class="public-body">
<a class="visually-hidden-focusable skip-link" href="#contenido">Saltar al contenido</a>
<nav class="navbar navbar-expand-lg public-nav sticky-top" aria-label="Principal">
  <div class="container-xl nav-fila">
    <a class="navbar-brand marca" href="<?=APP_URL?>/"><?php $marcaLema=true; include __DIR__."/marca_bloque.php"; ?></a>
    <form class="nav-buscador" method="GET" action="<?=APP_URL?>/" role="search">
      <input type="hidden" name="ver" value="catalogo">
      <div class="input-group">
        <input type="search" name="q" class="form-control dark-input nav-search" placeholder="Buscar pieza..." aria-label="Buscar pieza" value="<?=htmlspecialchars($q??"")?>">
        <button class="btn btn-gold" type="submit" aria-label="Buscar"><i aria-hidden="true" class="bi bi-search"></i></button>
      </div>
    </form>
    <a href="<?=APP_URL?>/?action=carrito" class="btn btn-sm btn-outline-secondary cart-btn nav-carrito" aria-label="Carrito, <?=(int)$cartCount?> pieza<?=$cartCount!==1?"s":""?>">
      <i aria-hidden="true" class="bi bi-cart3"></i>
      <?php if($cartCount>0): ?><span class="cart-count" aria-hidden="true"><?=$cartCount?></span><?php endif; ?>
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navP" aria-controls="navP" aria-expanded="false" aria-label="Abrir o cerrar el menú"><span class="navbar-toggler-icon" aria-hidden="true"></span></button>
    <div class="collapse navbar-collapse" id="navP">
      <ul class="navbar-nav me-auto gap-1">
        <?php foreach([["inicio","Inicio","/"],["catalogo","Catálogo","/?ver=catalogo"],["nosotros","Quiénes somos","/?action=nosotros"],["contacto","Contacto","/?action=contacto"]] as [$clave,$texto,$ruta]): $esActiva=$navActivo===$clave; ?>
        <li class="nav-item"><a class="nav-link <?=$esActiva?"active":"text-secondary"?>" href="<?=APP_URL.htmlspecialchars($ruta)?>"<?=$esActiva?' aria-current="page"':""?>><?=htmlspecialchars($texto)?></a></li>
        <?php endforeach; ?>
      </ul>
      <div class="nav-cuenta">
        <button type="button" class="btn btn-sm btn-outline-secondary tema-toggle" data-tema-toggle aria-label="Cambiar a modo claro" title="Cambiar a modo claro"><i aria-hidden="true" class="bi bi-sun"></i></button>
        <?php if(!empty($_SESSION["user"])): ?>
          <?php $roles=$_SESSION["user_roles"]??[]; $isAdmin=array_intersect(["Administrador","Vendedor","Consultor"],$roles); ?>
          <div class="dropdown">
            <button class="btn btn-sm btn-outline-warning dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menú de la cuenta de <?=htmlspecialchars(explode(" ",$_SESSION["user"]["nombre"])[0])?>">
              <i aria-hidden="true" class="bi bi-person-circle me-1"></i><span class="cuenta-nombre"><?=htmlspecialchars(explode(" ",$_SESSION["user"]["nombre"])[0])?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <?php if($isAdmin): ?>
              <li><a class="dropdown-item" href="<?=APP_URL?>/admin/"><i aria-hidden="true" class="bi bi-speedometer2 me-2"></i>Ir al panel</a></li>
              <?php else: ?>
              <li><a class="dropdown-item" href="<?=APP_URL?>/?action=mi_perfil"><i aria-hidden="true" class="bi bi-person-gear me-2"></i>Mi Perfil</a></li>
              <li><a class="dropdown-item" href="<?=APP_URL?>/?action=mis_pedidos"><i aria-hidden="true" class="bi bi-bag-check me-2"></i>Mis Pedidos</a></li>
              <?php endif; ?>
              <li><hr class="dropdown-divider" style="border-color:var(--border)"></li>
              <li><form method="post" action="<?=APP_URL?>/logout.php" class="m-0"><?=CsrfToken::field()?><button type="submit" class="dropdown-item item-peligro" data-no-bloquear><i aria-hidden="true" class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</button></form></li>
            </ul>
          </div>
        <?php else: ?>
          <a href="<?=APP_URL?>/login.php" class="nav-link nav-iniciar">Iniciar sesión</a>
          <a href="<?=APP_URL?>/registro.php" class="btn btn-sm btn-outline-secondary btn-crear-cuenta d-none d-xl-inline-flex align-items-center">Crear cuenta</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
<main id="contenido" tabindex="-1">
<?php if(!empty($_SESSION["flash"])){$f=$_SESSION["flash"];unset($_SESSION["flash"]);$rol=in_array($f["type"],["danger","warning"],true)?"alert":"status";echo"<div class=\"container-xl mt-3\"><div class=\"alert alert-{$f["type"]} alert-dismissible fade show\" role=\"{$rol}\">".htmlspecialchars($f["msg"])."<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"Cerrar aviso\"></button></div></div>";} ?>
