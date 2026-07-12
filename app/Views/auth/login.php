<?php $err=$_SESSION["login_error"]??""; unset($_SESSION["login_error"]); ?>
<!DOCTYPE html><html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Iniciar Sesión — Rastro del Istmo</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?=APP_URL?>/assets/css/style.css">
</head>
<body class="login-body d-flex align-items-center justify-content-center min-vh-100 p-3">
<div class="login-card">
  <div class="text-center mb-4">
    <div class="login-icon"><i class="bi bi-tools"></i></div>
    <h3 class="fw-bold text-white mb-0">Rastro del Istmo</h3>
    <p class="text-secondary small mt-1">Ingresa a tu cuenta para continuar</p>
  </div>
  <?php if($err): ?>
  <div class="alert-dark-danger d-flex align-items-center gap-2 mb-3 small"><i class="bi bi-shield-exclamation text-danger fs-5"></i><?=htmlspecialchars($err)?></div>
  <?php endif; ?>
  <form action="<?=APP_URL?>/login.php" method="POST">
    <?=CsrfToken::field()?>
    <div class="mb-3">
      <label class="form-label-dark">Correo Electrónico</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-envelope text-warning"></i></span>
        <input type="email" name="email" class="form-control dark-input" required placeholder="tu@correo.com" autocomplete="email">
      </div>
    </div>
    <div class="mb-4">
      <label class="form-label-dark">Contraseña <span class="text-secondary fw-normal">(8-12 caracteres)</span></label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-lock text-warning"></i></span>
        <input type="password" name="password" id="pf" class="form-control dark-input" required minlength="8" maxlength="12" autocomplete="current-password">
        <button class="btn btn-outline-secondary" type="button" onclick="var f=document.getElementById('pf');f.type=f.type==='password'?'text':'password'"><i class="bi bi-eye"></i></button>
      </div>
    </div>
    <button type="submit" class="btn btn-gold w-100 fw-bold py-2"><i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sesión</button>
  </form>
  <div class="text-center mt-3">
    <span class="text-secondary small">¿No tienes cuenta?</span>
    <a href="<?=APP_URL?>/registro.php" class="text-warning small fw-semibold"><i class="bi bi-person-plus me-1"></i>Crear cuenta</a>
  </div>
  <div class="text-center mt-3">
    <a href="<?=APP_URL?>/" class="text-secondary small"><i class="bi bi-arrow-left me-1"></i>Volver a la tienda</a>
  </div>
</div>
</body></html>
