<?php $edit=!empty($auto); $title=$edit?"Editar Auto":"Nuevo Auto"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="page-header mb-4">
  <div class="d-flex align-items-center gap-3">
    <a href="<?=APP_URL?>/admin/?mod=autos" class="btn-back" aria-label="Volver"><i aria-hidden="true" class="bi bi-arrow-left"></i></a>
    <h1 class="h4 fw-bold text-white mb-0"><?=$title?></h1>
  </div>
</div>
<?php if($error??""): ?><div role="alert" class="alert-dark-danger d-flex gap-2 mb-4"><i aria-hidden="true" class="bi bi-exclamation-triangle-fill text-danger"></i><?=htmlspecialchars($error)?></div><?php endif; ?>
<div class="dark-card" style="max-width:480px"><div class="card-body p-4">
<form action="<?=APP_URL?>/admin/?mod=autos&action=<?=$edit?"editar&id=".$id:"crear"?>" method="POST">
  <?=CsrfToken::field()?>
  <div class="mb-4"><label for="f-marca" class="form-label-dark">Marca *</label><input id="f-marca" type="text" name="marca" class="form-control dark-input" required value="<?=htmlspecialchars($auto["marca"]??"")?>"></div>
  <div class="mb-4"><label for="f-modelo" class="form-label-dark">Modelo *</label><input id="f-modelo" type="text" name="modelo" class="form-control dark-input" required value="<?=htmlspecialchars($auto["modelo"]??"")?>"></div>
  <div class="mb-4"><label for="f-anio" class="form-label-dark">Año *</label><input id="f-anio" type="number" name="anio" class="form-control dark-input" required min="1980" max="2030" value="<?=htmlspecialchars($auto["anio"]??date("Y"))?>"></div>
  <div class="d-flex gap-3"><button type="submit" class="btn btn-gold fw-bold px-4"><i aria-hidden="true" class="bi bi-save me-2"></i>Guardar</button><a href="<?=APP_URL?>/admin/?mod=autos" class="btn btn-outline-secondary">Cancelar</a></div>
</form>
</div></div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
