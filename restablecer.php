<?php
define("BASE_PATH", __DIR__);
require_once __DIR__ . "/bootstrap.php";

$ctrl = new PasswordResetController();
if ($_SERVER["REQUEST_METHOD"] === "POST") $ctrl->procesarRestablecer();
else $ctrl->restablecer();
