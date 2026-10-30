<?php
// Campos de la dirección de Panamá. Recibe $dir (provincia, distrito, corregimiento, calle, casa, barriada, referencia),
// $errores (campo => mensaje) y $geo (GeoPanamaModel). Los desplegables dependen unos de otros (assets/js/direccion.js);
// sin JavaScript el servidor ya pinta las listas del valor elegido y valida igual. Guna Yala (sin distritos) y Naso Tjër Di
// (sin corregimientos que mostrar) ocultan el nivel que no existe: el servidor lo completa solo.
$d = fn(string $k) => (string)($dir[$k] ?? "");
$e = $errores ?? [];
$provincias = $geo->provincias();
$distritos = $d("provincia") !== "" ? $geo->distritos($d("provincia")) : [];
$distritoImpl = $d("provincia") !== "" ? $geo->distritoImplicito($d("provincia")) : null;
$distritoEfectivo = $d("distrito") !== "" ? $d("distrito") : ($distritoImpl ?? "");
$corregimientos = $distritoEfectivo !== "" ? $geo->corregimientos($distritoEfectivo) : [];
$campoError = function (string $k) use ($e): string {
    return isset($e[$k]) ? '<p class="campo-error" id="e-dir-'.$k.'" role="alert"><i aria-hidden="true" class="bi bi-exclamation-circle"></i><span>'.htmlspecialchars($e[$k]).'</span></p>' : "";
};
$attrs = fn(string $k) => isset($e[$k]) ? ' aria-invalid="true" aria-describedby="e-dir-'.$k.'"' : "";
?>
<fieldset class="direccion-campos" data-geo-url="<?=APP_URL?>/?action=geo">
  <legend class="direccion-leyenda">Dirección en Panamá</legend>
  <div class="direccion-rejilla">
    <div class="direccion-campo">
      <label for="f-dir-provincia" class="form-label-dark">Provincia o comarca *</label>
      <select id="f-dir-provincia" name="provincia" class="form-select dark-input" required autocomplete="address-level1"<?=$attrs("provincia")?>>
        <option value="">Elige…</option>
        <?php foreach($provincias as $p): ?><option value="<?=htmlspecialchars($p["codigo"])?>"<?=$d("provincia")===$p["codigo"]?" selected":""?>><?=htmlspecialchars($p["nombre"])?></option><?php endforeach; ?>
      </select>
      <?=$campoError("provincia")?>
    </div>
    <div class="direccion-campo" id="dir-grupo-distrito"<?=$distritos?"":" hidden"?>>
      <label for="f-dir-distrito" class="form-label-dark">Distrito *</label>
      <select id="f-dir-distrito" name="distrito" class="form-select dark-input"<?=$distritos?" required":" disabled"?> autocomplete="address-level2"<?=$attrs("distrito")?>>
        <option value="">Elige…</option>
        <?php foreach($distritos as $x): ?><option value="<?=htmlspecialchars($x["codigo"])?>"<?=$d("distrito")===$x["codigo"]?" selected":""?>><?=htmlspecialchars($x["nombre"])?></option><?php endforeach; ?>
      </select>
      <?=$campoError("distrito")?>
    </div>
    <div class="direccion-campo" id="dir-grupo-corregimiento"<?=$corregimientos?"":" hidden"?>>
      <label for="f-dir-corregimiento" class="form-label-dark">Corregimiento *</label>
      <select id="f-dir-corregimiento" name="corregimiento" class="form-select dark-input"<?=$corregimientos?" required":" disabled"?> autocomplete="address-level3"<?=$attrs("corregimiento")?>>
        <option value="">Elige…</option>
        <?php foreach($corregimientos as $x): ?><option value="<?=htmlspecialchars($x["codigo"])?>"<?=$d("corregimiento")===$x["codigo"]?" selected":""?>><?=htmlspecialchars($x["nombre"])?></option><?php endforeach; ?>
      </select>
      <?=$campoError("corregimiento")?>
    </div>
    <div class="direccion-campo">
      <label for="f-dir-calle" class="form-label-dark">Calle o avenida *</label>
      <input id="f-dir-calle" type="text" name="calle" class="form-control dark-input" required minlength="3" maxlength="80" autocomplete="address-line1" value="<?=htmlspecialchars($d("calle"))?>"<?=$attrs("calle")?>>
      <?=$campoError("calle")?>
    </div>
    <div class="direccion-campo">
      <label for="f-dir-casa" class="form-label-dark">Casa o edificio y apartamento *</label>
      <input id="f-dir-casa" type="text" name="casa" class="form-control dark-input" required maxlength="60" autocomplete="address-line2" value="<?=htmlspecialchars($d("casa"))?>"<?=$attrs("casa")?>>
      <?=$campoError("casa")?>
    </div>
    <div class="direccion-campo">
      <label for="f-dir-barriada" class="form-label-dark">Barriada o urbanización *</label>
      <input id="f-dir-barriada" type="text" name="barriada" class="form-control dark-input" required minlength="3" maxlength="80" value="<?=htmlspecialchars($d("barriada"))?>"<?=$attrs("barriada")?>>
      <?=$campoError("barriada")?>
    </div>
    <div class="direccion-campo direccion-ancho">
      <label for="f-dir-referencia" class="form-label-dark">Punto de referencia <span class="text-secondary fw-normal">(opcional)</span></label>
      <input id="f-dir-referencia" type="text" name="referencia" class="form-control dark-input" maxlength="120" value="<?=htmlspecialchars($d("referencia"))?>"<?=$attrs("referencia")?>>
      <?=$campoError("referencia")?>
    </div>
  </div>
</fieldset>
