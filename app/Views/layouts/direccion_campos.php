<?php
// Campos de la dirección de Panamá. Recibe $dir (provincia, distrito, corregimiento, calle, casa, barriada, referencia),
// $errores (campo => mensaje) y $geo (GeoPanamaModel). Los desplegables dependen unos de otros (assets/js/direccion.js);
// sin JavaScript el servidor ya pinta las listas del valor elegido y valida igual. Guna Yala (sin distritos) y Naso Tjër Di
// (sin corregimientos que mostrar) ocultan el nivel que no existe: el servidor lo completa solo.
$geo = $geo ?? new GeoPanamaModel();
$cargarDireccionJs = true;
$dirV = fn(string $k) => (string)($dir[$k] ?? "");
$dirErr = $errores ?? [];
$dirProvincias = $geo->provincias();
$dirDistritos = $dirV("provincia") !== "" ? $geo->distritos($dirV("provincia")) : [];
$dirDistritoImpl = $dirV("provincia") !== "" ? $geo->distritoImplicito($dirV("provincia")) : null;
$dirDistritoEf = $dirV("distrito") !== "" ? $dirV("distrito") : ($dirDistritoImpl ?? "");
$dirCorregimientos = $dirDistritoEf !== "" ? $geo->corregimientos($dirDistritoEf) : [];
?>
<fieldset class="direccion-campos" data-geo-url="<?=APP_URL?>/?action=geo">
  <legend class="direccion-leyenda">Dirección en Panamá</legend>
  <div class="direccion-rejilla">
    <div class="direccion-campo">
      <label for="f-dir-provincia" class="form-label-dark">Provincia o comarca *</label>
      <select id="f-dir-provincia" name="provincia" class="form-select dark-input" required autocomplete="address-level1"<?=isset($dirErr["provincia"])?' aria-invalid="true" aria-describedby="e-dir-provincia"':""?>>
        <option value="">Elige…</option>
        <?php foreach($dirProvincias as $dirP): ?><option value="<?=htmlspecialchars($dirP["codigo"])?>"<?=$dirV("provincia")===$dirP["codigo"]?" selected":""?>><?=htmlspecialchars($dirP["nombre"])?></option><?php endforeach; ?>
      </select>
      <?php $dirCampo="provincia"; include BASE_PATH."/app/Views/layouts/campo_error.php"; ?>
    </div>
    <div class="direccion-campo" id="dir-grupo-distrito"<?=$dirDistritos?"":" hidden"?>>
      <label for="f-dir-distrito" class="form-label-dark">Distrito *</label>
      <select id="f-dir-distrito" name="distrito" class="form-select dark-input"<?=$dirDistritos?" required":" disabled"?> autocomplete="address-level2"<?=isset($dirErr["distrito"])?' aria-invalid="true" aria-describedby="e-dir-distrito"':""?>>
        <option value="">Elige…</option>
        <?php foreach($dirDistritos as $dirX): ?><option value="<?=htmlspecialchars($dirX["codigo"])?>"<?=$dirV("distrito")===$dirX["codigo"]?" selected":""?>><?=htmlspecialchars($dirX["nombre"])?></option><?php endforeach; ?>
      </select>
      <?php $dirCampo="distrito"; include BASE_PATH."/app/Views/layouts/campo_error.php"; ?>
    </div>
    <div class="direccion-campo" id="dir-grupo-corregimiento"<?=$dirCorregimientos?"":" hidden"?>>
      <label for="f-dir-corregimiento" class="form-label-dark">Corregimiento *</label>
      <select id="f-dir-corregimiento" name="corregimiento" class="form-select dark-input"<?=$dirCorregimientos?" required":" disabled"?> autocomplete="address-level3"<?=isset($dirErr["corregimiento"])?' aria-invalid="true" aria-describedby="e-dir-corregimiento"':""?>>
        <option value="">Elige…</option>
        <?php foreach($dirCorregimientos as $dirX): ?><option value="<?=htmlspecialchars($dirX["codigo"])?>"<?=$dirV("corregimiento")===$dirX["codigo"]?" selected":""?>><?=htmlspecialchars($dirX["nombre"])?></option><?php endforeach; ?>
      </select>
      <?php $dirCampo="corregimiento"; include BASE_PATH."/app/Views/layouts/campo_error.php"; ?>
    </div>
    <div class="direccion-campo">
      <label for="f-dir-calle" class="form-label-dark">Calle o avenida *</label>
      <input id="f-dir-calle" type="text" name="calle" class="form-control dark-input" required minlength="3" maxlength="80" autocomplete="address-line1" value="<?=htmlspecialchars($dirV("calle"))?>"<?=isset($dirErr["calle"])?' aria-invalid="true" aria-describedby="e-dir-calle"':""?>>
      <?php $dirCampo="calle"; include BASE_PATH."/app/Views/layouts/campo_error.php"; ?>
    </div>
    <div class="direccion-campo">
      <label for="f-dir-casa" class="form-label-dark">Casa o edificio y apartamento *</label>
      <input id="f-dir-casa" type="text" name="casa" class="form-control dark-input" required maxlength="60" autocomplete="address-line2" value="<?=htmlspecialchars($dirV("casa"))?>"<?=isset($dirErr["casa"])?' aria-invalid="true" aria-describedby="e-dir-casa"':""?>>
      <?php $dirCampo="casa"; include BASE_PATH."/app/Views/layouts/campo_error.php"; ?>
    </div>
    <div class="direccion-campo">
      <label for="f-dir-barriada" class="form-label-dark">Barriada o urbanización *</label>
      <input id="f-dir-barriada" type="text" name="barriada" class="form-control dark-input" required minlength="3" maxlength="80" value="<?=htmlspecialchars($dirV("barriada"))?>"<?=isset($dirErr["barriada"])?' aria-invalid="true" aria-describedby="e-dir-barriada"':""?>>
      <?php $dirCampo="barriada"; include BASE_PATH."/app/Views/layouts/campo_error.php"; ?>
    </div>
    <div class="direccion-campo direccion-ancho">
      <label for="f-dir-referencia" class="form-label-dark">Punto de referencia <span class="text-secondary fw-normal">(opcional)</span></label>
      <input id="f-dir-referencia" type="text" name="referencia" class="form-control dark-input" maxlength="120" value="<?=htmlspecialchars($dirV("referencia"))?>"<?=isset($dirErr["referencia"])?' aria-invalid="true" aria-describedby="e-dir-referencia"':""?>>
      <?php $dirCampo="referencia"; include BASE_PATH."/app/Views/layouts/campo_error.php"; ?>
    </div>
  </div>
</fieldset>
