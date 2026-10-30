<?php
// Abre la página de «Mi cuenta»: cabecera de la tienda, menú de la cuenta y la columna de contenido.
// Antes: $title y $cuentaActiva. Después: layouts/cuenta_cerrar.php.
include BASE_PATH."/app/Views/layouts/public_header.php";
?>
<div class="container-xl cuenta">
  <div class="cuenta-grid">
    <?php include BASE_PATH."/app/Views/layouts/cuenta_nav.php"; ?>
    <div class="cuenta-contenido">
