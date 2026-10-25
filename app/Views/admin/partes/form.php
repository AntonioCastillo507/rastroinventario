<?php $edit=!empty($parte); $title=$edit?"Editar Parte":"Nueva Parte"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="page-header mb-4">
  <div class="d-flex align-items-center gap-3">
    <a href="<?=APP_URL?>/admin/?mod=partes" class="btn-back" aria-label="Volver"><i aria-hidden="true" class="bi bi-arrow-left"></i></a>
    <h1 class="h4 fw-bold text-white mb-0"><?=$title?></h1>
  </div>
</div>
<?php if($error??""): ?><div role="alert" class="alert-dark-danger d-flex gap-2 mb-4"><i aria-hidden="true" class="bi bi-exclamation-triangle-fill text-danger"></i><?=htmlspecialchars($error)?></div><?php endif; ?>
<div class="dark-card">
<div class="card-body p-4">
<form action="<?=APP_URL?>/admin/?mod=partes&action=<?=$edit?"editar&id=".$id:"crear"?>" method="POST" enctype="multipart/form-data">
  <?=CsrfToken::field()?>
  <div class="row g-4">
    <div class="col-md-8">
      <label for="f-nombre" class="form-label-dark">Nombre de la Parte *</label>
      <input id="f-nombre" type="text" name="nombre" class="form-control dark-input" required maxlength="150" value="<?=htmlspecialchars($parte["nombre"]??"")?>">
    </div>
    <div class="col-md-4">
      <label for="f-precio" class="form-label-dark">Precio ($) *</label>
      <input id="f-precio" type="number" name="precio" class="form-control dark-input" step="0.01" min="0" required value="<?=htmlspecialchars($parte["precio"]??"")?>">
    </div>
    <div class="col-12">
      <label for="f-descripcion" class="form-label-dark">Descripción</label>
      <textarea id="f-descripcion" name="descripcion" class="form-control dark-input" rows="3"><?=htmlspecialchars($parte["descripcion"]??"")?></textarea>
    </div>
    <div class="col-md-4">
      <label for="f-auto-id" class="form-label-dark">Auto (Marca / Modelo / Año)</label>
      <select id="f-auto-id" name="auto_id" class="form-select dark-input">
        <option value="">— Sin especificar —</option>
        <?php foreach($autos as $a): ?>
        <option value="<?=$a["id"]?>" <?=($parte["auto_id"]??"")==$a["id"]?"selected":""?>><?=htmlspecialchars($a["marca"]." ".$a["modelo"]." (".$a["anio"].")")?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label for="f-seccion-id" class="form-label-dark">Sección del Almacén</label>
      <select id="f-seccion-id" name="seccion_id" class="form-select dark-input">
        <option value="">— Sin sección —</option>
        <?php foreach($secciones as $s): ?>
        <option value="<?=$s["id"]?>" <?=($parte["seccion_id"]??"")==$s["id"]?"selected":""?>><?=htmlspecialchars($s["nombre"])?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label for="f-cantidad" class="form-label-dark">Cantidad en Stock *</label>
      <input id="f-cantidad" type="number" name="cantidad" class="form-control dark-input" min="0" required value="<?=htmlspecialchars($parte["cantidad"]??0)?>">
    </div>
    <div class="col-12">
      <label for="f-imagen" class="form-label-dark">Imagen de la Parte</label>
      <input id="f-imagen" type="file" name="imagen" class="form-control dark-input" accept="image/*">
      <div class="text-secondary small mt-1">JPG / PNG / GIF / WebP — máximo 5 MB. Se vuelve a codificar y se genera la miniatura (420×315 px).</div>
      <?php if(ImageHelper::existe($parte["thumbnail"]??null,"thumbnails")): ?>
      <div class="mt-3 d-flex align-items-center gap-3">
        <img src="<?=APP_URL?>/uploads/thumbnails/<?=htmlspecialchars($parte["thumbnail"])?>" class="thumb-preview" alt="Imagen actual">
        <span class="text-secondary small">Imagen actual. Sube una nueva para reemplazarla.</span>
      </div><?php endif; ?>
    </div>
  </div>
  <div class="mt-4 d-flex gap-3">
    <button type="submit" class="btn btn-gold fw-bold px-4">
      <i aria-hidden="true" class="bi bi-save me-2"></i><?=$edit?"Guardar Cambios":"Crear Parte"?>
    </button>
    <a href="<?=APP_URL?>/admin/?mod=partes" class="btn btn-outline-secondary">Cancelar</a>
  </div>
</form>
</div>
</div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
