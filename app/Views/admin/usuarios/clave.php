<?php $title="Cambiar Contraseña"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="panel-cabeza">
  <h1 class="panel-titulo">Cambiar mi contraseña</h1>
</div>
<div class="form-panel form-panel-estrecho">
  <div>
    <div class="form-card">
      <?php if($error): ?>
      <div role="alert" class="alert alert-danger d-flex align-items-center gap-2 mb-3">
        <i aria-hidden="true" class="bi bi-shield-exclamation"></i>
        <span><?=htmlspecialchars($error)?></span>
      </div>
      <?php endif; ?>
      <?php if($success): ?>
      <div role="status" class="alert alert-success d-flex align-items-center gap-2 mb-3">
        <i aria-hidden="true" class="bi bi-check-circle-fill"></i>
        <span><?=htmlspecialchars($success)?></span>
      </div>
      <?php endif; ?>
      <form method="POST">
        <?=CsrfToken::field()?>
        <div class="mb-3">
          <label for="f-actual" class="form-label-dark">Contraseña actual</label>
          <input id="f-actual" type="password" name="actual" class="form-control dark-input" required autocomplete="current-password">
        </div>
        <div class="mb-3">
          <label for="f-nueva" class="form-label-dark">Nueva contraseña <span class="text-secondary">(mínimo <?=Validator::PASS_MIN?> caracteres, máximo <?=Validator::PASS_MAX?> bytes; una frase larga funciona bien)</span></label>
          <input id="f-nueva" type="password" name="nueva" class="form-control dark-input" required minlength="<?=Validator::PASS_MIN?>" maxlength="<?=Validator::PASS_MAX?>" autocomplete="new-password">
        </div>
        <div class="mb-4">
          <label for="f-confirmar" class="form-label-dark">Confirmar nueva contraseña</label>
          <input id="f-confirmar" type="password" name="confirmar" class="form-control dark-input" required minlength="<?=Validator::PASS_MIN?>" maxlength="<?=Validator::PASS_MAX?>" autocomplete="new-password">
        </div>
        <div class="form-acciones"><button type="submit" class="btn btn-gold fw-bold"><i aria-hidden="true" class="bi bi-save me-2"></i>Actualizar contraseña</button></div>
      </form>
    </div>
  </div>
</div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
