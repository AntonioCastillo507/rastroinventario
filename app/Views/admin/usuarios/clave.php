<?php $title="Cambiar Contraseña"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="dark-card p-4">
      <h5 class="fw-bold text-white mb-4"><i class="bi bi-key-fill text-warning me-2"></i>Cambiar mi Contraseña</h5>
      <?php if($error): ?>
      <div class="alert-dark-danger d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-shield-exclamation text-danger"></i>
        <span class="small"><?=htmlspecialchars($error)?></span>
      </div>
      <?php endif; ?>
      <?php if($success): ?>
      <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-check-circle-fill"></i>
        <span class="small"><?=htmlspecialchars($success)?></span>
      </div>
      <?php endif; ?>
      <form method="POST">
        <?=CsrfToken::field()?>
        <div class="mb-3">
          <label class="form-label text-secondary small">CONTRASEÑA ACTUAL</label>
          <input type="password" name="actual" class="form-control dark-input" required autocomplete="current-password">
        </div>
        <div class="mb-3">
          <label class="form-label text-secondary small">NUEVA CONTRASEÑA <span class="text-muted">(mínimo <?=Validator::PASS_MIN?> caracteres, máximo <?=Validator::PASS_MAX?> bytes; una frase larga funciona bien)</span></label>
          <input type="password" name="nueva" class="form-control dark-input" required minlength="<?=Validator::PASS_MIN?>" maxlength="<?=Validator::PASS_MAX?>" autocomplete="new-password">
        </div>
        <div class="mb-4">
          <label class="form-label text-secondary small">CONFIRMAR NUEVA CONTRASEÑA</label>
          <input type="password" name="confirmar" class="form-control dark-input" required minlength="<?=Validator::PASS_MIN?>" maxlength="<?=Validator::PASS_MAX?>" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-gold w-100 fw-bold py-2"><i class="bi bi-save me-2"></i>Actualizar Contraseña</button>
      </form>
    </div>
  </div>
</div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
