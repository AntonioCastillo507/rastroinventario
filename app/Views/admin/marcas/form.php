<?php $edit=!empty($id); $d=$marca??[]; $title=$edit?"Editar marca":"Nueva marca"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="form-panel form-panel-producto">
<div class="pagina-cabeza">
  <div class="pagina-cabeza-titulo">
    <a href="<?=APP_URL?>/admin/?mod=marcas" class="enlace-volver"><i aria-hidden="true" class="bi bi-arrow-left"></i>Volver a marcas</a>
    <h1 class="panel-titulo"><?=$title?></h1>
  </div>
  <div class="pagina-acciones">
    <a href="<?=APP_URL?>/admin/?mod=marcas" class="btn btn-outline-secondary">Cancelar</a>
    <button type="submit" form="form-marca" class="btn btn-gold fw-bold"><?=$edit?"Guardar cambios":"Guardar marca"?></button>
  </div>
</div>
<?php if($error??""): ?><div role="alert" class="alert alert-danger d-flex align-items-center gap-2 mb-4"><i aria-hidden="true" class="bi bi-exclamation-triangle-fill"></i><span><?=htmlspecialchars($error)?></span></div><?php endif; ?>
<form id="form-marca" action="<?=APP_URL?>/admin/?mod=marcas&action=<?=$edit?"editar&id=".(int)$id:"crear"?>" method="POST" enctype="multipart/form-data">
  <?=CsrfToken::field()?>
  <div class="producto-principal">
    <section class="form-card" aria-labelledby="mf-info">
      <h2 class="form-subtitulo" id="mf-info">Información</h2>
      <label for="f-nombre" class="form-label-dark">Nombre de la marca *</label>
      <input id="f-nombre" type="text" name="nombre" class="form-control dark-input" required maxlength="<?=MarcaAutoModel::NOMBRE_MAX?>" value="<?=htmlspecialchars($d["nombre"]??"")?>">
    </section>
    <section class="form-card" aria-labelledby="mf-logo">
      <h2 class="form-subtitulo" id="mf-logo">Logo</h2>
      <div class="foto-campo" data-logo-previa data-max-raster="<?=LogoMarca::MAX_BYTES_RASTER?>" data-max-svg="<?=Logo::MAX_BYTES_MARCA?>">
        <div class="foto-campo-datos">
          <label for="f-logo" class="form-label-dark">Archivo del logo</label>
          <input id="f-logo" type="file" name="logo" class="form-control dark-input" accept=".png,.webp,.svg,image/png,image/webp,image/svg+xml" aria-describedby="f-logo-ayuda">
          <p class="foto-ayuda" id="f-logo-ayuda">PNG o WebP con fondo transparente (máx. <?=intdiv(LogoMarca::MAX_BYTES_RASTER,1024)?> KB), o SVG sencillo (máx. <?=intdiv(Logo::MAX_BYTES_MARCA,1024)?> KB). Se muestra en una sola tinta, así que sirve cualquier color. Sin logo, la tienda usa el monograma.</p>
          <div class="small text-secondary" data-logo-info><?=!empty($d["logo"])?"Sube uno nuevo para reemplazar el actual.":"Aún no has elegido un logo."?></div>
          <div data-logo-avisos class="small" role="status" aria-live="polite"></div>
        </div>
        <div class="foto-campo-previa">
          <div class="text-secondary small mb-1" data-logo-titulo><?=!empty($d["logo"])?"Logo actual":"Vista previa"?></div>
          <div data-logo-casilla><?php $mlNombre=$d["nombre"]??""; $mlLogo=$d["logo"]??null; $mlClase=""; include BASE_PATH."/app/Views/layouts/marca_logo.php"; ?></div>
        </div>
      </div>
    </section>
  </div>
</form>
</div>
<script src="<?=Assets::url("js/marca_logo.js")?>"></script>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
