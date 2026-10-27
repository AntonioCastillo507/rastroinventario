<?php
/**
 * Estado vacío de una lista del panel: una fila con un mensaje que dice qué pasa y qué hacer.
 * Variables: $vaciaColspan (columnas de la tabla), $vaciaTitulo, $vaciaTexto y, opcional, $vaciaAccion = [url, texto] (un solo botón).
 */
$vaciaAccion = $vaciaAccion ?? null;
?><tr class="lista-vacia"><td colspan="<?=(int)$vaciaColspan?>">
  <div class="vacio">
    <p class="vacio-titulo"><?=htmlspecialchars($vaciaTitulo)?></p>
    <p class="vacio-texto"><?=htmlspecialchars($vaciaTexto)?></p>
    <?php if($vaciaAccion): ?><a class="btn btn-gold" href="<?=htmlspecialchars($vaciaAccion[0])?>"><?=htmlspecialchars($vaciaAccion[1])?></a><?php endif; ?>
  </div>
</td></tr>
