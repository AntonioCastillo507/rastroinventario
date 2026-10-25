<?php $edit=!empty($user); $title=$edit?"Editar Usuario":"Nuevo Usuario"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="page-header mb-4">
  <div class="d-flex align-items-center gap-3">
    <a href="<?=APP_URL?>/admin/?mod=usuarios" class="btn-back" aria-label="Volver"><i aria-hidden="true" class="bi bi-arrow-left"></i></a>
    <h1 class="h4 fw-bold text-white mb-0"><?=$title?></h1>
  </div>
</div>
<?php if($error??""): ?><div role="alert" class="alert-dark-danger d-flex gap-2 mb-4"><i aria-hidden="true" class="bi bi-exclamation-triangle-fill text-danger"></i><?=htmlspecialchars($error)?></div><?php endif; ?>
<div class="dark-card" style="max-width:650px"><div class="card-body p-4">
<form action="<?=APP_URL?>/admin/?mod=usuarios&action=<?=$edit?"editar&id=".$id:"crear"?>" method="POST">
  <?=CsrfToken::field()?>
  <div class="row g-4">
    <div class="col-md-6">
      <label for="f-nombre" class="form-label-dark">Nombre Completo *</label>
      <input id="f-nombre" type="text" name="nombre" class="form-control dark-input" required autocomplete="off" value="<?=htmlspecialchars($user["nombre"]??"")?>">
    </div>
    <div class="col-md-6">
      <label for="f-email" class="form-label-dark">Correo Electrónico *</label>
      <input id="f-email" type="email" name="email" class="form-control dark-input" required autocomplete="off" value="<?=htmlspecialchars($user["email"]??"")?>">
    </div>
    <div class="col-md-6">
      <label for="f-password" class="form-label-dark">Contraseña <?=$edit?"<span class=\"text-secondary\">(vacío = sin cambio)</span>":"*"?> <span class="text-secondary small">(mínimo <?=Validator::PASS_MIN?> caracteres, máximo <?=Validator::PASS_MAX?> bytes)</span></label>
      <input id="f-password" type="password" name="password" class="form-control dark-input" minlength="<?=Validator::PASS_MIN?>" maxlength="<?=Validator::PASS_MAX?>" <?=$edit?"":"required"?> autocomplete="new-password">
    </div>
    <div class="col-md-6">
      <div class="form-label-dark" id="roles-etiqueta">Roles del Usuario</div>
      <div class="roles-grid mt-1" role="group" aria-labelledby="roles-etiqueta">
        <?php foreach($roles as $r): ?>
        <label class="role-check">
          <input type="checkbox" name="roles[]" value="<?=$r["id"]?>" <?=in_array($r["id"],$userRoles??[])?"checked":""?> <?=($puedeRoles??false)?"":"disabled"?>>
          <span><?=htmlspecialchars($r["nombre"])?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="mt-4 d-flex gap-3">
    <button type="submit" class="btn btn-gold fw-bold px-4"><i aria-hidden="true" class="bi bi-save me-2"></i>Guardar</button>
    <a href="<?=APP_URL?>/admin/?mod=usuarios" class="btn btn-outline-secondary">Cancelar</a>
  </div>
</form>
</div></div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
