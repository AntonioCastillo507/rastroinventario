<?php
// Navegación de «Mi cuenta». Recibe $cuentaActiva: perfil | pedidos | direccion | clave
$cuentaItems = [
    "perfil"    => ["mi_perfil",    "Mi perfil"],
    "pedidos"   => ["mis_pedidos",  "Mis pedidos"],
    "direccion" => ["mi_direccion", "Dirección de entrega"],
    "clave"     => ["mi_clave",     "Cambiar contraseña"],
];
?>
<nav class="cuenta-nav" aria-label="Mi cuenta">
  <ul class="cuenta-nav-lista">
    <?php foreach($cuentaItems as $clave=>[$accion,$texto]): $actual=($cuentaActiva??"")===$clave; ?>
    <li><a class="cuenta-nav-enlace<?=$actual?" activo":""?>" href="<?=APP_URL?>/?action=<?=$accion?>"<?=$actual?' aria-current="page"':""?>><?=htmlspecialchars($texto)?></a></li>
    <?php endforeach; ?>
  </ul>
  <form method="post" action="<?=APP_URL?>/logout.php" class="cuenta-nav-salir"><?=CsrfToken::field()?><button type="submit" class="cuenta-nav-enlace" data-no-bloquear>Cerrar sesión</button></form>
</nav>
