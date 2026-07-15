<?php
define("DB_HOST",  "localhost");
define("DB_USER",  "root");
define("DB_PASS",  "");
define("DB_NAME",  "rastroinventario");
define("APP_URL",  "http://localhost/rastroinventario");
define("HMAC_KEY", "R4str0_S3cr3t_Inv_2025!");
require_once __DIR__ . "/rsa_secret.php"; // passphrase fuera del repositorio, ver .gitignore
// ── EmailJS () ──────────────────────
define("EMAILJS_ENABLED", true);
define("EMAILJS_SERVICE_ID", "service_k7lz71j");
define("EMAILJS_PUBLIC_KEY", "6DHgYN-Qkx8jfW4mA");
define("EMAILJS_PRIVATE_KEY", "Ev8qHRKFqv4M5lHc4CCZY");
define("EMAILJS_TEMPLATE_BIENVENIDA", "template_0t9yx44");
define("EMAILJS_TEMPLATE_PEDIDO", "template_mopzbjp");
