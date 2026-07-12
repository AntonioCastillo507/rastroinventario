<?php $title="Pedidos"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="page-header mb-4">
  <div><h4 class="fw-bold text-white mb-0"><i class="bi bi-bag-check me-2 text-warning"></i>Pedidos de la Tienda</h4>
  <p class="text-secondary small mb-0">Compras realizadas desde la tienda pública</p></div>
</div>
<div class="dark-card">
<div class="table-responsive">
<table class="table dark-table mb-0">
  <thead><tr><th>#</th><th>Cliente</th><th>Email</th><th>Teléfono</th><th>Total</th><th>Estado</th><th>Fecha</th><th>Acción</th></tr></thead>
  <tbody>
  <?php foreach($pedidos as $p): ?>
  <tr>
    <td class="text-warning fw-bold">#<?=$p["id"]?></td>
    <td class="fw-semibold text-white"><?=htmlspecialchars($p["cliente_nombre"])?></td>
    <td class="text-secondary small"><?=htmlspecialchars($p["cliente_email"])?></td>
    <td class="text-secondary small"><?=htmlspecialchars($p["cliente_telefono"])?></td>
    <td class="fw-bold text-success">$<?=number_format($p["total"],2)?></td>
    <td><span class="estado-<?=$p["estado"]?> fw-semibold chip-<?=match($p["estado"]){"pendiente"=>"warning","procesando"=>"info","entregado"=>"success",default=>"danger"} ?>"><?=ucfirst($p["estado"])?></span></td>
    <td class="text-secondary small"><?=date("d/m/Y H:i",strtotime($p["creado_en"]))?></td>
    <td><a href="<?=APP_URL?>/admin/?mod=pedidos&action=detalle&id=<?=$p["id"]?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-eye me-1"></i>Ver</a></td>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($pedidos)): ?><tr><td colspan="8" class="text-center text-secondary py-4">No hay pedidos registrados aún.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
</div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
