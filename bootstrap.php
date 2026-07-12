<?php
if (!defined("BASE_PATH")) define("BASE_PATH", __DIR__);
define("UPLOAD_PATH", BASE_PATH . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR);

date_default_timezone_set("America/Panama");

require_once BASE_PATH."/app/Config/config.php";
require_once BASE_PATH."/app/Interfaces/CrudInterface.php";
require_once BASE_PATH."/app/Interfaces/ErrorHandlerInterface.php";
require_once BASE_PATH."/app/Interfaces/CryptoInterface.php";
require_once BASE_PATH."/app/Config/Database.php";
require_once BASE_PATH."/app/Utils/Sanitizer.php";
require_once BASE_PATH."/app/Utils/Validator.php";
require_once BASE_PATH."/app/Utils/CsrfToken.php";
require_once BASE_PATH."/app/Utils/DigitalSignature.php";
require_once BASE_PATH."/app/Utils/HmacCrypto.php";
require_once BASE_PATH."/app/Utils/RsaCrypto.php";
require_once BASE_PATH."/app/Utils/ImageHelper.php";
require_once BASE_PATH."/app/Utils/objImagenes.php";
require_once BASE_PATH."/app/Utils/EmailJsService.php";
require_once BASE_PATH."/app/Models/BaseModel.php";
require_once BASE_PATH."/app/Models/LogModel.php";
require_once BASE_PATH."/app/Models/UserModel.php";
require_once BASE_PATH."/app/Models/RolModel.php";
require_once BASE_PATH."/app/Models/AutoModel.php";
require_once BASE_PATH."/app/Models/SeccionModel.php";
require_once BASE_PATH."/app/Models/ParteModel.php";
require_once BASE_PATH."/app/Models/VentaModel.php";
require_once BASE_PATH."/app/Models/ComentarioModel.php";
require_once BASE_PATH."/app/Models/PedidoModel.php";
require_once BASE_PATH."/app/Controllers/BaseController.php";
require_once BASE_PATH."/app/Controllers/AuthController.php";
require_once BASE_PATH."/app/Controllers/DashboardController.php";
require_once BASE_PATH."/app/Controllers/UserController.php";
require_once BASE_PATH."/app/Controllers/RolController.php";
require_once BASE_PATH."/app/Controllers/AutoController.php";
require_once BASE_PATH."/app/Controllers/SeccionController.php";
require_once BASE_PATH."/app/Controllers/ParteController.php";
require_once BASE_PATH."/app/Controllers/VentaController.php";
require_once BASE_PATH."/app/Controllers/ComentarioController.php";
require_once BASE_PATH."/app/Controllers/PedidoController.php";
require_once BASE_PATH."/app/Controllers/PedidoAdminController.php";
require_once BASE_PATH."/app/Controllers/ReporteController.php";
require_once BASE_PATH."/app/Controllers/FacturaController.php";
require_once BASE_PATH."/app/Controllers/HerramientasController.php";
require_once BASE_PATH."/app/Controllers/PublicController.php";

if (session_status() === PHP_SESSION_NONE) session_start();
