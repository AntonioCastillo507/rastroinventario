<?php
// Página de error temática. Recibe $codigo, $titulo, $mensaje y $ref (código de referencia, puede ser null). No toca la base de datos.
// Lleva la marca, el tema (oscuro o claro, el que eligió la persona) y una sola acción principal: volver al inicio.
?>
<!DOCTYPE html><html lang="es" data-theme="dark" data-bs-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title><?=htmlspecialchars($titulo)?> — <?=Marca::h("nombre")?></title>
<?php require __DIR__ . "/../layouts/recursos_head.php"; ?>
<link rel="stylesheet" href="<?=Assets::url("css/style.css")?>">
<link rel="stylesheet" href="<?=Assets::url("css/extra.css")?>">
<link rel="stylesheet" href="<?=Assets::url("css/base.css")?>">
</head>
<body class="login-body d-flex align-items-center justify-content-center min-vh-100 p-3">
<a class="visually-hidden-focusable skip-link" href="#contenido">Saltar al contenido</a>
<main class="login-card error-pagina" id="contenido" tabindex="-1">
  <div class="text-center mb-4">
    <?php include __DIR__ . "/../layouts/acceso_marca.php"; ?>
    <p class="error-codigo num mb-1">Error <?=(int)$codigo?></p>
    <h1 class="h3 fw-bold text-white mb-2"><?=htmlspecialchars($titulo)?></h1>
    <p class="text-secondary mb-0"><?=htmlspecialchars($mensaje)?></p>
    <?php if($ref): ?><p class="text-secondary small mt-2 mb-0">Si sigue, indica el código de referencia <b class="num"><?=htmlspecialchars($ref)?></b>.</p><?php endif; ?>
  </div>
  <?php if($codigo === 404): ?>
  <form class="error-buscador" method="GET" action="<?=APP_URL?>/" role="search">
    <input type="hidden" name="ver" value="catalogo">
    <label for="error-q" class="form-label-dark">Buscar una pieza</label>
    <div class="input-group">
      <input id="error-q" type="search" name="q" class="form-control dark-input" placeholder="Puerta, motor, vidrio...">
      <button class="btn btn-outline-secondary" type="submit">Buscar</button>
    </div>
  </form>
  <?php endif; ?>
  <div class="error-acciones">
    <a class="btn btn-gold fw-bold" href="<?=APP_URL?>/">Volver al inicio</a>
    <a class="btn btn-outline-secondary fw-bold" href="<?=APP_URL?>/?ver=catalogo">Ver el catálogo</a>
  </div>
</main>
</body></html>
