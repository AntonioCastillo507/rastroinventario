<?php
$title="Dirección de entrega"; $cuentaActiva="direccion"; $cargarDireccionJs=true;
include BASE_PATH."/app/Views/layouts/cuenta_abrir.php";
$antes=trim((string)($user["direccion"]??""));
$tieneNueva=!empty($user["dir_calle"]);
?>
<h1 class="cuenta-titulo">Dirección de entrega</h1>
<p class="cuenta-ayuda">Se usa para tus próximos pedidos. Los pedidos que ya hiciste conservan la dirección con la que los hiciste.</p>
<?php if($antes!==""&&!$tieneNueva): ?>
<div role="status" class="alert alert-info mb-3"><strong>Dirección anterior:</strong> <?=htmlspecialchars($antes)?>. Complétala con el formulario para poder usarla.</div>
<?php endif; ?>
<?php if($errores): ?><div role="alert" class="alert-dark-danger d-flex gap-2 mb-3"><i aria-hidden="true" class="bi bi-exclamation-triangle-fill text-danger"></i>Revisa los campos marcados.</div><?php endif; ?>
<form action="<?=APP_URL?>/?action=mi_direccion" method="POST" class="cuenta-tarjeta" novalidate>
  <?=CsrfToken::field()?>
  <?php include BASE_PATH."/app/Views/layouts/direccion_campos.php"; ?>
  <p class="cuenta-ayuda">No podemos comprobar que la calle exista: revisa que esté bien escrita para que el pedido llegue.</p>
  <button type="submit" class="btn btn-gold fw-bold">Guardar dirección</button>
</form>
<?php include BASE_PATH."/app/Views/layouts/cuenta_cerrar.php"; ?>
