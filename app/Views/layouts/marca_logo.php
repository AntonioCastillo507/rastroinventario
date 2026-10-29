<?php
/**
 * Casilla de la marca: su logo en una sola tinta (máscara sobre currentColor) o, sin logo, su monograma.
 * Variables: $mlNombre (nombre de la marca), $mlLogo (archivo guardado o null), $mlClase (clases extra, opcional)
 * y $mlNombreVisible (true: el nombre va escrito debajo, como en las tarjetas de la portada).
 * Es decorativa (aria-hidden): quien la usa escribe el nombre de la marca al lado o como texto oculto.
 */
$mlUrl = LogoMarca::url($mlLogo ?? null);
?>
<span class="marca-casilla<?=!empty($mlClase)?" ".htmlspecialchars($mlClase):""?>" aria-hidden="true"><?php if($mlUrl!==""): ?><span class="marca-mascara" style="--logo-url:url('<?=htmlspecialchars($mlUrl,ENT_QUOTES)?>')"></span><?php else: ?><span class="marca-monograma"><?=htmlspecialchars(MarcaAutoModel::iniciales((string)($mlNombre??"")))?></span><?php endif; ?><?php if(!empty($mlNombreVisible)): ?><span class="marca-nombre-visible"><?=htmlspecialchars((string)($mlNombre??""))?></span><?php endif; ?></span>
