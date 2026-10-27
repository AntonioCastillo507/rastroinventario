<?php $edit=!empty($sec); $title=$edit?"Editar Sección":"Nueva Sección"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="panel-cabeza">
  <div>
    <a href="<?=APP_URL?>/admin/?mod=secciones" class="enlace-volver"><i aria-hidden="true" class="bi bi-arrow-left"></i>Volver a secciones</a>
    <h1 class="panel-titulo"><?=$title?></h1>
  </div>
</div>
<?php if($error??""): ?><div role="alert" class="alert alert-danger d-flex align-items-center gap-2 mb-4"><i aria-hidden="true" class="bi bi-exclamation-triangle-fill"></i><span><?=htmlspecialchars($error)?></span></div><?php endif; ?>
<div class="form-panel"><div class="form-card">
<form action="<?=APP_URL?>/admin/?mod=secciones&action=<?=$edit?"editar&id=".$id:"crear"?>" method="POST">
  <?=CsrfToken::field()?>
  <div class="mb-4"><label for="f-nombre" class="form-label-dark">Nombre *</label><input id="f-nombre" type="text" name="nombre" class="form-control dark-input" required value="<?=htmlspecialchars($sec["nombre"]??"")?>"></div>
  <div class="mb-4"><label for="f-descripcion" class="form-label-dark">Descripción</label><textarea id="f-descripcion" name="descripcion" class="form-control dark-input" rows="3"><?=htmlspecialchars($sec["descripcion"]??"")?></textarea></div>
  <div class="form-acciones"><button type="submit" class="btn btn-gold fw-bold px-4"><i aria-hidden="true" class="bi bi-save me-2"></i>Guardar</button><a href="<?=APP_URL?>/admin/?mod=secciones" class="btn btn-outline-secondary">Cancelar</a></div>
</form>
</div></div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
