<!DOCTYPE html><html lang="es" data-theme="dark" data-bs-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Crear Cuenta — <?=Marca::h("nombre")?></title>
<?php require __DIR__ . "/../layouts/recursos_head.php"; ?>
<link rel="stylesheet" href="<?=Assets::url("css/style.css")?>">
<link rel="stylesheet" href="<?=Assets::url("css/extra.css")?>">
<link rel="stylesheet" href="<?=Assets::url("css/base.css")?>">
</head>
<body class="login-body d-flex align-items-center justify-content-center min-vh-100 p-3">
<a class="visually-hidden-focusable skip-link" href="#contenido">Saltar al contenido</a>
<main class="login-card" id="contenido" tabindex="-1">
  <div class="text-center mb-4">
    <div class="login-icon"><i aria-hidden="true" class="bi bi-person-plus"></i></div>
    <h1 class="h3 fw-bold text-white mb-0">Crear Cuenta</h1>
    <p class="text-secondary small mt-1">Regístrate para comprar y hacer seguimiento a tus pedidos</p>
  </div>
  <?php if($err): ?>
  <div role="alert" class="alert-dark-danger d-flex align-items-center gap-2 mb-3 small"><i aria-hidden="true" class="bi bi-shield-exclamation text-danger fs-5"></i><?=htmlspecialchars($err)?></div>
  <?php endif; ?>
  <form action="<?=APP_URL?>/registro.php" method="POST">
    <?=CsrfToken::field()?>
    <div class="mb-3">
      <label for="f-nombre" class="form-label-dark">Nombre completo</label>
      <div class="input-group">
        <span class="input-group-text"><i aria-hidden="true" class="bi bi-person text-warning"></i></span>
        <input id="f-nombre" type="text" name="nombre" class="form-control dark-input" required placeholder="Tu nombre" autocomplete="name">
      </div>
    </div>
    <div class="mb-3">
      <label for="f-email" class="form-label-dark">Correo Electrónico</label>
      <div class="input-group">
        <span class="input-group-text"><i aria-hidden="true" class="bi bi-envelope text-warning"></i></span>
        <input id="f-email" type="email" name="email" class="form-control dark-input" required placeholder="tu@correo.com" autocomplete="email">
      </div>
    </div>
    <div class="mb-3">
      <label for="f-telefono" class="form-label-dark">Teléfono <span class="text-secondary fw-normal">(opcional)</span></label>
      <div class="input-group">
        <span class="input-group-text"><i aria-hidden="true" class="bi bi-telephone text-warning"></i></span>
        <input id="f-telefono" type="tel" name="telefono" class="form-control dark-input" placeholder="6000-0000" autocomplete="tel">
      </div>
    </div>
    <div class="mb-4">
      <label for="pf" class="form-label-dark">Contraseña <span class="text-secondary fw-normal">(mínimo <?=Validator::PASS_MIN?> caracteres, máximo <?=Validator::PASS_MAX?> bytes; una frase larga funciona bien)</span></label>
      <div class="input-group">
        <span class="input-group-text"><i aria-hidden="true" class="bi bi-lock text-warning"></i></span>
        <input type="password" name="password" id="pf" class="form-control dark-input" required minlength="<?=Validator::PASS_MIN?>" maxlength="<?=Validator::PASS_MAX?>" autocomplete="new-password">
        <button class="btn btn-outline-secondary" type="button" aria-label="Mostrar u ocultar la contraseña" aria-controls="pf" onclick="var f=document.getElementById('pf');f.type=f.type==='password'?'text':'password'"><i aria-hidden="true" class="bi bi-eye"></i></button>
      </div>
    </div>
    <button type="submit" class="btn btn-gold w-100 fw-bold py-2"><i aria-hidden="true" class="bi bi-person-check me-2"></i>Crear mi cuenta</button>
  </form>
  <div class="text-center mt-3">
    <span class="text-secondary small">¿Ya tienes cuenta?</span>
    <a href="<?=APP_URL?>/login.php" class="text-warning small fw-semibold"><i aria-hidden="true" class="bi bi-box-arrow-in-right me-1"></i>Iniciar sesión</a>
  </div>
  <div class="text-center mt-3">
    <a href="<?=APP_URL?>/" class="text-secondary small"><i aria-hidden="true" class="bi bi-arrow-left me-1"></i>Volver a la tienda</a>
  </div>
</main>
</body></html>
