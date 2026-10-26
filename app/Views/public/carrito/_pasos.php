<?php
// Indicador de pasos de la compra (DESIGN.md E8). Recibe $pasoActual (1 o 2). Texto y número: el paso nunca se indica solo con color.
$pasoActual = (int)($pasoActual ?? 1);
?>
<nav class="pasos-nav" aria-label="Pasos de la compra">
  <ol class="pasos">
    <li class="paso<?=$pasoActual===1?' paso-actual':' paso-hecho'?>"<?=$pasoActual===1?' aria-current="step"':''?>><span class="paso-num" aria-hidden="true">1</span><span class="paso-texto"><span class="visually-hidden">Paso 1 de 2: </span>Carrito</span></li>
    <li class="paso<?=$pasoActual===2?' paso-actual':''?>"<?=$pasoActual===2?' aria-current="step"':''?>><span class="paso-num" aria-hidden="true">2</span><span class="paso-texto"><span class="visually-hidden">Paso 2 de 2: </span>Tus datos</span></li>
  </ol>
</nav>
