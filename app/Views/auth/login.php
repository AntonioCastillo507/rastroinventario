<?php $err=$_SESSION["login_error"]??""; unset($_SESSION["login_error"]); $viejo=FormViejo::tomar("login"); $okMsg=$_SESSION["login_ok"]??""; unset($_SESSION["login_ok"]); ?>
<!DOCTYPE html><html lang="es" data-theme="dark" data-bs-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Iniciar sesión — <?=Marca::h("nombre")?></title>
<?php require __DIR__ . "/../layouts/recursos_head.php"; ?>
<link rel="stylesheet" href="<?=Assets::url("css/style.css")?>">
<link rel="stylesheet" href="<?=Assets::url("css/extra.css")?>">
<link rel="stylesheet" href="<?=Assets::url("css/base.css")?>">
</head>
<body class="login-body d-flex align-items-center justify-content-center min-vh-100 p-3">
<a class="visually-hidden-focusable skip-link" href="#contenido">Saltar al contenido</a>
<main class="login-card" id="contenido" tabindex="-1">
  <div class="acceso-cabeza mb-4">
    <?php $accesoIcono = true; include __DIR__ . "/../layouts/acceso_marca.php"; ?>
    <h1 class="h3 fw-bold text-white mb-0">Iniciar sesión</h1>
    <p class="text-secondary small mt-1">Ingresa a tu cuenta para continuar</p>
  </div>
  <?php if($err): ?>
  <div role="alert" class="alert-dark-danger d-flex align-items-center gap-2 mb-3 small"><i aria-hidden="true" class="bi bi-shield-exclamation text-danger fs-5"></i><?=htmlspecialchars($err)?></div>
  <?php endif; ?>
  <?php if($okMsg): ?>
  <div role="status" class="alert-dark-info d-flex align-items-center gap-2 mb-3 small"><i aria-hidden="true" class="bi bi-check-circle icon-info fs-5"></i><?=htmlspecialchars($okMsg)?></div>
  <?php endif; ?>
  <form action="<?=APP_URL?>/login.php" method="POST">
    <?=CsrfToken::field()?>
    <div class="mb-3">
      <label for="f-email" class="form-label-dark">Correo electrónico</label>
      <input id="f-email" type="email" name="email" class="form-control dark-input" required placeholder="tu@correo.com" autocomplete="username" value="<?=htmlspecialchars($viejo["email"]??"")?>">
    </div>
    <div class="mb-4">
      <div class="d-flex justify-content-between align-items-baseline"><label class="form-label-dark" for="pf">Contraseña</label><a href="<?=APP_URL?>/olvide.php" class="acceso-enlace text-warning small">¿Olvidaste tu contraseña?</a></div>
      <div class="input-group">
        <input type="password" name="password" id="pf" class="form-control dark-input" required autocomplete="current-password">
        <button class="btn btn-outline-secondary" type="button" aria-label="Mostrar u ocultar la contraseña" aria-controls="pf" onclick="var f=document.getElementById('pf');f.type=f.type==='password'?'text':'password'"><i aria-hidden="true" class="bi bi-eye"></i></button>
      </div>
    </div>
    <button type="submit" class="btn btn-gold w-100 fw-bold py-2">Iniciar sesión</button>
  </form>
  <div class="mt-3">
    <span class="text-secondary small">¿No tienes cuenta?</span>
    <a href="<?=APP_URL?>/registro.php" class="acceso-enlace text-warning small fw-semibold">Crear cuenta</a>
  </div>
  <div>
    <a href="<?=APP_URL?>/" class="acceso-enlace text-secondary small">Volver a la tienda</a>
  </div>
</main>
<script src="<?=Assets::url("js/envio.js")?>"></script>
</body></html>
