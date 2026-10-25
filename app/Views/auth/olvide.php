<!DOCTYPE html><html lang="es" data-theme="dark" data-bs-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Recuperar contraseña — Rastro del Istmo</title>
<?php require __DIR__ . "/../layouts/recursos_head.php"; ?>
<link rel="stylesheet" href="<?=APP_URL?>/assets/css/style.css">
<link rel="stylesheet" href="<?=APP_URL?>/assets/css/base.css">
</head>
<body class="login-body d-flex align-items-center justify-content-center min-vh-100 p-3">
<div class="login-card">
  <div class="text-center mb-4">
    <div class="login-icon"><i class="bi bi-key"></i></div>
    <h3 class="fw-bold text-white mb-0">Recuperar contraseña</h3>
    <p class="text-secondary small mt-1">Te enviaremos un enlace para elegir una nueva</p>
  </div>
  <?php if($err): ?>
  <div class="alert-dark-danger d-flex align-items-center gap-2 mb-3 small"><i class="bi bi-shield-exclamation text-danger fs-5"></i><?=htmlspecialchars($err)?></div>
  <?php endif; ?>
  <?php if($ok): ?>
  <div class="alert-dark-info d-flex align-items-center gap-2 mb-3 small"><i class="bi bi-envelope-check text-info fs-5"></i><?=htmlspecialchars($ok)?></div>
  <?php else: ?>
  <form action="<?=APP_URL?>/olvide.php" method="POST">
    <?=CsrfToken::field()?>
    <div class="mb-4">
      <label class="form-label-dark">Correo Electrónico</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-envelope text-warning"></i></span>
        <input type="email" name="email" class="form-control dark-input" required maxlength="150" placeholder="tu@correo.com" autocomplete="email">
      </div>
    </div>
    <button type="submit" class="btn btn-gold w-100 fw-bold py-2"><i class="bi bi-send me-2"></i>Enviar enlace</button>
  </form>
  <?php endif; ?>
  <div class="text-center mt-3">
    <a href="<?=APP_URL?>/login.php" class="text-warning small fw-semibold"><i class="bi bi-box-arrow-in-right me-1"></i>Volver a iniciar sesión</a>
  </div>
</div>
</body></html>
