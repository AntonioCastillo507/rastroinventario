<!DOCTYPE html><html lang="es" data-theme="dark" data-bs-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Recuperar contraseña — <?=Marca::h("nombre")?></title>
<?php require __DIR__ . "/../layouts/recursos_head.php"; ?>
<link rel="stylesheet" href="<?=Assets::url("css/style.css")?>">
<link rel="stylesheet" href="<?=Assets::url("css/base.css")?>">
</head>
<body class="login-body d-flex align-items-center justify-content-center min-vh-100 p-3">
<a class="visually-hidden-focusable skip-link" href="#contenido">Saltar al contenido</a>
<main class="login-card" id="contenido" tabindex="-1">
  <div class="text-center mb-4">
    <div class="login-icon"><i aria-hidden="true" class="bi bi-key"></i></div>
    <h1 class="h3 fw-bold text-white mb-0">Recuperar contraseña</h1>
    <p class="text-secondary small mt-1">Te enviaremos un enlace para elegir una nueva</p>
  </div>
  <?php if($err): ?>
  <div role="alert" class="alert-dark-danger d-flex align-items-center gap-2 mb-3 small"><i aria-hidden="true" class="bi bi-shield-exclamation text-danger fs-5"></i><?=htmlspecialchars($err)?></div>
  <?php endif; ?>
  <?php if($ok): ?>
  <div role="status" class="alert-dark-info d-flex align-items-center gap-2 mb-3 small"><i aria-hidden="true" class="bi bi-envelope-check icon-info fs-5"></i><?=htmlspecialchars($ok)?></div>
  <?php else: ?>
  <form action="<?=APP_URL?>/olvide.php" method="POST">
    <?=CsrfToken::field()?>
    <div class="mb-4">
      <label for="f-email" class="form-label-dark">Correo Electrónico</label>
      <div class="input-group">
        <span class="input-group-text"><i aria-hidden="true" class="bi bi-envelope text-warning"></i></span>
        <input id="f-email" type="email" name="email" class="form-control dark-input" required maxlength="150" placeholder="tu@correo.com" autocomplete="email">
      </div>
    </div>
    <button type="submit" class="btn btn-gold w-100 fw-bold py-2"><i aria-hidden="true" class="bi bi-send me-2"></i>Enviar enlace</button>
  </form>
  <?php endif; ?>
  <div class="text-center mt-3">
    <a href="<?=APP_URL?>/login.php" class="text-warning small fw-semibold"><i aria-hidden="true" class="bi bi-box-arrow-in-right me-1"></i>Volver a iniciar sesión</a>
  </div>
</main>
</body></html>
