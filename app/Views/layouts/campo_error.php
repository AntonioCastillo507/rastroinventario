<?php
// Mensaje de error bajo un campo de la dirección. Recibe $campo (clave del campo) y $e (errores por campo).
if (isset($e[$campo])): ?><p class="campo-error" id="e-dir-<?=htmlspecialchars($campo)?>" role="alert"><i aria-hidden="true" class="bi bi-exclamation-circle"></i><span><?=htmlspecialchars($e[$campo])?></span></p><?php endif; ?>
