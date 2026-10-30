<?php
// Dirección de entrega de un pedido en líneas: la copia con campos separados, o el texto libre de los pedidos anteriores al formulario.
// Recibe $pedido (fila de pedidos).
$lineasDir = DireccionPanama::lineas($pedido);
$antigua = trim((string)($pedido["entrega_calle"] ?? "")) === "";
foreach ($lineasDir as $lineaDir): ?><span class="direccion-linea"><?=htmlspecialchars($lineaDir)?></span><?php endforeach;
if ($antigua && $lineasDir): ?><span class="direccion-nota">Escrita antes del formulario de Panamá</span><?php endif; ?>
