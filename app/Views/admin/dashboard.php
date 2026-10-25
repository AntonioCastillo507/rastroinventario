<?php $title="Dashboard"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h1 class="h4 fw-bold text-white mb-0 dash-title"><i aria-hidden="true" class="bi bi-speedometer2 me-2 text-warning"></i>Panel de Control</h1>
  <p class="text-secondary small mb-0">Bienvenido, <?=htmlspecialchars($_SESSION["user"]["nombre"]??"")?></p></div>
  <span class="badge-date"><?=date("d M Y, H:i")?></span>
</div>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <a href="<?=APP_URL?>/admin/?mod=partes" class="stat-card d-block" style="text-decoration:none">
      <div class="stat-icon gold"><i aria-hidden="true" class="bi bi-box-seam"></i></div>
      <div class="stat-val"><?=$totalPartes?></div><div class="stat-lbl">Partes activas</div>
    </a>
  </div>
  <div class="col-6 col-lg-3">
    <a href="<?=APP_URL?>/admin/?mod=partes&filtro=stock_bajo" class="stat-card d-block" style="text-decoration:none">
      <div class="stat-icon red"><i aria-hidden="true" class="bi bi-exclamation-triangle"></i></div>
      <div class="stat-val red"><?=$totalStockBajo?></div><div class="stat-lbl">Stock bajo (≤5)</div>
    </a>
  </div>
  <div class="col-6 col-lg-3">
    <a href="<?=APP_URL?>/admin/?mod=pedidos" class="stat-card d-block" style="text-decoration:none">
      <div class="stat-icon blue"><i aria-hidden="true" class="bi bi-bag-check"></i></div>
      <div class="stat-val"><?=$pedidosPendientes?></div><div class="stat-lbl">Pedidos pendientes</div>
    </a>
  </div>
  <div class="col-6 col-lg-3">
    <a href="<?=APP_URL?>/admin/?mod=ventas" class="stat-card d-block" style="text-decoration:none">
      <div class="stat-icon green"><i aria-hidden="true" class="bi bi-cash-coin"></i></div>
      <div class="stat-val green num">$<?=number_format($totalVendido,2)?></div><div class="stat-lbl">Total vendido</div>
    </a>
  </div>
</div>
<div class="row g-4">
  <div class="col-xl-8"><div class="dark-card">
    <div class="dark-card-header"><i aria-hidden="true" class="bi bi-clock-history me-2 text-warning"></i>Bitácora de Accesos Recientes</div>
    <div class="table-responsive"><table class="table dark-table mb-0">
      <thead><tr><th>Email</th><th>IP</th><th>Resultado</th><th>Fecha</th></tr></thead>
      <tbody><?php foreach($logs as $l): ?>
      <tr>
        <td class="small text-secondary"><?=htmlspecialchars($l["email_intento"])?></td>
        <td><code class="text-warning small"><?=htmlspecialchars($l["ip"])?></code></td>
        <td><?=$l["exitoso"]?"<span class=\"chip-success\"><i aria-hidden=\"true\" class=\"bi bi-check-circle me-1\"></i>OK</span>":"<span class=\"chip-danger\"><i aria-hidden=\"true\" class=\"bi bi-x-circle me-1\"></i>Fallido</span>"?></td>
        <td class="small text-secondary"><?=$l["fecha"]?></td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($logs)): ?><tr><td colspan="4" class="text-center text-secondary py-3">Sin registros.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div></div>
  <div class="col-xl-4"><div class="dark-card h-100">
    <div class="dark-card-header"><i aria-hidden="true" class="bi bi-lightning-charge me-2 text-warning"></i>Accesos Rápidos</div>
    <div>
      <a href="<?=APP_URL?>/admin/?mod=partes&action=crear" class="quick-link"><i aria-hidden="true" class="bi bi-plus-circle text-success"></i><span>Agregar Parte</span><i aria-hidden="true" class="bi bi-chevron-right text-secondary ms-auto"></i></a>
      <a href="<?=APP_URL?>/admin/?mod=pedidos" class="quick-link">
        <i aria-hidden="true" class="bi bi-bag-check text-warning"></i><span>Ver Pedidos</span>
        <?php if($pedidosPendientes>0): ?><span class="badge bg-danger rounded-pill ms-auto me-2"><?=$pedidosPendientes?></span><?php endif; ?>
        <i aria-hidden="true" class="bi bi-chevron-right text-secondary"></i>
      </a>
      <a href="<?=APP_URL?>/admin/?mod=ventas&action=crear" class="quick-link"><i aria-hidden="true" class="bi bi-cart-plus icon-info"></i><span>Registrar Venta</span><i aria-hidden="true" class="bi bi-chevron-right text-secondary ms-auto"></i></a>
      <a href="<?=APP_URL?>/admin/?mod=comentarios" class="quick-link"><i aria-hidden="true" class="bi bi-chat-dots icon-info"></i><span>Ver Comentarios</span><i aria-hidden="true" class="bi bi-chevron-right text-secondary ms-auto"></i></a>
      <a href="<?=APP_URL?>/admin/?mod=usuarios&action=crear" class="quick-link"><i aria-hidden="true" class="bi bi-person-plus text-warning"></i><span>Nuevo Usuario</span><i aria-hidden="true" class="bi bi-chevron-right text-secondary ms-auto"></i></a>
    </div>
  </div></div>
</div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
