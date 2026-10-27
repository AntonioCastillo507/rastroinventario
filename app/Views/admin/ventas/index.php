<?php $title="Ventas"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="panel-cabeza">
  <h1 class="panel-titulo">Registro de ventas <span class="panel-titulo-cuenta">(<?=(int)$pag->total?>)</span></h1>
  <a href="<?=APP_URL?>/admin/?mod=ventas&action=crear" class="btn btn-gold"><i aria-hidden="true" class="bi bi-plus-lg me-1"></i>Nueva venta</a>
</div>
<div class="lista-card">
<table class="lista-tabla">
  <thead><tr><th scope="col">Pieza</th><th scope="col" class="col-num">Cant.</th><th scope="col" class="col-num">Total</th><th scope="col">Vendedor</th><th scope="col">Firma</th><th scope="col">Fecha</th></tr></thead>
  <tbody>
  <?php foreach($ventas as $v): ?>
  <tr>
    <td class="celda-principal"><span class="celda-titulo"><?=htmlspecialchars($v["parte_nombre"]??"—")?></span><span class="celda-sub"><?=htmlspecialchars(trim(($v["marca"]??"")." ".($v["modelo"]??"")))?></span></td>
    <td class="col-num" data-label="Cant."><span class="num"><?=(int)$v["cantidad"]?></span></td>
    <td class="col-num" data-label="Total"><span class="celda-precio text-precio num">$<?=number_format($v["precio_venta"]*$v["cantidad"],2)?></span><span class="celda-sub num">$<?=number_format($v["precio_venta"],2)?> c/u</span></td>
    <td data-label="Vendedor"><?=htmlspecialchars($v["vendedor"]??"—")?></td>
    <td data-label="Firma"><?=$v["firma_ok"]
      ? "<span class=\"chip-success\"><i aria-hidden=\"true\" class=\"bi bi-shield-check me-1\"></i>OK</span>"
      : "<span class=\"chip-danger\"><i aria-hidden=\"true\" class=\"bi bi-shield-x me-1\"></i>Inválida</span>"?></td>
    <td data-label="Fecha"><span class="celda-sub"><?=htmlspecialchars((string)$v["fecha"])?></span></td>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($ventas)): $vaciaColspan=6; $vaciaTitulo="Aún no hay ventas"; $vaciaTexto="Registra una venta para que aparezca en esta lista."; $vaciaAccion=[APP_URL."/admin/?mod=ventas&action=crear","Nueva venta"]; include BASE_PATH."/app/Views/layouts/lista_vacia.php"; endif; ?>
  </tbody>
</table>
</div>
<?php $pagBase=APP_URL."/admin/"; include BASE_PATH."/app/Views/layouts/paginacion.php"; ?>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
