<?php $edit=!empty($sec); $title=$edit?"Editar Sección":"Nueva Sección"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="page-header mb-4">
  <div class="d-flex align-items-center gap-3">
    <a href="<?=APP_URL?>/admin/?mod=secciones" class="btn-back"><i class="bi bi-arrow-left"></i></a>
    <h4 class="fw-bold text-white mb-0"><?=$title?></h4>
  </div>
</div>
<?php if($error??""): ?><div class="alert-dark-danger d-flex gap-2 mb-4"><i class="bi bi-exclamation-triangle-fill text-danger"></i><?=htmlspecialchars($error)?></div><?php endif; ?>
<div class="dark-card" style="max-width:480px"><div class="card-body p-4">
<form action="<?=APP_URL?>/admin/?mod=secciones&action=<?=$edit?"editar&id=".$id:"crear"?>" method="POST">
  <?=CsrfToken::field()?>
  <div class="mb-4"><label class="form-label-dark">Nombre *</label><input type="text" name="nombre" class="form-control dark-input" required value="<?=htmlspecialchars($sec["nombre"]??"")?>"></div>
  <div class="mb-4"><label class="form-label-dark">Descripción</label><textarea name="descripcion" class="form-control dark-input" rows="3"><?=htmlspecialchars($sec["descripcion"]??"")?></textarea></div>
  <div class="d-flex gap-3"><button type="submit" class="btn btn-gold fw-bold px-4"><i class="bi bi-save me-2"></i>Guardar</button><a href="<?=APP_URL?>/admin/?mod=secciones" class="btn btn-outline-secondary">Cancelar</a></div>
</form>
</div></div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
