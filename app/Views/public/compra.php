<?php
$title="Comprar"; $navActivo="catalogo";
include BASE_PATH."/app/Views/layouts/public_header.php";
?>
<div class="container-xl py-5">
<div class="row g-4 gx-xl-5 justify-content-center">
  <div class="col-lg-7">
    <div class="d-flex align-items-center gap-3 mb-4">
      <a href="<?=APP_URL?>/?action=detalle&id=<?=$parte["id"]?>" class="btn-back" aria-label="Volver al detalle de la pieza"><i aria-hidden="true" class="bi bi-arrow-left"></i></a>
      <div><h1 class="h4 fw-bold text-white mb-0">Crear cuenta y comprar</h1>
      <p class="text-secondary small mb-0">Completa tus datos para crear tu cuenta y realizar el pedido</p></div>
    </div>
    <?php if($err): ?><div role="alert" class="alert-dark-danger d-flex gap-2 mb-4"><i aria-hidden="true" class="bi bi-exclamation-triangle-fill text-danger"></i><?=htmlspecialchars($err)?></div><?php endif; ?>
    <form action="<?=APP_URL?>/?action=procesar_compra" method="POST">
      <?=CsrfToken::field()?>
      <input type="hidden" name="parte_id" value="<?=$parte["id"]?>">

      <div class="compra-card mb-4" style="background:var(--bg-1);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem">
        <h2 class="h6 text-warning fw-bold mb-3"><i aria-hidden="true" class="bi bi-person me-2"></i>Datos de tu cuenta nueva</h2>
        <div class="row g-3">
          <div class="col-md-6"><label for="f-cliente-nombre" class="form-label-dark">Nombre completo *</label><input id="f-cliente-nombre" type="text" name="cliente_nombre" class="form-control dark-input" required autocomplete="name"></div>
          <div class="col-md-6"><label for="f-cliente-telefono" class="form-label-dark">Teléfono *</label><input id="f-cliente-telefono" type="tel" name="cliente_telefono" class="form-control dark-input" required placeholder="6000-0000" autocomplete="tel"></div>
          <div class="col-12"><label for="f-cliente-email" class="form-label-dark">Correo electrónico *</label><input id="f-cliente-email" type="email" name="cliente_email" class="form-control dark-input" required autocomplete="email"></div>
          <div class="col-md-6"><label for="f-nueva-pass" class="form-label-dark">Contraseña * <span class="text-secondary fw-normal">(mínimo <?=Validator::PASS_MIN?> caracteres, máximo <?=Validator::PASS_MAX?> bytes)</span></label><input id="f-nueva-pass" type="password" name="nueva_pass" class="form-control dark-input" minlength="<?=Validator::PASS_MIN?>" maxlength="<?=Validator::PASS_MAX?>" required autocomplete="new-password"></div>
          <div class="col-md-6"><label for="f-cantidad" class="form-label-dark">Cantidad *</label><input id="f-cantidad" type="number" name="cantidad" class="form-control dark-input" min="1" max="<?=$parte["cantidad"]?>" value="1" required></div>
          <div class="col-12"><label for="f-cliente-direccion" class="form-label-dark">Dirección de entrega *</label><textarea id="f-cliente-direccion" name="cliente_direccion" class="form-control dark-input auto-exp" required autocomplete="street-address" placeholder="Corregimiento, calle, referencia..."></textarea></div>
          <div class="col-12"><label for="f-cliente-notas" class="form-label-dark">Mensaje al vendedor <span class="text-secondary fw-normal">(opcional)</span></label><textarea id="f-cliente-notas" name="cliente_notas" class="form-control dark-input auto-exp" placeholder="Instrucciones especiales, horario preferido..."></textarea></div>
        </div>
      </div>

      <button type="submit" class="btn btn-gold fw-bold w-100 py-3" style="font-size:1.05rem">
        <i aria-hidden="true" class="bi bi-person-check me-2"></i>Crear cuenta y comprar
      </button>
      <p class="text-secondary small text-center mt-2">¿Ya tienes cuenta? <a href="<?=APP_URL?>/login.php" class="text-warning">Inicia sesión</a> para comprar más rápido.</p>
    </form>
  </div>
  <div class="col-lg-4">
    <div style="background:var(--bg-2);border:1px solid var(--border);border-radius:var(--radius);padding:1.25rem;position:sticky;top:80px">
      <h2 class="h6 text-warning fw-bold mb-3"><i aria-hidden="true" class="bi bi-bag me-2"></i>Tu pedido</h2>
      <div class="d-flex gap-3 mb-3">
        <?php if(ImageHelper::existe($parte["thumbnail"],"thumbnails")): ?><img src="<?=APP_URL?>/uploads/thumbnails/<?=htmlspecialchars($parte["thumbnail"])?>" class="rounded-2" style="width:64px;height:64px;object-fit:cover;border:1px solid var(--border)" alt="">
        <?php else: ?><div style="width:64px;height:64px;background:var(--bg-3);border-radius:8px;display:flex;align-items:center;justify-content:center"><i aria-hidden="true" class="bi bi-gear text-secondary"></i></div><?php endif; ?>
        <div><div class="text-white fw-semibold small"><?=htmlspecialchars($parte["nombre"])?></div><?php if($parte["marca"]??""): ?><div class="text-secondary" style="font-size:.75rem"><?=htmlspecialchars($parte["marca"]." ".$parte["modelo"])?></div><?php endif; ?></div>
      </div>
      <hr style="border-color:var(--border)">
      <div class="d-flex justify-content-between mb-1"><span class="text-secondary small">Precio unit. (sin <?=Marca::h("impuesto.nombre")?>)</span><span class="text-white small num">$<?=number_format($parte["precio"],2)?></span></div>
      <div class="text-secondary mb-1" style="font-size:.75rem">Se suma <?=htmlspecialchars(Marca::impuestoEtiqueta())?> al confirmar el pedido.</div>
      <div class="d-flex justify-content-between"><span class="text-secondary small">Stock disp.</span><span class="chip-success"><?=$parte["cantidad"]?> uds.</span></div>
    </div>
  </div>
</div>
</div>
<?php include BASE_PATH."/app/Views/layouts/public_footer.php"; ?>
