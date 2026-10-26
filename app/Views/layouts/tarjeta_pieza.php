<?php
/**
 * Tarjeta de pieza reutilizable: catálogo, portada, estantes (piezas destacadas, relacionadas).
 * Foto 1:1 completa sobre la baldosa blanca, nombre (enlace extendido: toda la tarjeta lleva al detalle), compatibilidad,
 * insignia de stock con texto y color, precio a 20 px (token --precio) y "Añadir al carrito" (sin recargar con JavaScript;
 * sin él, el formulario vuelve a esta misma página en la tarjeta). En teléfono es una fila horizontal con la foto de 96 px.
 * Recibe $tarjetaParte (fila de la pieza) y $tarjetaVolver (ruta interna a la que vuelve el formulario sin JavaScript).
 */
$tp = $tarjetaParte;
$tpId = (int)$tp["id"];
$tpCant = (int)($tp["cantidad"] ?? 0);
$tpCompat = trim(($tp["marca"] ?? "") . " " . ($tp["modelo"] ?? ""));
if ($tpCompat !== "" && !empty($tp["anio"])) $tpCompat .= " · " . $tp["anio"];
[$tpClase, $tpTexto] = $tpCant <= 0 ? ["stock-out", "Agotado"] : ($tpCant <= 5 ? ["stock-low", "Quedan " . $tpCant] : ["stock-ok", "Disponible"]);
$tpUrl = APP_URL . "/?action=detalle&id=" . $tpId;
?>
<article class="tarjeta-pieza" id="parte-<?=$tpId?>">
  <span class="tarjeta-foto"><?php $fotoParte = $tp; $fotoTam = "thumbnails"; $fotoClase = "foto-ancha"; $fotoAlt = ""; $fotoLazy = true; include BASE_PATH . "/app/Views/layouts/foto_pieza.php"; ?></span>
  <div class="tarjeta-cuerpo">
    <h3 class="tarjeta-titulo" id="parte-titulo-<?=$tpId?>"><a class="tarjeta-enlace" href="<?=htmlspecialchars($tpUrl)?>"><?=htmlspecialchars($tp["nombre"])?></a></h3>
    <?php if($tpCompat !== ""): ?><p class="tarjeta-compat"><?=htmlspecialchars($tpCompat)?></p><?php endif; ?>
    <span class="stock-insignia <?=$tpClase?>"><?=$tpTexto?></span>
    <div class="tarjeta-pie">
      <span class="precio num">$<?=number_format((float)$tp["precio"], 2)?></span>
      <?php if($tpCant > 0): ?>
      <form method="POST" action="<?=APP_URL?>/?action=agregar_carrito&id=<?=$tpId?>" class="tarjeta-form" data-agregar-carrito data-nombre="<?=htmlspecialchars($tp["nombre"])?>">
        <?=CsrfToken::field()?>
        <input type="hidden" name="volver" value="<?=htmlspecialchars($tarjetaVolver ?? "")?>">
        <button type="submit" class="btn btn-gold tarjeta-agregar" aria-describedby="parte-titulo-<?=$tpId?>"><i aria-hidden="true" class="bi bi-cart-plus"></i><span class="tarjeta-agregar-texto">Añadir al carrito</span></button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</article>
