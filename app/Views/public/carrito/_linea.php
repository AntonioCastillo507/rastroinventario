<?php
// Una línea del paso 1. Recibe $l (id, nombre, compat, precio, cantidad, max, subtotal, imagen_grande, thumbnail), ya leídos de la base.
// Sin JavaScript cada botón es un envío normal con la cantidad ya calculada; con JavaScript (carrito_lista.js) se envía con fetch.

?>
<li class="carrito-linea" data-linea="<?=(int)$l["id"]?>">
  <?php $fotoParte=$l; $fotoTam="thumbnails"; $fotoClase="foto-80"; $fotoAlt=""; include BASE_PATH."/app/Views/layouts/foto_pieza.php"; ?>
  <div class="carrito-linea-info">
    <a class="carrito-nombre" href="<?=APP_URL?>/?action=detalle&id=<?=(int)$l["id"]?>"><?=htmlspecialchars($l["nombre"])?></a>
    <?php if($l["compat"]!==""): ?><p class="carrito-compat"><?=htmlspecialchars($l["compat"])?></p><?php endif; ?>
    <p class="carrito-unitario"><span class="num">$<?=number_format($l["precio"],2)?></span> c/u</p>
  </div>
  <form method="post" action="<?=APP_URL?>/?action=actualizar_carrito&id=<?=(int)$l["id"]?>" class="carrito-cantidad" data-carrito-form>
    <?=CsrfToken::field()?>
    <div class="cantidad-control" role="group" aria-label="Cantidad de <?=htmlspecialchars($l["nombre"])?>">
      <button type="submit" name="cantidad" value="<?=max(1,$l["cantidad"]-1)?>" data-accion="menos" aria-label="Quitar una unidad de <?=htmlspecialchars($l["nombre"])?>"<?=$l["cantidad"]<=1?' disabled':''?>><i aria-hidden="true" class="bi bi-dash-lg"></i></button>
      <span class="cantidad-valor num" data-linea-cantidad><?=(int)$l["cantidad"]?></span>
      <button type="submit" name="cantidad" value="<?=min($l["max"],$l["cantidad"]+1)?>" data-accion="mas" aria-label="Añadir una unidad de <?=htmlspecialchars($l["nombre"])?>"<?=$l["cantidad"]>=$l["max"]?' disabled':''?>><i aria-hidden="true" class="bi bi-plus-lg"></i></button>
    </div>
  </form>
  <p class="carrito-linea-total precio num" data-linea-subtotal>$<?=number_format($l["subtotal"],2)?></p>
  <form method="post" action="<?=APP_URL?>/?action=quitar_carrito&id=<?=(int)$l["id"]?>" class="carrito-quitar" data-carrito-form data-quitar>
    <?=CsrfToken::field()?>
    <button type="submit" class="carrito-quitar-btn" aria-label="Quitar <?=htmlspecialchars($l["nombre"])?> del carrito"><i aria-hidden="true" class="bi bi-trash3"></i></button>
  </form>
</li>
