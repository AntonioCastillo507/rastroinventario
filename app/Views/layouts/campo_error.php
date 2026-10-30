<?php
// Mensaje de error bajo un campo de la dirección. Recibe $dirCampo (clave del campo) y $dirErr (errores por campo).
if (isset($dirErr[$dirCampo])): ?><p class="campo-error" id="e-dir-<?=htmlspecialchars($dirCampo)?>" role="alert"><i aria-hidden="true" class="bi bi-exclamation-circle"></i><span><?=htmlspecialchars($dirErr[$dirCampo])?></span></p><?php endif; ?>
