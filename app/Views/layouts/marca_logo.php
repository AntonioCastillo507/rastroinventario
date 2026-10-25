<?php
// Solo el logo, centrado, para las tarjetas de acceso (decorativo: el nombre ya está en el título)
$logoSvg = Logo::enLinea("marca-logo");
$logoPng = Marca::url("logo");
?>
<?php if($logoSvg!==""||$logoPng!==""): ?><div class="marca-acceso" aria-hidden="true"><?php if($logoSvg!==""): ?><?=$logoSvg?><?php else: ?><img class="marca-logo marca-logo-png" src="<?=htmlspecialchars($logoPng)?>" alt="" onerror="this.style.display='none'"><?php endif; ?></div><?php endif; ?>
