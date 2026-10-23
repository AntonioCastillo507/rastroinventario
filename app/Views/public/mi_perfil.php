<?php
$title="Mi Perfil — Rastro del Istmo";
include BASE_PATH."/app/Views/layouts/public_header.php";
?>
<a href="javascript:history.back()" class="btn-float-back"><i class="bi bi-arrow-left"></i></a>
<div class="container-xl py-4">
  <div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">
      <h4 class="fw-bold text-white mb-4"><i class="bi bi-person-gear me-2 text-warning"></i>Mi Perfil</h4>
      <?php if($error??""): ?><div class="alert-dark-danger d-flex gap-2 mb-4"><i class="bi bi-exclamation-triangle-fill text-danger"></i><?=htmlspecialchars($error)?></div><?php endif; ?>
      <?php if($success??""): ?><div class="alert alert-success mb-4"><?=htmlspecialchars($success)?></div><?php endif; ?>
      <div style="background:var(--bg-1);border:1px solid var(--border);border-radius:var(--radius);padding:2rem">
        <form action="<?=APP_URL?>/?action=mi_perfil" method="POST">
          <?=CsrfToken::field()?>
          <div class="mb-3"><label class="form-label-dark">Nombre completo *</label><input type="text" name="nombre" class="form-control dark-input" required value="<?=htmlspecialchars($user["nombre"]??"")?>"></div>
          <div class="mb-3"><label class="form-label-dark">Correo electrónico</label><input type="email" class="form-control dark-input" disabled value="<?=htmlspecialchars($user["email"]??"")?>" style="opacity:.6"><div class="form-text">El correo no se puede cambiar.</div></div>
          <div class="mb-3"><label class="form-label-dark">Teléfono</label><input type="tel" name="telefono" class="form-control dark-input" value="<?=htmlspecialchars($user["telefono"]??"")?>" placeholder="6000-0000"></div>
          <div class="mb-4"><label class="form-label-dark">Dirección de entrega predeterminada</label><textarea name="direccion" class="form-control dark-input auto-exp" rows="2" placeholder="Corregimiento, calle, referencia..."><?=htmlspecialchars($user["direccion"]??"")?></textarea></div>
          <button type="submit" class="btn btn-gold fw-bold w-100"><i class="bi bi-save me-2"></i>Guardar cambios</button>
        </form>
        <hr style="border-color:var(--border)">
        <div class="d-flex gap-2 justify-content-center">
          <a href="<?=APP_URL?>/?action=mis_pedidos" class="btn btn-outline-secondary btn-sm"><i class="bi bi-bag-check me-1"></i>Mis Pedidos</a>
          <a href="<?=APP_URL?>/logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Cerrar sesión</a>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="container-xl">
  <div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">
      <div style="background:var(--bg-1);border:1px solid var(--border);border-radius:var(--radius);padding:2rem;margin-top:1.5rem;margin-bottom:2rem">
        <h5 class="fw-bold text-white mb-3"><i class="bi bi-key-fill me-2 text-warning"></i>Cambiar Contraseña</h5>
        <form action="<?=APP_URL?>/?action=mi_perfil" method="POST">
          <?=CsrfToken::field()?>
          <div class="mb-3"><label class="form-label-dark">Contraseña actual</label><input type="password" name="actual" class="form-control dark-input" required autocomplete="current-password"></div>
          <div class="mb-3"><label class="form-label-dark">Nueva contraseña (mínimo <?=Validator::PASS_MIN?> caracteres, máximo <?=Validator::PASS_MAX?> bytes; una frase larga funciona bien)</label><input type="password" name="nueva" class="form-control dark-input" required minlength="<?=Validator::PASS_MIN?>" maxlength="<?=Validator::PASS_MAX?>" autocomplete="new-password"></div>
          <div class="mb-4"><label class="form-label-dark">Confirmar nueva contraseña</label><input type="password" name="confirmar" class="form-control dark-input" required minlength="<?=Validator::PASS_MIN?>" maxlength="<?=Validator::PASS_MAX?>" autocomplete="new-password"></div>
          <button type="submit" class="btn btn-outline-warning fw-bold w-100"><i class="bi bi-shield-lock me-2"></i>Actualizar Contraseña</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include BASE_PATH."/app/Views/layouts/public_footer.php"; ?>
