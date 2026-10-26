<?php
// Totales del pedido: subtotal, impuesto y total, calculados en el servidor (Itbms). Recibe $d (subtotal, itbms, total).
?>
<dl class="resumen-montos">
  <div class="resumen-fila"><dt>Subtotal</dt><dd class="num" data-resumen="subtotal">$<?=number_format($d["subtotal"],2)?></dd></div>
  <div class="resumen-fila"><dt><?=htmlspecialchars(Marca::impuestoEtiqueta())?></dt><dd class="num" data-resumen="itbms">$<?=number_format($d["itbms"],2)?></dd></div>
  <div class="resumen-fila resumen-total"><dt>Total</dt><dd class="precio text-precio num" data-resumen="total">$<?=number_format($d["total"],2)?></dd></div>
</dl>
