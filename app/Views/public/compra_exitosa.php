<?php
$title = "Compra Exitosa — Rastro del Istmo";
include BASE_PATH."/app/Views/layouts/public_header.php";
?>
<a href="javascript:history.back()" class="btn-float-back"><i class="bi bi-arrow-left"></i></a>
<div class="container-xl py-5">
  <div style="max-width:640px;margin:0 auto">
    <div class="dark-card p-4 mb-4 text-center" style="border-color:rgba(3,192,118,.3)">
      <div style="font-size:3.5rem;color:var(--success)"><i class="bi bi-check-circle-fill"></i></div>
      <h3 class="fw-bold text-white mt-2">Pedido registrado exitosamente</h3>
      <p class="text-secondary">Tu pedido <strong class="text-warning">#<?=str_pad($pedido["id"],6,"0",STR_PAD_LEFT)?></strong> ha sido recibido y está pendiente de procesamiento.</p>
    </div>

    <!-- Resumen del pedido -->
    <div class="dark-card p-4 mb-4">
      <h6 class="fw-bold text-warning mb-3"><i class="bi bi-receipt me-2"></i>Detalle de tu compra</h6>
      <div class="row g-2 mb-3">
        <div class="col-6"><div class="text-secondary small">Cliente</div><div class="text-white fw-semibold"><?=htmlspecialchars($pedido["cliente_nombre"])?></div></div>
        <div class="col-6"><div class="text-secondary small">Teléfono</div><div class="text-white fw-semibold"><?=htmlspecialchars($pedido["cliente_telefono"])?></div></div>
        <div class="col-12"><div class="text-secondary small">Dirección de entrega</div><div class="text-white"><?=htmlspecialchars($pedido["cliente_direccion"])?></div></div>
      </div>
      <hr style="border-color:var(--border)">
      <?php $monto=Itbms::dePedido($pedido); ?>
      <?php foreach($pedido["items"] as $item): ?>
      <div class="d-flex justify-content-between align-items-center py-2 border-bottom" style="border-color:var(--border)!important">
        <div><div class="text-white small fw-semibold"><?=htmlspecialchars($item["parte_nombre"])?></div><div class="text-secondary" style="font-size:.75rem">Cant: <?=$item["cantidad"]?> x $<?=number_format($item["precio_unitario"],2)?></div></div>
        <div class="text-success fw-bold">$<?=number_format($item["precio_unitario"]*$item["cantidad"],2)?></div>
      </div>
      <?php endforeach; ?>
      <div class="mt-2 text-end">
        <div class="text-secondary small">Subtotal: $<?=number_format($monto["subtotal"],2)?></div>
        <div class="text-secondary small">ITBMS (7%): $<?=number_format($monto["itbms"],2)?></div>
        <div class="text-white fw-bold fs-5">Total: <span class="text-success">$<?=number_format($monto["total"],2)?></span></div>
      </div>
    </div>

    <!-- BOTÓN FACTURA PDF -->
    <div class="dark-card p-4 mb-4" style="border-color:rgba(240,185,11,.3)">
      <h6 class="fw-bold text-warning mb-2"><i class="bi bi-file-earmark-pdf me-2"></i>Factura Electrónica</h6>
      <p class="text-secondary small mb-3">Descarga tu factura oficial con firma digital y desglose del ITBMS (7%).</p>
      <a href="<?=APP_URL?>/?action=factura&pedido=<?=$pedido["id"]?>" class="btn btn-gold fw-bold w-100" target="_blank">
        <i class="bi bi-download me-2"></i>Descargar Factura PDF
      </a>
    </div>

    <div class="alert-dark-info mb-4 small">
      <i class="bi bi-info-circle text-info me-2"></i>
      Te contactaremos al número <strong><?=htmlspecialchars($pedido["cliente_telefono"])?></strong> para coordinar la entrega.
    </div>
    <div class="d-flex gap-3 justify-content-center">
      <a href="<?=APP_URL?>/" class="btn btn-gold px-4"><i class="bi bi-house me-2"></i>Volver al Inicio</a>
      <a href="<?=APP_URL?>/?ver=catalogo" class="btn btn-outline-secondary px-4"><i class="bi bi-grid me-2"></i>Seguir comprando</a>
    </div>
  </div>
</div>
<?php include BASE_PATH."/app/Views/layouts/public_footer.php"; ?>
