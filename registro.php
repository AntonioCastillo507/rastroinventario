<?php
define("BASE_PATH", __DIR__);
require_once __DIR__ . "/bootstrap.php";

$ctrl = new AuthController();
if ($_SERVER["REQUEST_METHOD"] === "POST") $ctrl->procesarRegistro();
else $ctrl->registro();
