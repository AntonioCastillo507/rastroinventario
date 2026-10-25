<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tests\Support\Proceso;
use Tests\Support\ServidorTemporal;
use Tests\Support\ClienteHttp;

/**
 * "Salir" solo cierra la sesión por POST con token. Por GET (un enlace, una imagen ajena, la precarga del
 * navegador) no cierra nada. Con un token que no vale, la sesión también se cierra y se va al acceso.
 */
final class SalirPostTest extends TestCase {
    private function sesion(): array {
        return ["user" => ["id" => 1, "nombre" => "Alguien", "email" => "alguien@example.invalid"], "user_roles" => ["Cliente"], "last_activity" => time()];
    }

    private function salir(string $metodo, bool $csrf): array {
        return Proceso::accion(["clase" => "AuthController", "accion" => "doLogout", "metodo_http" => $metodo, "csrf" => $csrf, "sesion" => $this->sesion()]);
    }

    public function testPorGetNoCierraLaSesion(): void {
        $r = $this->salir("GET", true);
        $this->assertSame(1, $r["sesion"]["user"]["id"], "la sesión sigue abierta");
        if (Proceso::veCabeceras()) $this->assertStringEndsWith("/rastroinventario/", (string)Proceso::ubicacion($r), "vuelve al inicio");
    }

    public function testPorPostConTokenValidoCierraLaSesionYVaAlAcceso(): void {
        $r = $this->salir("POST", true);
        $this->assertArrayNotHasKey("user", $r["sesion"]);
        $this->assertSame([], $r["sesion"], "la sesión queda vacía");
        if (Proceso::veCabeceras()) $this->assertStringEndsWith("/login.php", (string)Proceso::ubicacion($r));
    }

    public function testPorPostConTokenQueNoValeNoDejaLaSesionAbiertaYVaAlAcceso(): void {
        $r = $this->salir("POST", false);
        $this->assertArrayNotHasKey("user", $r["sesion"]);
        if (Proceso::veCabeceras()) $this->assertStringEndsWith("/login.php", (string)Proceso::ubicacion($r));
    }

    public function testEnUnServidorRealGetNoCierraYPostConTokenSi(): void {
        $srv = new ServidorTemporal();
        $srv->iniciar();
        try {
            $c = new ClienteHttp($srv->base());
            $r = $c->get("/logout.php");
            $this->assertSame(302, $r["codigo"]);
            $this->assertStringEndsWith("/rastroinventario/", (string)$r["ubicacion"]);
            $this->assertSame(302, $c->post("/logout.php", ["csrf_token" => "falso"])["codigo"]);
            $r = $c->post("/logout.php", ["csrf_token" => ClienteHttp::token($c->get("/login.php")["cuerpo"])]);
            $this->assertSame(302, $r["codigo"]);
            $this->assertStringEndsWith("/login.php", (string)$r["ubicacion"]);
        } finally {
            $srv->detener();
        }
    }
}
