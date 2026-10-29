<?php
/** Marcas de autos del catálogo (nombre y logo). Usa el permiso del módulo «autos»: la marca es parte del catálogo de autos. */
class MarcaAutoController extends BaseController {
    private MarcaAutoModel $mm;
    public function __construct() { $this->mm = new MarcaAutoModel(); }

    public function index(): void {
        $this->requirePermission("autos");
        $marcas = $this->mm->listar();
        $this->view("admin/marcas/index", compact("marcas"));
    }

    /** ¿Llegó un archivo en el campo del logo? (el campo vacío del formulario no cuenta) */
    private function hayLogo(): bool {
        return isset($_FILES["logo"]) && is_array($_FILES["logo"]) && ($_FILES["logo"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    public function crear(): void {
        $this->requirePermission("autos", "puede_crear");
        $error = ""; $marca = [];
        if ($this->isPost()) {
            CsrfToken::verify();
            $marca = ["nombre" => Sanitizer::post("nombre")];
            $logo = null;
            if ($this->hayLogo()) {
                $logo = LogoMarca::subir($_FILES["logo"]);
                if ($logo === null) $error = LogoMarca::$error;
            }
            if ($error === "") {
                try {
                    $id = $this->mm->crear($marca["nombre"]);
                    if ($logo !== null) $this->mm->guardarLogo($id, $logo);
                    $this->redirect(APP_URL . "/admin/?mod=marcas", "Marca agregada.");
                } catch (InvalidArgumentException $e) {
                    $error = $e->getMessage();
                    LogoMarca::borrar($logo);
                } catch (Throwable $e) {
                    LogoMarca::borrar($logo);
                    throw $e;
                }
            }
        }
        $this->view("admin/marcas/form", compact("error", "marca"));
    }

    public function editar(): void {
        $this->requirePermission("autos", "puede_editar");
        $id = Sanitizer::get("id", "int"); $marca = $this->mm->findById($id);
        if (!$marca) $this->redirect(APP_URL . "/admin/?mod=marcas", "No encontrada.", "danger");
        $error = "";
        if ($this->isPost()) {
            CsrfToken::verify();
            $nombre = Sanitizer::post("nombre");
            $logo = null;
            if ($this->hayLogo()) {
                $logo = LogoMarca::subir($_FILES["logo"]);
                if ($logo === null) $error = LogoMarca::$error;
            }
            if ($error === "") {
                try {
                    if (MarcaAutoModel::limpiarNombre($nombre) !== $marca["nombre"]) $this->mm->renombrar($id, $nombre);
                    if ($logo !== null) {
                        $this->mm->guardarLogo($id, $logo);
                        LogoMarca::borrar($marca["logo"]); // el logo anterior ya no se usa
                    }
                    $this->redirect(APP_URL . "/admin/?mod=marcas", "Marca actualizada.");
                } catch (InvalidArgumentException $e) {
                    $error = $e->getMessage();
                    LogoMarca::borrar($logo);
                } catch (Throwable $e) {
                    LogoMarca::borrar($logo);
                    throw $e;
                }
            }
            $marca = ["nombre" => $nombre] + $marca; // el formulario vuelve con lo escrito
        }
        $this->view("admin/marcas/form", compact("marca", "error", "id"));
    }

    public function quitarLogo(): void {
        $this->requirePermission("autos", "puede_editar");
        if (!$this->isPost()) $this->redirect(APP_URL . "/admin/?mod=marcas"); // cambia datos: solo POST
        CsrfToken::verify();
        $marca = $this->mm->findById(Sanitizer::get("id", "int"));
        if (!$marca) $this->redirect(APP_URL . "/admin/?mod=marcas", "No encontrada.", "danger");
        $this->mm->guardarLogo((int)$marca["id"], null);
        LogoMarca::borrar($marca["logo"]);
        $this->redirect(APP_URL . "/admin/?mod=marcas", "Logo quitado. La marca usa su monograma.");
    }

    public function activar(): void {
        $this->requirePermission("autos", "puede_editar");
        if (!$this->isPost()) $this->redirect(APP_URL . "/admin/?mod=marcas");
        CsrfToken::verify();
        $marca = $this->mm->findById(Sanitizer::get("id", "int"));
        if (!$marca) $this->redirect(APP_URL . "/admin/?mod=marcas", "No encontrada.", "danger");
        $activa = Sanitizer::post("activo", "int") === 1;
        $this->mm->activar((int)$marca["id"], $activa);
        $this->redirect(APP_URL . "/admin/?mod=marcas", $activa ? "Marca activada." : "Marca desactivada. Ya no aparece en la portada.");
    }
}
