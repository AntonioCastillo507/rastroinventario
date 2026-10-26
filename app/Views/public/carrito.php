<?php
// Paso 1 de la compra: el carrito. Recibe $d (líneas y totales leídos de la base), $deshacer y $errCarro.
$title="Carrito"; $pasoActual=1;
include BASE_PATH."/app/Views/layouts/public_header.php";
$piezas=count($d["lineas"]);
?>
<div class="container-xl carrito-pagina" data-carrito
     data-url-restaurar="<?=APP_URL?>/?action=restaurar_carrito">
  <?php include __DIR__."/carrito/_pasos.php"; ?>
  <div class="catalogo-cabecera">
    <h1 class="catalogo-titulo">Carrito</h1>
    <p class="catalogo-conteo" data-carrito-conteo><?=(int)$piezas?> pieza<?=$piezas!==1?"s":""?></p>
  </div>

  <div id="carrito-avisos">
    <?php if($errCarro): ?><div role="alert" class="alert-dark-danger d-flex gap-2 mb-3"><i aria-hidden="true" class="bi bi-exclamation-triangle-fill text-danger"></i><?=htmlspecialchars($errCarro)?></div><?php endif; ?>
    <?php foreach($d["avisos"] as $aviso): ?><div role="alert" class="alert alert-warning mb-3"><?=htmlspecialchars($aviso)?></div><?php endforeach; ?>
    <?php if($deshacer): ?>
    <div role="status" class="carrito-deshacer" data-deshacer>
      <span>Quitaste «<?=htmlspecialchars($deshacer["nombre"])?>» de tu carrito.</span>
      <form method="post" action="<?=APP_URL?>/?action=restaurar_carrito&id=<?=(int)$deshacer["id"]?>" data-carrito-form data-restaurar>
        <?=CsrfToken::field()?>
        <button type="submit" class="btn btn-sm btn-outline-secondary">Deshacer</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
  <p id="carrito-estado" class="visually-hidden" role="status" aria-live="polite"></p>

  <?php if(!$d["lineas"]): ?>
  <div class="carrito-vacio text-center py-5">
    <h2 class="h5 text-secondary">Tu carrito está vacío</h2>
    <a href="<?=APP_URL?>/?ver=catalogo" class="btn btn-gold mt-3 px-5">Ver catálogo</a>
  </div>
  <?php else: ?>
  <div class="carrito-layout">
    <section aria-label="Piezas del carrito">
      <ul class="carrito-lista" id="carrito-lista" role="list">
        <?php foreach($d["lineas"] as $l): include __DIR__."/carrito/_linea.php"; endforeach; ?>
      </ul>
      <p class="mt-3 mb-0"><a class="carrito-seguir" href="<?=APP_URL?>/?ver=catalogo">Seguir comprando</a></p>
    </section>
    <aside class="carrito-resumen" aria-labelledby="resumen-titulo">
      <h2 id="resumen-titulo" class="carrito-resumen-titulo">Resumen</h2>
      <?php include __DIR__."/carrito/_resumen.php"; ?>
      <p class="carrito-nota">Los precios no incluyen <?=Marca::h("impuesto.nombre")?>; se suma al total.</p>
      <a class="btn btn-gold carrito-continuar" href="<?=APP_URL?>/?action=carrito_datos">Continuar con tus datos</a>
    </aside>
  </div>
  <?php endif; ?>
</div>
<script src="<?=Assets::url("js/carrito_lista.js")?>"></script>
<?php include BASE_PATH."/app/Views/layouts/public_footer.php"; ?>
