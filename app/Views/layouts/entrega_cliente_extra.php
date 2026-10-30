<?php
// Cliente con cuenta, en el formulario de compra: teléfono de contacto y la casilla «Guardar esta dirección en mi perfil».
// Recibe $viejo (lo escrito en un intento anterior) y $userInfo (su perfil). La casilla viene marcada solo si el perfil aún no tiene dirección.
$hayViejo = isset($viejo["calle"]);
$marcada  = $hayViejo ? !empty($viejo["guardar_direccion"]) : empty($userInfo["dir_calle"]);
$telPerfil = $viejo["cliente_telefono"] ?? ($userInfo["telefono"] ?? "");
?>
<div class="mb-3">
  <label for="f-cliente-telefono" class="form-label-dark">Teléfono de contacto *</label>
  <input id="f-cliente-telefono" type="tel" name="cliente_telefono" class="form-control dark-input" required maxlength="20" autocomplete="tel" inputmode="tel" placeholder="6000-0000" value="<?=htmlspecialchars((string)$telPerfil)?>" aria-describedby="a-cliente-telefono">
  <div class="form-text" id="a-cliente-telefono">Celular de 8 dígitos (empieza por 6) o fijo de 7 dígitos.</div>
</div>
<div class="form-check mb-3">
  <input class="form-check-input" type="checkbox" id="f-guardar-direccion" name="guardar_direccion" value="1" autocomplete="off"<?=$marcada?" checked":""?>>
  <label class="form-check-label" for="f-guardar-direccion">Guardar esta dirección en mi perfil</label>
  <div class="form-text">Si no la marcas, solo vale para este pedido.</div>
</div>
