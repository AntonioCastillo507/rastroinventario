<?php
if(empty($title))$title="Panel Admin";
// Opción activa del menú: el módulo que enruta admin/index.php (?mod=). Los cuatro primeros más Usuarios van siempre
// a la vista; el resto, en "Más" desde 1200 px y en la lista del menú plegable por debajo.
$modActivo = (string)($_GET["mod"] ?? "dashboard");
$menuPrincipal = [["dashboard","Dashboard","speedometer2","/admin/"],["partes","Partes","box-seam","/admin/?mod=partes"],["pedidos","Pedidos","bag-check","/admin/?mod=pedidos"],["ventas","Ventas","cart-check","/admin/?mod=ventas"],["usuarios","Usuarios","people","/admin/?mod=usuarios"]];
$menuMas = [["autos","Autos","car-front","/admin/?mod=autos"],["secciones","Secciones","diagram-3","/admin/?mod=secciones"],["roles","Roles","shield-lock","/admin/?mod=roles"],["comentarios","Comentarios","chat-dots","/admin/?mod=comentarios"],["reportes","Reportes","file-earmark-bar-graph","/admin/?mod=reportes"]];
$masActivo = in_array($modActivo, array_column($menuMas, 0), true);
?>
<!DOCTYPE html><html lang="es" data-theme="dark" data-bs-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($title)?> — <?=Marca::h("nombre")?></title>
<?php require __DIR__ . "/recursos_head.php"; ?>
<link rel="stylesheet" href="<?=Assets::url("css/style.css")?>">
<link rel="stylesheet" href="<?=Assets::url("css/extra.css")?>">
<link rel="stylesheet" href="<?=Assets::url("css/base.css")?>">
</head>
<body class="admin-body">
<a class="visually-hidden-focusable skip-link" href="#contenido">Saltar al contenido</a>
<nav class="navbar navbar-expand-xl admin-nav px-3" aria-label="Panel">
  <a class="navbar-brand d-flex align-items-center gap-2" href="<?=APP_URL?>/admin/">
<?php if(Marca::url("logo")!==""): ?><img src="<?=htmlspecialchars(Marca::url("logo"))?>" height="28" alt="" onerror="this.style.display='none'"><?php endif; ?>
    <span class="fw-bold text-white"><?=Marca::h("nombre")?></span>
  </a>
  <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navA" aria-controls="navA" aria-expanded="false" aria-label="Abrir o cerrar el menú"><span class="navbar-toggler-icon" aria-hidden="true"></span></button>
  <div class="collapse navbar-collapse" id="navA">
<ul class="navbar-nav me-auto ms-3 gap-1">
      <?php foreach($menuPrincipal as [$clave,$texto,$icono,$ruta]): $act=$modActivo===$clave; ?>
      <li class="nav-item"><a class="nav-link<?=$act?" active":""?>" href="<?=APP_URL.htmlspecialchars($ruta)?>"<?=$act?' aria-current="page"':""?>><i aria-hidden="true" class="bi bi-<?=htmlspecialchars($icono)?> me-1"></i><?=htmlspecialchars($texto)?></a></li>
      <?php endforeach; ?>
      <?php foreach($menuMas as [$clave,$texto,$icono,$ruta]): $act=$modActivo===$clave; ?>
      <li class="nav-item d-xl-none"><a class="nav-link<?=$act?" active":""?>" href="<?=APP_URL.htmlspecialchars($ruta)?>"<?=$act?' aria-current="page"':""?>><i aria-hidden="true" class="bi bi-<?=htmlspecialchars($icono)?> me-1"></i><?=htmlspecialchars($texto)?></a></li>
      <?php endforeach; ?>
      <li class="nav-item dropdown d-none d-xl-block">
        <a class="nav-link dropdown-toggle<?=$masActivo?" active":""?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Más</a>
        <ul class="dropdown-menu">
          <?php foreach($menuMas as [$clave,$texto,$icono,$ruta]): $act=$modActivo===$clave; ?>
          <li><a class="dropdown-item<?=$act?" active":""?>" href="<?=APP_URL.htmlspecialchars($ruta)?>"<?=$act?' aria-current="page"':""?>><i aria-hidden="true" class="bi bi-<?=htmlspecialchars($icono)?> me-2"></i><?=htmlspecialchars($texto)?></a></li>
          <?php endforeach; ?>
        </ul>
      </li>
    </ul>
    <ul class="navbar-nav ms-auto align-items-center gap-2">
      <li class="nav-item"><button type="button" class="btn btn-sm btn-outline-secondary tema-toggle" data-tema-toggle aria-label="Cambiar a modo claro" title="Cambiar a modo claro"><i aria-hidden="true" class="bi bi-sun"></i></button></li>
      <li class="nav-item"><span class="text-warning small fw-semibold"><i aria-hidden="true" class="bi bi-person-circle me-1"></i><?=htmlspecialchars($_SESSION["user"]["nombre"]??"")?></span></li>
      <li class="nav-item"><a href="<?=APP_URL?>/admin/?mod=usuarios&action=mi_clave" class="btn btn-sm btn-outline-warning" title="Cambiar mi contraseña" aria-label="Cambiar mi contraseña"><i aria-hidden="true" class="bi bi-key"></i></a></li>
      <li class="nav-item"><a href="<?=APP_URL?>/" class="btn btn-sm btn-outline-secondary" target="_blank"><i aria-hidden="true" class="bi bi-shop me-1"></i>Tienda</a></li>
      <li class="nav-item"><a href="<?=APP_URL?>/logout.php" class="btn btn-sm btn-danger" aria-label="Cerrar sesión"><i aria-hidden="true" class="bi bi-box-arrow-right"></i></a></li>
    </ul>
  </div>
</nav>
<main id="contenido" tabindex="-1" class="admin-content container-fluid py-4 px-4">
<?php if(!empty($_SESSION["flash"])){$f=$_SESSION["flash"];unset($_SESSION["flash"]);$icons=["success"=>"check-circle-fill","danger"=>"exclamation-triangle-fill","warning"=>"exclamation-circle-fill","info"=>"info-circle-fill"];$ic=$icons[$f["type"]]??"info-circle-fill";$rol=in_array($f["type"],["danger","warning"],true)?"alert":"status";echo"<div class=\"alert alert-{$f["type"]} alert-dismissible fade show d-flex align-items-center gap-2\" role=\"{$rol}\"><i aria-hidden=\"true\" class=\"bi bi-{$ic}\"></i><span>".htmlspecialchars($f["msg"])."</span><button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"Cerrar aviso\"></button></div>";} ?>
