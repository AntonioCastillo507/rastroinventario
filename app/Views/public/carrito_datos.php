<?php
// Paso 2 de la compra: tus datos. Recibe $d (líneas y totales leídos de la base), $viejo, $err, $isCliente, $userInfo y $huella.
// El resumen es UN solo bloque: barra lateral fija desde 992 px y, por debajo, una hoja inferior (offcanvas) que abre la barra "Ver resumen".
$title="Tus datos"; $pasoActual=2;
include BASE_PATH."/app/Views/layouts/public_header.php";
?>
<div class="container-xl carrito-pagina">
  <?php include __DIR__."/carrito/_pasos.php"; ?>
  <div class="catalogo-cabecera">
    <h1 class="catalogo-titulo">Tus datos</h1>
    <p class="catalogo-conteo"><a class="carrito-seguir" href="<?=APP_URL?>/?action=carrito">Volver al carrito</a></p>
  </div>
  <?php if($err): ?><div role="alert" class="alert-dark-danger d-flex gap-2 mb-3"><i aria-hidden="true" class="bi bi-exclamation-triangle-fill text-danger"></i><?=htmlspecialchars($err)?></div><?php endif; ?>
  <?php foreach($d["avisos"] as $aviso): ?><div role="alert" class="alert alert-warning mb-3"><?=htmlspecialchars($aviso)?></div><?php endforeach; ?>

  <div class="carrito-datos-layout">
    <form method="post" action="<?=APP_URL?>/?action=procesar_carrito" id="form-datos" class="carrito-datos-form">
      <?=CsrfToken::field()?>
      <input type="hidden" name="resumen_visto" value="<?=htmlspecialchars($huella)?>">
      <?php include __DIR__."/carrito/_entrega.php"; ?>
      <?php if(!$isCliente) include __DIR__."/carrito/_cuenta.php"; ?>
      <button type="submit" class="btn btn-gold carrito-confirmar">Confirmar pedido · <span class="num">$<?=number_format($d["total"],2)?></span></button>
    </form>
    <aside id="resumen-hoja" class="carrito-resumen carrito-resumen-compacto offcanvas-lg offcanvas-bottom" tabindex="-1" aria-labelledby="resumen-titulo">
      <div class="offcanvas-header">
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#resumen-hoja" aria-label="Cerrar el resumen"></button>
      </div>
      <div class="offcanvas-body">
        <div class="resumen-tarjeta">
        <h2 id="resumen-titulo" class="carrito-resumen-titulo">Tu pedido</h2>
        <ul class="resumen-lineas" role="list">
          <?php foreach($d["lineas"] as $l): ?>
          <li class="resumen-linea">
            <?php $fotoParte=$l; $fotoTam="thumbnails"; $fotoClase="foto-48"; $fotoAlt=""; include BASE_PATH."/app/Views/layouts/foto_pieza.php"; ?>
            <span class="resumen-linea-nombre"><?=htmlspecialchars($l["nombre"])?> <span class="resumen-linea-cant">×<span class="num"><?=(int)$l["cantidad"]?></span></span></span>
            <span class="num resumen-linea-monto">$<?=number_format($l["subtotal"],2)?></span>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php include __DIR__."/carrito/_resumen.php"; ?>
        <p class="carrito-nota">Los precios no incluyen <?=Marca::h("impuesto.nombre")?>; se suma al total.</p>
        </div>
      </div>
    </aside>
  </div>
</div>
<div class="barra-resumen">
  <p class="barra-resumen-total">Total <span class="precio text-precio num">$<?=number_format($d["total"],2)?></span></p>
  <button type="button" class="btn btn-outline-secondary barra-resumen-boton" data-bs-toggle="offcanvas" data-bs-target="#resumen-hoja" aria-controls="resumen-hoja">Ver resumen</button>
</div>
<?php include BASE_PATH."/app/Views/layouts/public_footer.php"; ?>
