<?php
// Bloque "Entrega": quién recibe y dónde. Recibe $viejo (lo escrito en un intento anterior), $isCliente y $userInfo.
// El cliente con sesión ya está identificado: solo escribe la dirección y el mensaje; el invitado escribe también sus datos de contacto.
?>
<fieldset class="carrito-bloque">
  <legend class="carrito-bloque-titulo">Entrega</legend>
  <?php if($isCliente): ?>
  <p class="carrito-identidad">Compras como <strong><?=htmlspecialchars($userInfo["nombre"])?></strong> (<?=htmlspecialchars($userInfo["email"])?>).</p>
  <?php else: ?>
  <div class="mb-3"><label for="f-cliente-nombre" class="form-label-dark">Nombre completo *</label><input id="f-cliente-nombre" type="text" name="cliente_nombre" class="form-control dark-input" required autocomplete="name" value="<?=htmlspecialchars($viejo["cliente_nombre"]??"")?>"></div>
  <div class="row g-3 mb-3">
    <div class="col-md-6"><label for="f-cliente-telefono" class="form-label-dark">Teléfono *</label><input id="f-cliente-telefono" type="tel" name="cliente_telefono" class="form-control dark-input" required autocomplete="tel" value="<?=htmlspecialchars($viejo["cliente_telefono"]??"")?>"></div>
    <div class="col-md-6"><label for="f-cliente-email" class="form-label-dark">Correo electrónico *</label><input id="f-cliente-email" type="email" name="cliente_email" class="form-control dark-input" required autocomplete="email" value="<?=htmlspecialchars($viejo["cliente_email"]??"")?>"></div>
  </div>
  <?php endif; ?>
  <?php if($isCliente){ $dir=$viejo?:["provincia"=>$userInfo["dir_provincia"]??"","distrito"=>$userInfo["dir_distrito"]??"","corregimiento"=>$userInfo["dir_corregimiento"]??"","calle"=>$userInfo["dir_calle"]??"","casa"=>$userInfo["dir_casa"]??"","barriada"=>$userInfo["dir_barriada"]??"","referencia"=>$userInfo["dir_referencia"]??""]; } else { $dir=$viejo; } $errores=[]; include BASE_PATH."/app/Views/layouts/direccion_campos.php"; if($isCliente) include BASE_PATH."/app/Views/layouts/entrega_cliente_extra.php"; ?>
  <div class="mb-0"><label for="f-cliente-notas" class="form-label-dark">Mensaje al vendedor <span class="text-secondary fw-normal">(opcional)</span></label><textarea id="f-cliente-notas" name="cliente_notas" class="form-control dark-input auto-exp" rows="2"><?=htmlspecialchars($viejo["cliente_notas"]??"")?></textarea></div>
</fieldset>
