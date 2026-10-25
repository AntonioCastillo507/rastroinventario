<?php
// Bloque de marca (DESIGN.md, brand-lockup): logo + nombre + lema, todo dentro del enlace que lo incluye.
// Lo incluyen el encabezado, el pie, el acceso y el panel. $marcaLema = true muestra el lema (solo desde 768 px, por CSS).
$marcaLema = $marcaLema ?? false;
$logoSvg = Logo::enLinea("marca-logo");
$logoPng = Marca::url("logo");
?>
<?php if($logoSvg!==""): ?><?=$logoSvg?><?php elseif($logoPng!==""): ?><img class="marca-logo marca-logo-png" src="<?=htmlspecialchars($logoPng)?>" alt="" onerror="this.style.display='none'"><?php endif; ?>
<span class="marca-texto"><span class="marca-nombre"><?=Marca::h("nombre")?></span><?php if($marcaLema&&Marca::get("eslogan")!==""): ?><span class="marca-lema"><?=Marca::h("eslogan")?></span><?php endif; ?></span>
