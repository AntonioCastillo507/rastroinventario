<?php $title="Ventas"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="page-header mb-4">
  <div><h1 class="h4 fw-bold text-white mb-0"><i aria-hidden="true" class="bi bi-cart-check me-2 text-warning"></i>Registro de Ventas</h1></div>
  <a href="<?=APP_URL?>/admin/?mod=ventas&action=crear" class="btn btn-gold btn-sm"><i aria-hidden="true" class="bi bi-plus-lg me-1"></i>Nueva Venta</a>
</div>
<div class="dark-card">
<div class="table-responsive">
<table class="table dark-table mb-0">
  <thead><tr><th>#</th><th>Parte</th><th>Auto</th><th>Cant.</th><th>Precio Unit.</th><th>Total</th><th>Vendedor</th><th>Firma</th><th>Fecha</th></tr></thead>
  <tbody>
  <?php foreach($ventas as $v): ?>
  <tr>
    <td class="small text-secondary"><?=$v["id"]?></td>
    <td class="fw-semibold text-white"><?=htmlspecialchars($v["parte_nombre"]??"—")?></td>
    <td class="small text-secondary"><?=htmlspecialchars(($v["marca"]??"")." ".($v["modelo"]??""))?></td>
    <td class="text-white num"><?=$v["cantidad"]?></td>
    <td class="text-secondary num">$<?=number_format($v["precio_venta"],2)?></td>
    <td class="fw-bold text-success num">$<?=number_format($v["precio_venta"]*$v["cantidad"],2)?></td>
    <td class="small text-secondary"><?=htmlspecialchars($v["vendedor"]??"—")?></td>
    <td><?=$v["firma_ok"]
      ? "<span class=\"chip-success\"><i aria-hidden=\"true\" class=\"bi bi-shield-check me-1\"></i>OK</span>"
      : "<span class=\"chip-danger\"><i aria-hidden=\"true\" class=\"bi bi-shield-x me-1\"></i>Inválida</span>"?></td>
    <td class="small text-secondary"><?=$v["fecha"]?></td>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($ventas)): ?><tr><td colspan="9" class="text-center text-secondary py-4">Sin ventas registradas.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
</div>
<?php $pagBase=APP_URL."/admin/"; include BASE_PATH."/app/Views/layouts/paginacion.php"; ?>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
