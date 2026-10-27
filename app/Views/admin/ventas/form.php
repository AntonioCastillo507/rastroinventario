<?php $title="Nueva Venta"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="panel-cabeza">
  <div>
    <a href="<?=APP_URL?>/admin/?mod=ventas" class="enlace-volver"><i aria-hidden="true" class="bi bi-arrow-left"></i>Volver a ventas</a>
    <h1 class="panel-titulo">Registrar Venta</h1>
  </div>
</div>
<?php if($error??""): ?><div role="alert" class="alert alert-danger d-flex align-items-center gap-2 mb-4"><i aria-hidden="true" class="bi bi-exclamation-triangle-fill"></i><span><?=htmlspecialchars($error)?></span></div><?php endif; ?>
<div class="form-panel"><div class="form-card">
<form action="<?=APP_URL?>/admin/?mod=ventas&action=crear" method="POST">
  <?=CsrfToken::field()?>
  <div class="mb-4">
    <label for="parteSelect" class="form-label-dark">Parte del Inventario *</label>
    <select name="parte_id" id="parteSelect" class="form-select dark-input" required onchange="setPrecio(this)">
      <option value="">— Seleccionar parte —</option>
      <?php foreach($partes as $p): ?>
      <option value="<?=$p["id"]?>" data-precio="<?=$p["precio"]?>" data-stock="<?=$p["cantidad"]?>">
        <?=htmlspecialchars($p["nombre"]." — ".($p["marca"]??"")." ".($p["modelo"]??"")." | $".$p["precio"]." | Stock: ".$p["cantidad"])?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div id="stockAlert" role="status" class="alert alert-info mb-4 d-none">
    <i aria-hidden="true" class="bi bi-info-circle icon-info me-2"></i><span id="stockMsg"></span>
  </div>
  <div class="mb-4">
    <label for="precioVenta" class="form-label-dark">Precio de Venta ($) *</label>
    <input type="number" name="precio_venta" id="precioVenta" class="form-control dark-input" step="0.01" min="0.01" required value="0.00">
    <div class="text-secondary small mt-1">Puedes ajustar el precio antes de confirmar.</div>
  </div>
  <div class="mb-4">
    <label for="cantVenta" class="form-label-dark">Cantidad *</label>
    <input type="number" name="cantidad" id="cantVenta" class="form-control dark-input" min="1" value="1" required>
  </div>
  <div id="totalPreview" class="total-preview mb-4 d-none">
    Total estimado: <span id="totalVal" class="text-precio fw-bold"></span>
  </div>
  <div class="form-acciones">
    <button type="submit" class="btn btn-gold fw-bold"><i aria-hidden="true" class="bi bi-cart-check me-2"></i>Confirmar y registrar venta</button>
    <a href="<?=APP_URL?>/admin/?mod=ventas" class="btn btn-outline-secondary">Cancelar</a>
  </div>
</form>
</div>
</div>
<script>
function setPrecio(sel){
  var opt=sel.options[sel.selectedIndex];
  var precio=parseFloat(opt.dataset.precio)||0;
  var stock=parseInt(opt.dataset.stock)||0;
  document.getElementById("precioVenta").value=precio.toFixed(2);
  document.getElementById("cantVenta").max=stock;
  var al=document.getElementById("stockAlert");
  document.getElementById("stockMsg").textContent="Stock disponible: "+stock+" unidades.";
  al.classList.toggle("d-none",!opt.value);
  calcTotal();
}
function calcTotal(){
  var p=parseFloat(document.getElementById("precioVenta").value)||0;
  var c=parseInt(document.getElementById("cantVenta").value)||0;
  var t=document.getElementById("totalPreview");
  document.getElementById("totalVal").textContent="$"+(p*c).toFixed(2);
  t.classList.toggle("d-none",!(p&&c));
}
document.getElementById("precioVenta").addEventListener("input",calcTotal);
document.getElementById("cantVenta").addEventListener("input",calcTotal);
</script>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
