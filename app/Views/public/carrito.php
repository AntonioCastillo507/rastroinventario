<?php
$title="Carrito — Rastro del Istmo";
include BASE_PATH."/app/Views/layouts/public_header.php";
$errCarro=$_SESSION["carrito_error"]??""; unset($_SESSION["carrito_error"]);
$isCliente=!empty($_SESSION["user"])&&in_array("Cliente",$_SESSION["user_roles"]??[]);
?>
<a href="javascript:history.back()" class="btn-float-back"><i class="bi bi-arrow-left"></i></a>
<div class="container-xl py-4">
  <div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?=APP_URL?>/?ver=catalogo" class="btn-back"><i class="bi bi-arrow-left"></i></a>
<h4 class="fw-bold text-white mb-0">
    <i class="bi bi-cart3 me-2 text-warning"></i>
    Mi Carrito
    <span class="text-secondary fw-normal" style="font-size:1rem">
        (<?= count($cart) ?> pieza<?= count($cart) !== 1 ? "s" : "" ?>)
    </span>
</h4>
  </div>
  <?php if($errCarro): ?><div class="alert-dark-danger d-flex gap-2 mb-4"><i class="bi bi-exclamation-triangle-fill text-danger"></i><?=htmlspecialchars($errCarro)?></div><?php endif; ?>
  <?php if(empty($cart)): ?>
  <div class="text-center py-5"><i class="bi bi-cart-x display-1 text-secondary opacity-25"></i>
    <h5 class="text-secondary mt-3">Tu carrito está vacío</h5>
    <a href="<?=APP_URL?>/?ver=catalogo" class="btn btn-gold mt-3 px-5"><i class="bi bi-grid me-2"></i>Ver catálogo</a>
  </div>
  <?php else: ?>
  <div class="row g-4">
    <div class="col-lg-8">
      <form method="POST" action="<?=APP_URL?>/?action=actualizar_carrito" id="frmCarrito">
      <?=CsrfToken::field()?>
      <button type="submit" class="visually-hidden" tabindex="-1" aria-hidden="true"></button><!-- Enter en una cantidad actualiza, no quita -->
      <?php foreach($cart as $id=>$item): ?>
      <div class="cart-item d-flex align-items-center gap-3">
        <?php if($item["thumbnail"]): ?><img src="<?=APP_URL?>/uploads/thumbnails/<?=htmlspecialchars($item["thumbnail"])?>" style="width:70px;height:70px;object-fit:cover;border-radius:8px;border:1px solid var(--border)" alt=""><?php else: ?><div style="width:70px;height:70px;background:var(--bg-3);border-radius:8px;display:flex;align-items:center;justify-content:center"><i class="bi bi-gear text-secondary fs-4"></i></div><?php endif; ?>
        <div class="flex-fill">
          <div class="text-white fw-semibold"><?=htmlspecialchars($item["nombre"])?></div>
          <?php if($item["marca"]??""): ?><div class="text-secondary small"><?=htmlspecialchars($item["marca"])?></div><?php endif; ?>
          <div class="text-success fw-bold">$<?=number_format($item["precio"],2)?></div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <input type="number" name="cantidad[<?=$id?>]" value="<?=$item["cantidad"]?>" min="1" max="<?=$item["max"]?>" class="form-control dark-input text-center" style="width:70px">
          <span class="text-success fw-bold" style="min-width:70px;text-align:right">$<?=number_format($item["precio"]*$item["cantidad"],2)?></span>
          <button type="submit" formmethod="post" formaction="<?=APP_URL?>/?action=quitar_carrito&id=<?=$id?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Quitar esta pieza?')"><i class="bi bi-trash"></i></button>
        </div>
      </div>
      <?php endforeach; ?>
      <button type="submit" class="btn btn-outline-secondary btn-sm mt-2"><i class="bi bi-arrow-clockwise me-1"></i>Actualizar cantidades</button>
      </form>
    </div>
    <div class="col-lg-4">
      <div style="background:var(--bg-1);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;position:sticky;top:80px">
        <h6 class="text-warning fw-bold mb-3">Resumen del pedido</h6>
        <?php foreach($cart as $item): ?>
        <div class="d-flex justify-content-between mb-1 small"><span class="text-secondary"><?=htmlspecialchars(mb_substr($item["nombre"],0,28)).(mb_strlen($item["nombre"])>28?"…":"")?>  ×<?=$item["cantidad"]?></span><span class="text-white">$<?=number_format($item["precio"]*$item["cantidad"],2)?></span></div>
        <?php endforeach; ?>
        <hr style="border-color:var(--border)">
        <div class="d-flex justify-content-between mb-1 small"><span class="text-secondary">Subtotal</span><span class="text-white">$<?=number_format($subtotal,2)?></span></div>
        <div class="d-flex justify-content-between mb-2 small"><span class="text-secondary">ITBMS (7%)</span><span class="text-white">$<?=number_format($itbms,2)?></span></div>
        <div class="d-flex justify-content-between mb-3"><span class="text-white fw-bold">Total</span><span class="text-success fw-bold fs-5">$<?=number_format($total,2)?></span></div>

        <?php if($isCliente): ?>
        <!-- Cliente logueado: checkout rápido -->
        <form method="POST" action="<?=APP_URL?>/?action=procesar_carrito">
          <?=CsrfToken::field()?>
          <label class="form-label-dark">Dirección de entrega *</label>
          <textarea name="cliente_direccion" class="form-control dark-input auto-exp mb-2" required rows="2" placeholder="Dirección completa..."></textarea>
          <label class="form-label-dark">Mensaje (opcional)</label>
          <textarea name="cliente_notas" class="form-control dark-input auto-exp mb-3" rows="1"></textarea>
          <button type="submit" class="btn btn-gold fw-bold w-100 py-2"><i class="bi bi-bag-check me-2"></i>Confirmar pedido</button>
        </form>
        <?php else: ?>
        <!-- Invitado: crear cuenta obligatoria -->
        <form method="POST" action="<?=APP_URL?>/?action=procesar_carrito">
          <?=CsrfToken::field()?>
          <div class="mb-2"><label class="form-label-dark">Nombre *</label><input type="text" name="cliente_nombre" class="form-control dark-input form-control-sm" required></div>
          <div class="mb-2"><label class="form-label-dark">Teléfono *</label><input type="tel" name="cliente_telefono" class="form-control dark-input form-control-sm" required></div>
          <div class="mb-2"><label class="form-label-dark">Correo *</label><input type="email" name="cliente_email" class="form-control dark-input form-control-sm" required></div>
          <div class="mb-2"><label class="form-label-dark">Contraseña * (8-12 chars)</label><input type="password" name="nueva_pass" class="form-control dark-input form-control-sm" minlength="8" maxlength="12" required></div>
          <div class="mb-2"><label class="form-label-dark">Dirección entrega *</label><textarea name="cliente_direccion" class="form-control dark-input form-control-sm" required rows="2"></textarea></div>
          <div class="mb-3"><label class="form-label-dark">Mensaje (opcional)</label><textarea name="cliente_notas" class="form-control dark-input form-control-sm" rows="1"></textarea></div>
          <button type="submit" class="btn btn-gold fw-bold w-100 py-2"><i class="bi bi-person-check me-2"></i>Crear cuenta y pedir</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php include BASE_PATH."/app/Views/layouts/public_footer.php"; ?>
