<?php $edit=!empty($auto); $title=$edit?"Editar Auto":"Nuevo Auto"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="panel-cabeza">
  <div>
    <a href="<?=APP_URL?>/admin/?mod=autos" class="enlace-volver"><i aria-hidden="true" class="bi bi-arrow-left"></i>Volver a autos</a>
    <h1 class="panel-titulo"><?=$title?></h1>
  </div>
</div>
<?php if($error??""): ?><div role="alert" class="alert alert-danger d-flex align-items-center gap-2 mb-4"><i aria-hidden="true" class="bi bi-exclamation-triangle-fill"></i><span><?=htmlspecialchars($error)?></span></div><?php endif; ?>
<div class="form-panel"><div class="form-card">
<form action="<?=APP_URL?>/admin/?mod=autos&action=<?=$edit?"editar&id=".$id:"crear"?>" method="POST">
  <?=CsrfToken::field()?>
  <?php $marcaSel=(int)($auto["marca_id"]??0); $enLista=array_column($marcas,"id"); ?>
  <div class="mb-4">
    <label for="f-marca" class="form-label-dark">Marca *</label>
    <select id="f-marca" name="marca_id" class="form-select dark-input" required>
      <option value="">— Elige una marca —</option>
      <?php if($marcaSel&&!in_array($marcaSel,$enLista)): ?><option value="<?=$marcaSel?>" selected><?=htmlspecialchars($auto["marca"]??"")?> (inactiva)</option><?php endif; ?>
      <?php foreach($marcas as $m): ?><option value="<?=(int)$m["id"]?>"<?=$marcaSel===(int)$m["id"]?" selected":""?>><?=htmlspecialchars($m["nombre"])?></option><?php endforeach; ?>
    </select>
    <p class="foto-ayuda mb-0">¿No está? <a href="<?=APP_URL?>/admin/?mod=marcas&amp;action=crear">Agrega la marca</a> primero.</p>
  </div>
  <div class="mb-4"><label for="f-modelo" class="form-label-dark">Modelo *</label><input id="f-modelo" type="text" name="modelo" class="form-control dark-input" required value="<?=htmlspecialchars($auto["modelo"]??"")?>"></div>
  <div class="mb-4"><label for="f-anio" class="form-label-dark">Año *</label><input id="f-anio" type="number" name="anio" class="form-control dark-input" required min="1980" max="2030" value="<?=htmlspecialchars($auto["anio"]??date("Y"))?>"></div>
  <div class="form-acciones"><button type="submit" class="btn btn-gold fw-bold px-4"><i aria-hidden="true" class="bi bi-save me-2"></i>Guardar</button><a href="<?=APP_URL?>/admin/?mod=autos" class="btn btn-outline-secondary">Cancelar</a></div>
</form>
</div></div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
