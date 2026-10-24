<?php
// Carga datos de volumen en rastroinventario_test para ver la paginación en el navegador.
// Se corre con:  composer test:volumen   (se deshace con:  composer test:reset)
// tests/bootstrap.php aborta si la base no es exactamente rastroinventario_test.
require __DIR__ . "/../bootstrap.php";

$db = Database::get();
\Tests\Support\BaseDatos::verificarBase($db);
\Tests\Support\Volumen::crear($db);
foreach (["partes", "usuarios", "pedidos", "comentarios", "vendido_parte"] as $t) {
    echo str_pad($t, 15), $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn(), PHP_EOL;
}
