<?php
define("BASE_PATH", __DIR__);
require_once __DIR__."/bootstrap.php";
(new AuthController())->doLogout();
