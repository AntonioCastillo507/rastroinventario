<?php $title="Reportes"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="page-header mb-4">
  <div>
    <h1 class="h4 fw-bold text-white mb-0"><i aria-hidden="true" class="bi bi-file-earmark-bar-graph me-2 text-warning"></i>Reportes y Exportaciones</h1>
    <p class="text-secondary small mb-0">Genera reportes Excel del inventario y estadísticas de ventas</p>
  </div>
</div>

<?php if(!($hayComposer??true)): ?>
<div role="alert" class="alert alert-danger mb-4">
  <i aria-hidden="true" class="bi bi-exclamation-triangle-fill me-2"></i>
  <strong>Composer no instalado.</strong> Abre CMD en <code>C:\wamp64\www\rastroinventario</code> y ejecuta: <code>composer install</code> — luego recarga esta página.
</div>
<?php endif; ?>

<!-- STATS resumen -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon gold"><i aria-hidden="true" class="bi bi-box-seam"></i></div><div class="stat-val"><?=$totalPartes?></div><div class="stat-lbl">Piezas activas</div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon blue"><i aria-hidden="true" class="bi bi-cart-check"></i></div><div class="stat-val"><?=$totalVentas?></div><div class="stat-lbl">Ventas registradas</div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon green"><i aria-hidden="true" class="bi bi-cash-coin"></i></div><div class="stat-val precio num">$<?=number_format($ingresos,2)?></div><div class="stat-lbl">Ingresos totales</div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon gold"><i aria-hidden="true" class="bi bi-bag-check"></i></div><div class="stat-val"><?=$totalPedidos?></div><div class="stat-lbl">Pedidos online</div></div></div>
</div>

<div class="row g-4">
  <!-- Reporte 1: Inventario por categoría -->
  <div class="col-md-6">
    <div class="dark-card h-100">
      <div class="dark-card-header"><i aria-hidden="true" class="bi bi-table me-2 text-warning"></i>Inventario por Categoría</div>
      <div class="card-body p-4">
        <p class="text-secondary small mb-3">Exporta todas las piezas del inventario. Puedes filtrar por sección/categoría.</p>
        <form method="GET" action="<?=APP_URL?>/admin/">
          <input type="hidden" name="mod" value="reportes">
          <input type="hidden" name="action" value="excel_inventario">
          <div class="mb-3">
            <label for="f-seccion" class="form-label-dark">Filtrar por Sección (opcional)</label>
            <select id="f-seccion" name="seccion" class="form-select dark-input form-select-sm">
              <option value="">Todas las secciones</option>
              <?php foreach($secciones as $s): ?>
              <option value="<?=$s["id"]?>"><?=htmlspecialchars($s["nombre"])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-gold fw-bold w-100 <?=!($hayComposer??true)?"disabled":""?>">
            <i aria-hidden="true" class="bi bi-file-earmark-excel me-2"></i>Descargar Excel — Inventario
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Reporte 2: Ventas por mes -->
  <div class="col-md-6">
    <div class="dark-card h-100">
      <div class="dark-card-header"><i aria-hidden="true" class="bi bi-bar-chart me-2 text-warning"></i>Ventas por Mes</div>
      <div class="card-body p-4">
        <p class="text-secondary small mb-3">Estadísticas mensuales: total vendido, unidades y monto por cada mes.</p>
        <a href="<?=APP_URL?>/admin/?mod=reportes&action=excel_ventas_mes"
           class="btn btn-gold fw-bold w-100 mt-5 <?=!($hayComposer??true)?"disabled":""?>">
          <i aria-hidden="true" class="bi bi-file-earmark-excel me-2"></i>Descargar Excel — Ventas por Mes
        </a>
      </div>
    </div>
  </div>

  <!-- Reporte 3: Total por categoría -->
  <div class="col-md-6">
    <div class="dark-card h-100">
      <div class="dark-card-header"><i aria-hidden="true" class="bi bi-pie-chart me-2 text-warning"></i>Total por Categoría</div>
      <div class="card-body p-4">
        <p class="text-secondary small mb-3">Valor de inventario y monto vendido agrupado por cada sección del rastro.</p>
        <a href="<?=APP_URL?>/admin/?mod=reportes&action=excel_categorias"
           class="btn btn-gold fw-bold w-100 mt-5 <?=!($hayComposer??true)?"disabled":""?>">
          <i aria-hidden="true" class="bi bi-file-earmark-excel me-2"></i>Descargar Excel — Total por Categoría
        </a>
      </div>
    </div>
  </div>

  <!-- Reporte 4: Más vendidas -->
  <div class="col-md-6">
    <div class="dark-card h-100">
      <div class="dark-card-header"><i aria-hidden="true" class="bi bi-trophy me-2 text-warning"></i>Piezas Más Vendidas</div>
      <div class="card-body p-4">
        <p class="text-secondary small mb-3">Top 50 de las piezas con mayor cantidad de unidades vendidas e ingresos.</p>
        <a href="<?=APP_URL?>/admin/?mod=reportes&action=excel_mas_vendidas"
           class="btn btn-gold fw-bold w-100 mt-5 <?=!($hayComposer??true)?"disabled":""?>">
          <i aria-hidden="true" class="bi bi-file-earmark-excel me-2"></i>Descargar Excel — Más Vendidas
        </a>
      </div>
    </div>
  </div>
</div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
