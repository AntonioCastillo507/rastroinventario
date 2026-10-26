<?php
$title="Confirmar Compra"; $navActivo="catalogo";
include BASE_PATH."/app/Views/layouts/public_header.php";
?>
<div class="container-xl py-5">
<div class="row g-4 gx-xl-5 justify-content-center">
  <div class="col-lg-7">
    <div class="d-flex align-items-center gap-3 mb-4">
      <a href="<?=APP_URL?>/?action=detalle&id=<?=$parte["id"]?>" class="btn-back" aria-label="Volver al detalle de la pieza"><i aria-hidden="true" class="bi bi-arrow-left"></i></a>
      <div><h1 class="h4 fw-bold text-white mb-0"><i aria-hidden="true" class="bi bi-bag-check me-2 text-warning"></i>Confirmar compra</h1>
      <p class="text-secondary small mb-0">Revisa tus datos antes de confirmar</p></div>
    </div>
    <div style="background:var(--bg-1);border:1px solid rgba(3,192,118,.3);border-radius:var(--radius);padding:1.5rem;margin-bottom:1.5rem">
      <h2 class="h6 text-success fw-bold mb-3"><i aria-hidden="true" class="bi bi-person-check me-2"></i>Tus datos registrados</h2>
      <div class="row g-2">
        <div class="col-6"><div class="text-secondary small">Nombre</div><div class="text-white fw-semibold"><?=htmlspecialchars($userInfo["nombre"])?></div></div>
        <div class="col-6"><div class="text-secondary small">Teléfono</div><div class="text-white fw-semibold"><?=htmlspecialchars($userInfo["telefono"]??"—")?></div></div>
        <div class="col-12"><div class="text-secondary small">Correo</div><div class="text-white fw-semibold"><?=htmlspecialchars($userInfo["email"])?></div></div>
      </div>
    </div>
    <form action="<?=APP_URL?>/?action=confirmar_compra" method="POST">
      <?=CsrfToken::field()?>
      <input type="hidden" name="parte_id" value="<?=$parte["id"]?>">
      <div style="background:var(--bg-1);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;margin-bottom:1.25rem">
        <h2 class="h6 text-warning fw-bold mb-3"><i aria-hidden="true" class="bi bi-truck me-2"></i>Dirección de entrega</h2>
        <textarea name="direccion_entrega" class="form-control dark-input auto-exp" required rows="2" aria-label="Dirección de entrega" autocomplete="street-address" placeholder="Corregimiento, calle, punto de referencia..."><?=htmlspecialchars($viejo["direccion_entrega"]??$userInfo["direccion"]??"")?></textarea>
        <div class="mb-3 mt-3"><label for="f-cantidad" class="form-label-dark">Cantidad *</label><input id="f-cantidad" type="number" name="cantidad" class="form-control dark-input" min="1" max="<?=$parte["cantidad"]?>" value="<?=(int)($viejo["cantidad"]??1)?:1?>" required></div>
        <label for="f-cliente-notas" class="form-label-dark">Mensaje al vendedor <span class="text-secondary fw-normal">(opcional)</span></label>
        <textarea id="f-cliente-notas" name="cliente_notas" class="form-control dark-input auto-exp" rows="2" placeholder="Instrucciones especiales..."><?=htmlspecialchars($viejo["cliente_notas"]??"")?></textarea>
      </div>
      <div style="background:rgba(240,185,11,.06);border:1px solid rgba(240,185,11,.2);border-radius:var(--radius-sm);padding:1rem;margin-bottom:1.25rem">
        <p class="text-warning mb-1 fw-semibold"><i aria-hidden="true" class="bi bi-info-circle me-2"></i>¿Seguro que quieres realizar esta compra?</p>
        <p class="text-secondary small mb-0">Pieza: <strong class="text-white"><?=htmlspecialchars($parte["nombre"])?></strong> — Precio: <strong class="text-precio num">$<?=number_format($parte["precio"],2)?></strong></p>
      </div>
      <button type="submit" class="btn btn-gold fw-bold w-100 py-3" style="font-size:1.05rem">
        <i aria-hidden="true" class="bi bi-check-circle me-2"></i>Sí, confirmar compra
      </button>
    </form>
  </div>
  <div class="col-lg-4">
    <div style="background:var(--bg-2);border:1px solid var(--border);border-radius:var(--radius);padding:1.25rem;position:sticky;top:80px">
      <h2 class="h6 text-warning fw-bold mb-3"><i aria-hidden="true" class="bi bi-bag me-2"></i>Resumen</h2>
      <div class="mb-3"><?php $fotoParte=$parte; $fotoTam="medium"; $fotoClase="foto-ancha"; $fotoAlt=""; include BASE_PATH."/app/Views/layouts/foto_pieza.php"; ?></div>
      <div class="text-white fw-semibold mb-1"><?=htmlspecialchars($parte["nombre"])?></div>
      <?php if($parte["marca"]??""): ?><div class="text-secondary small mb-2"><?=htmlspecialchars($parte["marca"]." ".$parte["modelo"])?></div><?php endif; ?>
      <div class="text-precio fw-bold fs-4 num">$<?=number_format($parte["precio"],2)?></div>
      <div class="text-secondary" style="font-size:.75rem">Precio sin <?=Marca::h("impuesto.nombre")?>. Se suma el <?=Marca::impuestoPorcentaje()?>% al confirmar.</div>
      <div class="text-secondary small mt-1"><span class="num"><?=$parte["cantidad"]?></span> unidades disponibles</div>
    </div>
  </div>
</div>
</div>
<?php include BASE_PATH."/app/Views/layouts/public_footer.php"; ?>
