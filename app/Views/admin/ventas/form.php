<?php $title="Nueva Venta"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="page-header mb-4">
  <div class="d-flex align-items-center gap-3">
    <a href="<?=APP_URL?>/admin/?mod=ventas" class="btn-back" aria-label="Volver"><i aria-hidden="true" class="bi bi-arrow-left"></i></a>
    <h1 class="h4 fw-bold text-white mb-0">Registrar Venta</h1>
  </div>
</div>
<?php if($error??""): ?><div role="alert" class="alert-dark-danger d-flex gap-2 mb-4"><i aria-hidden="true" class="bi bi-exclamation-triangle-fill text-danger"></i><?=htmlspecialchars($error)?></div><?php endif; ?>
<div class="dark-card" style="max-width:580px">
<div class="card-body p-4">
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
  <div id="stockAlert" role="status" class="alert-dark-info mb-4 d-none">
    <i aria-hidden="true" class="bi bi-info-circle text-info me-2"></i><span id="stockMsg"></span>
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
    Total estimado: <span id="totalVal" class="text-success fw-bold"></span>
  </div>
  <button type="submit" class="btn btn-gold fw-bold w-100 py-2">
    <i aria-hidden="true" class="bi bi-cart-check me-2"></i>Confirmar y Registrar Venta
  </button>
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
