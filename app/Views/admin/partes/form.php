<?php $edit=!empty($parte); $d=$edit?$parte:($viejo??[]); $title=$edit?"Editar parte":"Agregar parte al inventario"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="form-panel-producto">
<div class="pagina-cabeza">
  <div class="pagina-cabeza-titulo">
    <a href="<?=APP_URL?>/admin/?mod=partes" class="enlace-volver"><i aria-hidden="true" class="bi bi-arrow-left"></i>Volver a partes</a>
    <h1 class="panel-titulo"><?=$title?></h1>
  </div>
  <div class="pagina-acciones">
    <a href="<?=APP_URL?>/admin/?mod=partes" class="btn btn-outline-secondary">Cancelar</a>
    <button type="submit" form="form-parte" class="btn btn-gold fw-bold"><?=$edit?"Guardar cambios":"Guardar parte"?></button>
  </div>
</div>
<?php if($error??""): ?><div role="alert" class="alert alert-danger d-flex align-items-center gap-2 mb-4"><i aria-hidden="true" class="bi bi-exclamation-triangle-fill"></i><span><?=htmlspecialchars($error)?></span></div><?php endif; ?>
<form id="form-parte" action="<?=APP_URL?>/admin/?mod=partes&action=<?=$edit?"editar&id=".$id:"crear"?>" method="POST" enctype="multipart/form-data">
  <?=CsrfToken::field()?>
  <div class="producto-grid">
    <div class="producto-principal">
      <section class="form-card" aria-labelledby="pf-info">
        <h2 class="form-subtitulo" id="pf-info">Información</h2>
        <div class="producto-campos">
          <div>
            <label for="f-nombre" class="form-label-dark">Nombre de la Parte *</label>
            <input id="f-nombre" type="text" name="nombre" class="form-control dark-input" required maxlength="150" value="<?=htmlspecialchars($d["nombre"]??"")?>">
          </div>
          <div>
            <label for="f-descripcion" class="form-label-dark">Descripción</label>
            <textarea id="f-descripcion" name="descripcion" class="form-control dark-input" rows="5"><?=htmlspecialchars($d["descripcion"]??"")?></textarea>
          </div>
        </div>
      </section>
      <section class="form-card" aria-labelledby="pf-fotos">
        <h2 class="form-subtitulo" id="pf-fotos">Fotos</h2>
        <div class="foto-campo" data-foto-previa data-prop-min="<?=ImageHelper::PROPORCION_MIN?>" data-prop-max="<?=ImageHelper::PROPORCION_MAX?>" data-lado-bajo="<?=ImageHelper::LADO_BAJO?>">
          <div class="foto-campo-datos">
            <label for="f-imagen" class="form-label-dark">Imagen de la parte</label>
            <input id="f-imagen" type="file" name="imagen" class="form-control dark-input" accept="image/*">
            <p class="foto-ayuda">JPG, PNG, GIF o WebP · máx. <?=htmlspecialchars(ImageHelper::limiteTexto())?></p>
            <div class="small text-secondary" data-foto-info><?=!empty($parte)?"Sube una nueva para reemplazarla.":"Aún no has elegido una foto."?></div>
            <div data-foto-avisos class="small" role="status" aria-live="polite"></div>
          </div>
          <div class="foto-campo-previa">
            <div class="text-secondary small mb-1" data-foto-titulo><?=!empty($parte)?"Imagen actual":"Vista previa"?></div>
            <?php $fotoParte=$parte??[]; $fotoTam="thumbnails"; $fotoClase="foto-160"; $fotoAlt=!empty($parte)?"Imagen actual":""; $fotoLazy=false; include BASE_PATH."/app/Views/layouts/foto_pieza.php"; ?>
          </div>
        </div>
      </section>
      <section class="form-card" aria-labelledby="pf-portada">
        <h2 class="form-subtitulo" id="pf-portada">Portada de la tienda</h2>
        <?php $fotoDestacar=$edit?ImageHelper::fuente($parte["imagen_grande"]??null,$parte["thumbnail"]??null,"large"):""; ?>
        <div class="form-check form-switch destacar-campo">
          <input id="f-destacado" type="checkbox" name="destacado" value="1" class="form-check-input" role="switch" data-destacar data-foto-actual="<?=$fotoDestacar!==""?htmlspecialchars(APP_URL."/uploads/".$fotoDestacar):""?>"<?=!empty($d["destacado"])?" checked":""?>>
          <label for="f-destacado" class="form-check-label">Mostrar esta pieza en la portada</label>
        </div>
        <p class="foto-ayuda mb-0">Con 2 o más destacadas, la portada muestra solo esas (máximo <?=ParteModel::MAX_DESTACADAS?>). Necesitan foto.</p>
        <div data-destacar-aviso class="small text-secondary" role="status" aria-live="polite"></div>
      </section>
    </div>
    <div class="producto-lateral">
      <section class="form-card" aria-labelledby="pf-precio">
        <h2 class="form-subtitulo" id="pf-precio">Precio e inventario</h2>
        <div class="producto-campos">
          <div>
            <label for="f-precio" class="form-label-dark">Precio ($) *</label>
            <input id="f-precio" type="number" name="precio" class="form-control dark-input" step="0.01" min="0" required value="<?=htmlspecialchars($d["precio"]??"")?>">
          </div>
          <div>
            <label for="f-cantidad" class="form-label-dark">Cantidad en Stock *</label>
            <input id="f-cantidad" type="number" name="cantidad" class="form-control dark-input" min="0" required value="<?=htmlspecialchars($d["cantidad"]??0)?>">
          </div>
        </div>
      </section>
      <section class="form-card" aria-labelledby="pf-org">
        <h2 class="form-subtitulo" id="pf-org">Organización</h2>
        <label for="f-seccion-id" class="form-label-dark">Sección del Almacén</label>
        <select id="f-seccion-id" name="seccion_id" class="form-select dark-input">
          <option value="">— Sin sección —</option>
          <?php foreach($secciones as $s): ?>
          <option value="<?=$s["id"]?>" <?=($d["seccion_id"]??"")==$s["id"]?"selected":""?>><?=htmlspecialchars($s["nombre"])?></option>
          <?php endforeach; ?>
        </select>
      </section>
      <section class="form-card" aria-labelledby="pf-compat">
        <h2 class="form-subtitulo" id="pf-compat">Compatibilidad</h2>
        <label for="f-auto-id" class="form-label-dark">Auto (Marca / Modelo / Año)</label>
        <select id="f-auto-id" name="auto_id" class="form-select dark-input">
          <option value="">— Sin especificar —</option>
          <?php foreach($autos as $a): ?>
          <option value="<?=$a["id"]?>" <?=($d["auto_id"]??"")==$a["id"]?"selected":""?>><?=htmlspecialchars($a["marca"]." ".$a["modelo"]." (".$a["anio"].")")?></option>
          <?php endforeach; ?>
        </select>
      </section>
    </div>
  </div>
</form>
</div>
<dialog class="modal-confirmar modal-confirmar--ancho" id="modalDestacar" role="alertdialog" aria-labelledby="mdTitulo" aria-describedby="mdTexto">
  <h2 class="modal-confirmar-titulo" id="mdTitulo">Destacar en la portada</h2>
  <p class="modal-confirmar-texto" id="mdTexto">En la portada la foto se muestra horizontal, recortada al centro, en formato 16:9. Lo principal de la pieza debe verse en el centro. Mínimo recomendado: 1200 × 675 px.</p>
  <div class="destacar-previa"><span class="hero-foto" data-destacar-marco><img src="" alt="Vista previa del recorte 16:9 en la portada" data-destacar-img></span></div>
  <div class="modal-confirmar-acciones">
    <button type="button" class="btn btn-outline-secondary" data-destacar-cancelar>Cancelar</button>
    <button type="button" class="btn btn-gold" data-destacar-ok>Sí, se ve bien, destacarla</button>
  </div>
</dialog>
<script src="<?=Assets::url("js/parte_imagen.js")?>"></script>
<script src="<?=Assets::url("js/parte_destacar.js")?>"></script>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
