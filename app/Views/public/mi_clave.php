<?php
$title="Cambiar contraseña"; $cuentaActiva="clave";
include BASE_PATH."/app/Views/layouts/cuenta_abrir.php";
?>
<h1 class="cuenta-titulo">Cambiar contraseña</h1>
<?php if($error??""): ?><div role="alert" class="alert-dark-danger d-flex gap-2 mb-3"><i aria-hidden="true" class="bi bi-exclamation-triangle-fill text-danger"></i><?=htmlspecialchars($error)?></div><?php endif; ?>
<?php if($success??""): ?><div role="status" class="alert alert-success mb-3"><?=htmlspecialchars($success)?></div><?php endif; ?>
<form action="<?=APP_URL?>/?action=mi_clave" method="POST" class="cuenta-tarjeta">
  <?=CsrfToken::field()?>
  <div class="mb-3"><label for="f-actual" class="form-label-dark">Contraseña actual</label><input id="f-actual" type="password" name="actual" class="form-control dark-input" required autocomplete="current-password"></div>
  <div class="mb-3"><label for="f-nueva" class="form-label-dark">Nueva contraseña</label><input id="f-nueva" type="password" name="nueva" class="form-control dark-input" required minlength="<?=Validator::PASS_MIN?>" maxlength="<?=Validator::PASS_MAX?>" autocomplete="new-password" aria-describedby="a-nueva"><div class="form-text" id="a-nueva">Mínimo <?=Validator::PASS_MIN?> caracteres, máximo <?=Validator::PASS_MAX?> bytes; una frase larga funciona bien.</div></div>
  <div class="mb-4"><label for="f-confirmar" class="form-label-dark">Confirmar nueva contraseña</label><input id="f-confirmar" type="password" name="confirmar" class="form-control dark-input" required minlength="<?=Validator::PASS_MIN?>" maxlength="<?=Validator::PASS_MAX?>" autocomplete="new-password"></div>
  <button type="submit" class="btn btn-gold fw-bold">Actualizar contraseña</button>
</form>
<?php include BASE_PATH."/app/Views/layouts/cuenta_cerrar.php"; ?>
