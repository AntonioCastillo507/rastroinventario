<?php $title="Herramientas"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="page-header mb-4">
  <div><h4 class="fw-bold text-white mb-0 dash-title"><i class="bi bi-tools me-2 text-warning"></i>Herramientas</h4></div>
</div>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="dark-card">
      <div class="dark-card-header"><i class="bi bi-eraser me-2 text-danger"></i>Limpiar datos de prueba</div>
      <div class="card-body p-4">
        <p class="text-secondary small">
          Esto borra <strong class="text-white">todos</strong> los pedidos, ventas y cuentas de
          cliente registradas (útil antes de la presentación oficial). No toca autos, partes,
          secciones ni las cuentas de Administrador / Vendedor / Consultor.
        </p>
        <div class="row g-3 mb-3">
          <div class="col-4 text-center">
            <div class="stat-val red"><?=$totalPedidos?></div>
            <div class="stat-lbl">Pedidos</div>
          </div>
          <div class="col-4 text-center">
            <div class="stat-val red"><?=$totalVentas?></div>
            <div class="stat-lbl">Ventas</div>
          </div>
          <div class="col-4 text-center">
            <div class="stat-val red"><?=$totalClientes?></div>
            <div class="stat-lbl">Cuentas cliente</div>
          </div>
        </div>
        <form action="<?=APP_URL?>/admin/?mod=herramientas&action=limpiarPrueba" method="POST"
              onsubmit="return confirm('¿Seguro? Esto borra pedidos, ventas y cuentas de cliente de forma permanente.');">
          <?=CsrfToken::field()?>
          <button type="submit" class="btn btn-danger fw-bold w-100">
            <i class="bi bi-eraser me-2"></i>Borrar datos de prueba
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
