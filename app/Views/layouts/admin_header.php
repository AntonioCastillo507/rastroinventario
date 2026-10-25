<?php if(empty($title))$title="Panel Admin"; ?>
<!DOCTYPE html><html lang="es" data-theme="dark" data-bs-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($title)?> — Rastro del Istmo</title>
<?php require __DIR__ . "/recursos_head.php"; ?>
<link rel="stylesheet" href="<?=APP_URL?>/assets/css/style.css">
<link rel="stylesheet" href="<?=APP_URL?>/assets/css/extra.css">
<link rel="stylesheet" href="<?=APP_URL?>/assets/css/base.css">
</head>
<body class="admin-body">
<nav class="navbar navbar-expand-lg admin-nav px-3">
  <a class="navbar-brand d-flex align-items-center gap-2" href="<?=APP_URL?>/admin/">
<img src="<?=APP_URL?>/assets/img/logo.png" height="28" alt="" onerror="this.style.display='none'">
    <span class="fw-bold text-white">Rastro del Istmo</span>
  </a>
  <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navA"><span class="navbar-toggler-icon"></span></button>
  <div class="collapse navbar-collapse" id="navA">
<ul class="navbar-nav me-auto ms-3 gap-1 flex-nowrap">
      <li class="nav-item"><a class="nav-link" href="<?=APP_URL?>/admin/"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
      <li class="nav-item"><a class="nav-link" href="<?=APP_URL?>/admin/?mod=partes"><i class="bi bi-box-seam me-1"></i>Partes</a></li>
      <li class="nav-item"><a class="nav-link" href="<?=APP_URL?>/admin/?mod=pedidos"><i class="bi bi-bag-check me-1"></i>Pedidos</a></li>
      <li class="nav-item"><a class="nav-link" href="<?=APP_URL?>/admin/?mod=ventas"><i class="bi bi-cart-check me-1"></i>Ventas</a></li>
      <li class="nav-item"><a class="nav-link" href="<?=APP_URL?>/admin/?mod=autos"><i class="bi bi-car-front me-1"></i>Autos</a></li>
      <li class="nav-item"><a class="nav-link" href="<?=APP_URL?>/admin/?mod=secciones"><i class="bi bi-diagram-3 me-1"></i>Secciones</a></li>
      <li class="nav-item"><a class="nav-link" href="<?=APP_URL?>/admin/?mod=usuarios"><i class="bi bi-people me-1"></i>Usuarios</a></li>
      <li class="nav-item"><a class="nav-link" href="<?=APP_URL?>/admin/?mod=roles"><i class="bi bi-shield-lock me-1"></i>Roles</a></li>
      <li class="nav-item"><a class="nav-link" href="<?=APP_URL?>/admin/?mod=comentarios"><i class="bi bi-chat-dots me-1"></i>Comentarios</a></li>
      <li class="nav-item"><a class="nav-link text-warning" href="<?=APP_URL?>/admin/?mod=reportes"><i class="bi bi-file-earmark-bar-graph me-1"></i>Reportes</a></li>

    </ul>
    <ul class="navbar-nav ms-auto align-items-center gap-2">
      <li class="nav-item"><button type="button" class="btn btn-sm btn-outline-secondary tema-toggle" data-tema-toggle aria-label="Cambiar a modo claro" title="Cambiar a modo claro"><i class="bi bi-sun" aria-hidden="true"></i></button></li>
      <li class="nav-item"><span class="text-warning small fw-semibold"><i class="bi bi-person-circle me-1"></i><?=htmlspecialchars($_SESSION["user"]["nombre"]??"")?></span></li>
      <li class="nav-item"><a href="<?=APP_URL?>/admin/?mod=usuarios&action=mi_clave" class="btn btn-sm btn-outline-warning" title="Cambiar mi contraseña"><i class="bi bi-key"></i></a></li>
      <li class="nav-item"><a href="<?=APP_URL?>/" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="bi bi-shop me-1"></i>Tienda</a></li>
      <li class="nav-item"><a href="<?=APP_URL?>/logout.php" class="btn btn-sm btn-danger"><i class="bi bi-box-arrow-right"></i></a></li>
    </ul>
  </div>
</nav>
<div class="admin-content container-fluid py-4 px-4">
<?php if(!empty($_SESSION["flash"])){$f=$_SESSION["flash"];unset($_SESSION["flash"]);$icons=["success"=>"check-circle-fill","danger"=>"exclamation-triangle-fill","warning"=>"exclamation-circle-fill","info"=>"info-circle-fill"];$ic=$icons[$f["type"]]??"info-circle-fill";echo"<div class=\"alert alert-{$f["type"]} alert-dismissible fade show d-flex align-items-center gap-2\"><i class=\"bi bi-{$ic}\"></i><span>".htmlspecialchars($f["msg"])."</span><button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button></div>";} ?>
