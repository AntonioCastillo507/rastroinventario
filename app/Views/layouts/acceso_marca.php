<?php
// Marca de las pantallas de acceso. Con $accesoIcono = true (login, registro, olvide y restablecer) va solo el icono de la R,
// pequeño y a la izquierda, dentro de un enlace de 44 px al inicio con el nombre como texto accesible. Sin él (página de error)
// sigue el brand-lockup completo: logo de 48 px + nombre + lema (el lema solo desde 768 px).
$accesoIcono = $accesoIcono ?? false;
?>
<?php if($accesoIcono): ?>
<div class="marca-acceso marca-acceso--icono"><a class="marca-acceso-enlace" href="<?=APP_URL?>/" aria-label="<?=Marca::h("nombre")?>, ir al inicio"><?php $logoSvg = Logo::enLinea("marca-logo"); $logoPng = Marca::url("logo"); ?><?php if($logoSvg!==""): ?><?=$logoSvg?><?php elseif($logoPng!==""): ?><img class="marca-logo marca-logo-png" src="<?=htmlspecialchars($logoPng)?>" alt="" onerror="this.style.display='none'"><?php endif; ?></a></div>
<?php else: ?>
<div class="marca-acceso"><a class="marca marca-acceso-enlace" href="<?=APP_URL?>/"><?php $marcaLema = true; include __DIR__ . "/marca_bloque.php"; ?></a></div>
<?php endif; ?>
