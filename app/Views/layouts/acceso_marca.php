<?php
// Bloque de marca de las pantallas de acceso (brand-lockup, DESIGN.md): logo de 48 px + nombre + lema (el lema solo desde 768 px),
// todo en un enlace de al menos 44 px que lleva al inicio. Lo usan login, registro, olvide y restablecer.
?>
<div class="marca-acceso"><a class="marca marca-acceso-enlace" href="<?=APP_URL?>/"><?php $marcaLema = true; include __DIR__ . "/marca_bloque.php"; ?></a></div>
