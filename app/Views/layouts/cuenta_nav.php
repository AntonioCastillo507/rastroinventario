<?php
// Navegación de «Mi cuenta», con el patrón del menú lateral del panel. Recibe $cuentaActiva: perfil | pedidos | direccion | clave
$cuentaItems = [
    "perfil"    => ["mi_perfil",    "Mi perfil",            "person"],
    "pedidos"   => ["mis_pedidos",  "Mis pedidos",          "bag-check"],
    "direccion" => ["mi_direccion", "Dirección de entrega", "geo-alt"],
    "clave"     => ["mi_clave",     "Cambiar contraseña",   "key"],
];
?>
<nav class="cuenta-nav" aria-label="Mi cuenta">
  <ul class="cuenta-nav-lista">
    <?php foreach($cuentaItems as $clave=>[$accion,$texto,$icono]): $actual=($cuentaActiva??"")===$clave; ?>
    <li><a class="cuenta-nav-enlace<?=$actual?" activo":""?>" href="<?=APP_URL?>/?action=<?=htmlspecialchars($accion)?>"<?=$actual?' aria-current="page"':""?>><i aria-hidden="true" class="bi bi-<?=htmlspecialchars($icono)?>"></i><span><?=htmlspecialchars($texto)?></span></a></li>
    <?php endforeach; ?>
  </ul>
  <form method="post" action="<?=APP_URL?>/logout.php" class="cuenta-nav-salir"><?=CsrfToken::field()?><button type="submit" class="cuenta-nav-enlace cuenta-nav-peligro" data-no-bloquear><i aria-hidden="true" class="bi bi-box-arrow-right"></i><span>Cerrar sesión</span></button></form>
</nav>
