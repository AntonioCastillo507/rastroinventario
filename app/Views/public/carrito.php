<?php
$title="Carrito";
include BASE_PATH."/app/Views/layouts/public_header.php";
$errCarro=$_SESSION["carrito_error"]??""; unset($_SESSION["carrito_error"]);
$viejo=FormViejo::tomar("carrito");
$isCliente=!empty($_SESSION["user"])&&in_array("Cliente",$_SESSION["user_roles"]??[]);
?>
<div class="container-xl py-4">
  <div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?=APP_URL?>/?ver=catalogo" class="btn-back" aria-label="Volver al catálogo"><i aria-hidden="true" class="bi bi-arrow-left"></i></a>
<h1 class="h4 fw-bold text-white mb-0">
    <i aria-hidden="true" class="bi bi-cart3 me-2 text-warning"></i>
    Mi Carrito
    <span class="text-secondary fw-normal" style="font-size:1rem">
        (<?= count($cart) ?> pieza<?= count($cart) !== 1 ? "s" : "" ?>)
    </span>
</h1>
  </div>
  <?php if($errCarro): ?><div role="alert" class="alert-dark-danger d-flex gap-2 mb-4"><i aria-hidden="true" class="bi bi-exclamation-triangle-fill text-danger"></i><?=htmlspecialchars($errCarro)?></div><?php endif; ?>
  <?php if(empty($cart)): ?>
  <div class="text-center py-5"><i aria-hidden="true" class="bi bi-cart-x display-1 text-secondary opacity-25"></i>
    <h2 class="h5 text-secondary mt-3">Tu carrito está vacío</h2>
    <a href="<?=APP_URL?>/?ver=catalogo" class="btn btn-gold mt-3 px-5"><i aria-hidden="true" class="bi bi-grid me-2"></i>Ver catálogo</a>
  </div>
  <?php else: ?>
  <div class="row g-4">
    <div class="col-lg-8">
      <form method="POST" action="<?=APP_URL?>/?action=actualizar_carrito" id="frmCarrito">
      <?=CsrfToken::field()?>
      <button type="submit" class="visually-hidden" tabindex="-1" aria-hidden="true"></button><!-- Enter en una cantidad actualiza, no quita -->
      <?php foreach($cart as $id=>$item): ?>
      <div class="cart-item d-flex align-items-center gap-3">
        <?php $fotoParte=$item; $fotoTam="thumbnails"; $fotoClase="foto-80"; $fotoAlt=""; include BASE_PATH."/app/Views/layouts/foto_pieza.php"; ?>
        <div class="flex-fill">
          <div class="text-white fw-semibold"><?=htmlspecialchars($item["nombre"])?></div>
          <?php if($item["marca"]??""): ?><div class="text-secondary small"><?=htmlspecialchars($item["marca"])?></div><?php endif; ?>
          <div class="text-precio fw-bold num">$<?=number_format($item["precio"],2)?></div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <input type="number" name="cantidad[<?=$id?>]" value="<?=$item["cantidad"]?>" min="1" max="<?=$item["max"]?>" class="form-control dark-input text-center" style="width:70px" aria-label="Cantidad de <?=htmlspecialchars($item["nombre"])?>">
          <span class="text-precio fw-bold num" style="min-width:70px;text-align:right">$<?=number_format($item["precio"]*$item["cantidad"],2)?></span>
          <button type="submit" formmethod="post" formaction="<?=APP_URL?>/?action=quitar_carrito&id=<?=$id?>" class="btn btn-sm btn-outline-danger" aria-label="Quitar <?=htmlspecialchars($item["nombre"])?> del carrito" onclick="return confirm('¿Quitar esta pieza?')"><i aria-hidden="true" class="bi bi-trash"></i></button>
        </div>
      </div>
      <?php endforeach; ?>
      <button type="submit" class="btn btn-outline-secondary btn-sm mt-2"><i aria-hidden="true" class="bi bi-arrow-clockwise me-1"></i>Actualizar cantidades</button>
      </form>
    </div>
    <div class="col-lg-4">
      <div style="background:var(--bg-1);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;position:sticky;top:80px">
        <h2 class="h6 text-warning fw-bold mb-3">Resumen del pedido</h2>
        <?php foreach($cart as $item): ?>
        <div class="d-flex justify-content-between mb-1 small"><span class="text-secondary"><?=htmlspecialchars(mb_substr($item["nombre"],0,28)).(mb_strlen($item["nombre"])>28?"…":"")?>  ×<span class="num"><?=$item["cantidad"]?></span></span><span class="text-white num">$<?=number_format($item["precio"]*$item["cantidad"],2)?></span></div>
        <?php endforeach; ?>
        <hr style="border-color:var(--border)">
        <div class="d-flex justify-content-between mb-1 small"><span class="text-secondary">Subtotal</span><span class="text-white num">$<?=number_format($subtotal,2)?></span></div>
        <div class="d-flex justify-content-between mb-2 small"><span class="text-secondary"><?=htmlspecialchars(Marca::impuestoEtiqueta())?></span><span class="text-white num">$<?=number_format($itbms,2)?></span></div>
        <div class="d-flex justify-content-between mb-3"><span class="text-white fw-bold">Total</span><span class="text-precio fw-bold fs-5 num">$<?=number_format($total,2)?></span></div>

        <?php if($isCliente): ?>
        <!-- Cliente logueado: checkout rápido -->
        <form method="POST" action="<?=APP_URL?>/?action=procesar_carrito">
          <?=CsrfToken::field()?>
          <label for="f-cliente-direccion" class="form-label-dark">Dirección de entrega *</label>
          <textarea id="f-cliente-direccion" name="cliente_direccion" class="form-control dark-input auto-exp mb-2" required rows="2" autocomplete="street-address" placeholder="Dirección completa..."><?=htmlspecialchars($viejo["cliente_direccion"]??"")?></textarea>
          <label for="f-cliente-notas" class="form-label-dark">Mensaje (opcional)</label>
          <textarea id="f-cliente-notas" name="cliente_notas" class="form-control dark-input auto-exp mb-3" rows="1"><?=htmlspecialchars($viejo["cliente_notas"]??"")?></textarea>
          <button type="submit" class="btn btn-gold fw-bold w-100 py-2"><i aria-hidden="true" class="bi bi-bag-check me-2"></i>Confirmar pedido</button>
        </form>
        <?php else: ?>
        <!-- Invitado: crear cuenta obligatoria -->
        <form method="POST" action="<?=APP_URL?>/?action=procesar_carrito">
          <?=CsrfToken::field()?>
          <div class="mb-2"><label for="f-cliente-nombre" class="form-label-dark">Nombre *</label><input id="f-cliente-nombre" type="text" name="cliente_nombre" class="form-control dark-input form-control-sm" required autocomplete="name" value="<?=htmlspecialchars($viejo["cliente_nombre"]??"")?>"></div>
          <div class="mb-2"><label for="f-cliente-telefono" class="form-label-dark">Teléfono *</label><input id="f-cliente-telefono" type="tel" name="cliente_telefono" class="form-control dark-input form-control-sm" required autocomplete="tel" value="<?=htmlspecialchars($viejo["cliente_telefono"]??"")?>"></div>
          <div class="mb-2"><label for="f-cliente-email" class="form-label-dark">Correo *</label><input id="f-cliente-email" type="email" name="cliente_email" class="form-control dark-input form-control-sm" required autocomplete="email" value="<?=htmlspecialchars($viejo["cliente_email"]??"")?>"></div>
          <div class="mb-2"><label for="f-nueva-pass" class="form-label-dark">Contraseña * (mínimo <?=Validator::PASS_MIN?> caracteres, máximo <?=Validator::PASS_MAX?> bytes)</label><input id="f-nueva-pass" type="password" name="nueva_pass" class="form-control dark-input form-control-sm" minlength="<?=Validator::PASS_MIN?>" maxlength="<?=Validator::PASS_MAX?>" required autocomplete="new-password"></div>
          <div class="mb-2"><label for="f-cliente-direccion" class="form-label-dark">Dirección entrega *</label><textarea id="f-cliente-direccion" name="cliente_direccion" class="form-control dark-input form-control-sm" required rows="2" autocomplete="street-address"><?=htmlspecialchars($viejo["cliente_direccion"]??"")?></textarea></div>
          <div class="mb-3"><label for="f-cliente-notas" class="form-label-dark">Mensaje (opcional)</label><textarea id="f-cliente-notas" name="cliente_notas" class="form-control dark-input form-control-sm" rows="1"><?=htmlspecialchars($viejo["cliente_notas"]??"")?></textarea></div>
          <button type="submit" class="btn btn-gold fw-bold w-100 py-2"><i aria-hidden="true" class="bi bi-person-check me-2"></i>Crear cuenta y pedir</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php include BASE_PATH."/app/Views/layouts/public_footer.php"; ?>
