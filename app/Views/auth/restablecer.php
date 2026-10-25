<!DOCTYPE html><html lang="es" data-theme="dark" data-bs-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="referrer" content="no-referrer">
<title>Nueva contraseña — <?=Marca::h("nombre")?></title>
<?php require __DIR__ . "/../layouts/recursos_head.php"; ?>
<link rel="stylesheet" href="<?=Assets::url("css/style.css")?>">
<link rel="stylesheet" href="<?=Assets::url("css/base.css")?>">
</head>
<body class="login-body d-flex align-items-center justify-content-center min-vh-100 p-3">
<a class="visually-hidden-focusable skip-link" href="#contenido">Saltar al contenido</a>
<main class="login-card" id="contenido" tabindex="-1">
  <div class="text-center mb-4">
    <div class="login-icon"><i aria-hidden="true" class="bi bi-shield-lock"></i></div>
    <h1 class="h3 fw-bold text-white mb-0">Nueva contraseña</h1>
    <?php if($valido): ?><p class="text-secondary small mt-1">Elige una contraseña nueva para tu cuenta</p><?php endif; ?>
  </div>
  <?php if($err): ?>
  <div role="alert" class="alert-dark-danger d-flex align-items-center gap-2 mb-3 small"><i aria-hidden="true" class="bi bi-shield-exclamation text-danger fs-5"></i><?=htmlspecialchars($err)?></div>
  <?php endif; ?>
  <?php if($valido): ?>
  <form action="<?=APP_URL?>/restablecer.php" method="POST">
    <?=CsrfToken::field()?>
    <div class="mb-3">
      <label for="pf" class="form-label-dark">Contraseña nueva <span class="text-secondary fw-normal">(mínimo <?=Validator::PASS_MIN?> caracteres, máximo <?=Validator::PASS_MAX?> bytes; una frase larga funciona bien)</span></label>
      <div class="input-group">
        <span class="input-group-text"><i aria-hidden="true" class="bi bi-lock text-warning"></i></span>
        <input type="password" name="password" id="pf" class="form-control dark-input" required minlength="<?=Validator::PASS_MIN?>" maxlength="<?=Validator::PASS_MAX?>" autocomplete="new-password">
        <button class="btn btn-outline-secondary" type="button" aria-label="Mostrar u ocultar la contraseña" aria-controls="pf" onclick="var f=document.getElementById('pf');f.type=f.type==='password'?'text':'password'"><i aria-hidden="true" class="bi bi-eye"></i></button>
      </div>
    </div>
    <div class="mb-4">
      <label for="f-password2" class="form-label-dark">Repite la contraseña</label>
      <div class="input-group">
        <span class="input-group-text"><i aria-hidden="true" class="bi bi-lock-fill text-warning"></i></span>
        <input id="f-password2" type="password" name="password2" class="form-control dark-input" required minlength="<?=Validator::PASS_MIN?>" maxlength="<?=Validator::PASS_MAX?>" autocomplete="new-password">
      </div>
    </div>
    <button type="submit" class="btn btn-gold w-100 fw-bold py-2"><i aria-hidden="true" class="bi bi-check2-circle me-2"></i>Cambiar contraseña</button>
  </form>
  <?php else: ?>
  <div class="text-center mt-3">
    <a href="<?=APP_URL?>/olvide.php" class="btn btn-gold fw-bold"><i aria-hidden="true" class="bi bi-send me-2"></i>Pedir un enlace nuevo</a>
  </div>
  <?php endif; ?>
  <div class="text-center mt-3">
    <a href="<?=APP_URL?>/login.php" class="text-secondary small"><i aria-hidden="true" class="bi bi-arrow-left me-1"></i>Volver a iniciar sesión</a>
  </div>
</main>
<script src="<?=Assets::url("js/envio.js")?>"></script>
</body></html>
