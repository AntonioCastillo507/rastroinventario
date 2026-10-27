<?php
if(empty($title))$title="Panel Admin";
// Módulo activo: el que enruta admin/index.php (?mod=). "Mi contraseña" es de la cuenta, no de un módulo.
$modActivo = (string)($_GET["mod"] ?? "dashboard");
$esMiClave = $modActivo==="usuarios" && (string)($_GET["action"] ?? "")==="mi_clave";
// Menú lateral agrupado: [grupo, [[clave, texto, icono, ruta, permiso que lo muestra (null = siempre)]]]
$menuGrupos = [
  ["General",        [["dashboard","Dashboard","speedometer2","/admin/",null]]],
  ["Catálogo",       [["partes","Partes","box-seam","/admin/?mod=partes","partes"],["autos","Autos","car-front","/admin/?mod=autos","autos"],["secciones","Secciones","diagram-3","/admin/?mod=secciones","secciones"]]],
  ["Ventas",         [["pedidos","Pedidos","bag-check","/admin/?mod=pedidos","pedidos"],["ventas","Ventas","cart-check","/admin/?mod=ventas","ventas"],["reportes","Reportes","file-earmark-bar-graph","/admin/?mod=reportes","ventas"],["comentarios","Comentarios","chat-dots","/admin/?mod=comentarios","comentarios"]]],
  ["Administración", [["usuarios","Usuarios","people","/admin/?mod=usuarios","usuarios"],["roles","Roles","shield-lock","/admin/?mod=roles","roles"],["seguridad","Seguridad","shield-check","/admin/?mod=seguridad","seguridad"]]],
];
$uidPanel = (int)($_SESSION["user"]["id"] ?? 0);
$modulosVisibles = $uidPanel ? (new UserModel())->modulosVisibles($uidPanel) : [];
$tituloPanel = $esMiClave ? "Mi contraseña" : $title;
foreach ($menuGrupos as $pgi => $pg) {
  $pg[1] = array_values(array_filter($pg[1], fn($pm) => $pm[4] === null || in_array($pm[4], $modulosVisibles, true)));
  $menuGrupos[$pgi] = $pg;
  foreach ($pg[1] as $pm) if (!$esMiClave && $pm[0] === $modActivo) $tituloPanel = $pm[1];
}
$menuGrupos = array_values(array_filter($menuGrupos, fn($pg) => $pg[1] !== []));
unset($pgi, $pg, $pm);
$nombrePanel = (string)($_SESSION["user"]["nombre"] ?? "");
$inicialPanel = mb_strtoupper(mb_substr($nombrePanel !== "" ? $nombrePanel : "?", 0, 1));
?>
<!DOCTYPE html><html lang="es" data-theme="dark" data-bs-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($title)?> — <?=Marca::h("nombre")?></title>
<?php require __DIR__ . "/recursos_head.php"; ?>
<link rel="stylesheet" href="<?=Assets::url("css/style.css")?>">
<link rel="stylesheet" href="<?=Assets::url("css/extra.css")?>">
<link rel="stylesheet" href="<?=Assets::url("css/base.css")?>">
<link rel="stylesheet" href="<?=Assets::url("css/panel.css")?>">
<link rel="stylesheet" href="<?=Assets::url("css/lista.css")?>">
<link rel="stylesheet" href="<?=Assets::url("css/dashboard.css")?>">
<script src="<?=Assets::url("js/panel.js")?>"></script>
</head>
<body class="admin-body">
<a class="visually-hidden-focusable skip-link" href="#contenido">Saltar al contenido</a>
<div class="admin-shell">
  <aside class="offcanvas-lg offcanvas-start admin-sidebar" tabindex="-1" id="panelMenu" aria-labelledby="panelMenuTitulo">
    <div class="admin-sidebar-cabeza">
      <a class="marca marca-panel" id="panelMenuTitulo" href="<?=APP_URL?>/admin/"><?php $marcaLema=false; include __DIR__."/marca_bloque.php"; ?></a>
      <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#panelMenu" aria-label="Cerrar el menú"></button>
    </div>
    <nav class="admin-sidebar-nav" aria-label="Módulos del panel">
      <?php foreach($menuGrupos as $pgi => [$pgrupo,$pmodulos]): ?>
      <div class="admin-grupo">
        <p class="admin-grupo-titulo" id="grupo<?=(int)$pgi?>"><?=htmlspecialchars($pgrupo)?></p>
        <ul class="admin-grupo-lista" aria-labelledby="grupo<?=(int)$pgi?>">
          <?php foreach($pmodulos as [$pclave,$ptexto,$picono,$pruta]): $pact=!$esMiClave && $modActivo===$pclave; ?>
          <li><a class="admin-enlace<?=$pact?" activo":""?>" href="<?=APP_URL.htmlspecialchars($pruta)?>"<?=$pact?' aria-current="page"':""?>><i aria-hidden="true" class="bi bi-<?=htmlspecialchars($picono)?>"></i><span class="admin-enlace-texto"><?=htmlspecialchars($ptexto)?></span></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endforeach; ?>
    </nav>
    <div class="admin-sidebar-pie d-none d-lg-block">
      <button type="button" class="admin-enlace admin-colapsar" data-panel-menu-toggle aria-expanded="true" aria-controls="panelMenu"><i aria-hidden="true" class="bi bi-layout-sidebar"></i><span class="admin-enlace-texto">Contraer menú</span></button>
    </div>
  </aside>
  <div class="admin-main">
    <header class="admin-topbar">
      <button type="button" class="admin-icono d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#panelMenu" aria-controls="panelMenu" aria-label="Abrir el menú"><i aria-hidden="true" class="bi bi-list"></i></button>
      <p class="admin-topbar-titulo"><?=htmlspecialchars($tituloPanel)?></p>
      <div class="admin-topbar-acciones">
        <button type="button" class="admin-icono tema-toggle" data-tema-toggle aria-label="Cambiar a modo claro" title="Cambiar a modo claro"><i aria-hidden="true" class="bi bi-sun"></i></button>
        <div class="dropdown">
          <button type="button" class="admin-cuenta" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menú de la cuenta de <?=htmlspecialchars($nombrePanel)?>">
            <span class="admin-cuenta-inicial" aria-hidden="true"><?=htmlspecialchars($inicialPanel)?></span>
            <span class="admin-cuenta-nombre"><?=htmlspecialchars($nombrePanel)?></span>
            <i aria-hidden="true" class="bi bi-chevron-down admin-cuenta-flecha"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end admin-cuenta-menu">
            <li><a class="dropdown-item" href="<?=APP_URL?>/admin/?mod=usuarios&amp;action=mi_clave"<?=$esMiClave?' aria-current="page"':""?>><i aria-hidden="true" class="bi bi-key me-2"></i>Cambiar mi contraseña</a></li>
            <li><a class="dropdown-item" href="<?=APP_URL?>/"><i aria-hidden="true" class="bi bi-shop me-2"></i>Ver la tienda</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><form method="post" action="<?=APP_URL?>/logout.php" class="m-0"><?=CsrfToken::field()?><button type="submit" class="dropdown-item" data-no-bloquear><i aria-hidden="true" class="bi bi-box-arrow-right me-2"></i>Salir</button></form></li>
          </ul>
        </div>
      </div>
    </header>
<main id="contenido" tabindex="-1" class="admin-contenido">
<?php if(!empty($_SESSION["flash"])){$f=$_SESSION["flash"];unset($_SESSION["flash"]);$icons=["success"=>"check-circle-fill","danger"=>"exclamation-triangle-fill","warning"=>"exclamation-circle-fill","info"=>"info-circle-fill"];$ic=$icons[$f["type"]]??"info-circle-fill";$rol=in_array($f["type"],["danger","warning"],true)?"alert":"status";echo"<div class=\"alert alert-{$f["type"]} alert-dismissible fade show d-flex align-items-center gap-2\" role=\"{$rol}\"><i aria-hidden=\"true\" class=\"bi bi-{$ic}\"></i><span>".htmlspecialchars($f["msg"])."</span><button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"Cerrar aviso\"></button></div>";} ?>
