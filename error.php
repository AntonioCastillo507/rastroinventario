<?php
// Destino de ErrorDocument de Apache (.htaccess): ?codigo=403|404|500|503. Cualquier otro valor se trata como 404.
// No refleja nada de la petición: el texto es fijo.
define("BASE_PATH", __DIR__);
require_once __DIR__."/bootstrap.php";

$codigo = (int)(is_string($_GET["codigo"] ?? null) ? $_GET["codigo"] : 404);
PaginaError::mostrar(in_array($codigo, PaginaError::CODIGOS, true) ? $codigo : 404);
