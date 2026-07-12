<?php
$title="Mis Pedidos — Rastro del Istmo";
include BASE_PATH."/app/Views/layouts/public_header.php";
?>
<div class="container py-4">
  <h4 class="fw-bold text-white mb-4"><i class="bi bi-bag-check me-2 text-warning"></i>Mis Pedidos</h4>
  <?php if(empty($pedidos)): ?>
  <div class="text-center py-5"><i class="bi bi-bag display-1 text-secondary opacity-25"></i>
    <h5 class="text-secondary mt-3">Aún no tienes pedidos</h5>
    <a href="<?=APP_URL?>/?ver=catalogo" class="btn btn-gold mt-3">Explorar catálogo</a>
  </div>
  <?php else: ?>
  <div class="dark-card"><div class="table-responsive">
  <table class="table dark-table mb-0">
    <thead><tr><th>#</th><th>Total</th><th>Ítems</th><th>Estado</th><th>Fecha</th><th>Factura</th></tr></thead>
    <tbody><?php foreach($pedidos as $p): ?>
    <tr>
      <td class="text-warning fw-bold">#<?=$p["id"]?></td>
      <td class="text-success fw-bold">$<?=number_format($p["total"],2)?></td>
      <td class="text-secondary"><?=$p["total_items"]?> pieza(s)</td>
      <td><span class="estado-<?=$p["estado"]?> fw-semibold"><?=ucfirst($p["estado"])?></span></td>
      <td class="text-secondary small"><?=date("d/m/Y H:i",strtotime($p["creado_en"]))?></td>
      <td><a href="<?=APP_URL?>/?action=factura&pedido=<?=$p["id"]?>" class="btn btn-sm btn-outline-warning" target="_blank" title="Descargar factura"><i class="bi bi-file-earmark-pdf"></i></a></td>
    </tr>
    <?php endforeach; ?></tbody>
  </table></div></div>
  <?php endif; ?>
</div>
<?php include BASE_PATH."/app/Views/layouts/public_footer.php"; ?>
