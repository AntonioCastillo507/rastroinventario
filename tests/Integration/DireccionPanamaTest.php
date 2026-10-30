<?php
namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BaseDatos;

/** DireccionPanama::validar y GeoPanamaModel::resolver: la combinación existe, los textos no son basura y las direcciones reales pasan */
final class DireccionPanamaTest extends BaseDatos {
    private \GeoPanamaModel $geo;

    protected function setUp(): void {
        parent::setUp();
        $this->geo = new \GeoPanamaModel();
    }

    /** Códigos de una unidad por nombre: [provincia, distrito, corregimiento] */
    private function codigos(string $corr, string $dist, string $prov): array {
        $f = $this->db->prepare("SELECT p.codigo, d.codigo, c.codigo FROM corregimientos c JOIN distritos d ON d.codigo = c.distrito_codigo JOIN provincias p ON p.codigo = d.provincia_codigo WHERE c.nombre = ? AND d.nombre = ? AND p.nombre = ?");
        $f->execute([$corr, $dist, $prov]);
        $r = $f->fetch(\PDO::FETCH_NUM);
        $this->assertNotFalse($r, "no existe $corr / $dist / $prov");
        return $r;
    }

    private function entrada(array $cambios = []): array {
        [$p, $d, $c] = $this->codigos("Bella Vista", "Panamá", "Panamá");
        return $cambios + ["provincia" => $p, "distrito" => $d, "corregimiento" => $c, "calle" => "Calle 50", "casa" => "Casa 5-B", "barriada" => "Bella Vista", "referencia" => ""];
    }

    private function validar(array $cambios = []): array {
        return \DireccionPanama::validar($this->entrada($cambios), $this->geo);
    }

    // ── La división ──────────────────────────────────────────────────

    public function testUnaDireccionCompletaPasaYGuardaLosNombresDeLaBase(): void {
        $r = $this->validar(["referencia" => "Frente al supermercado"]);
        $this->assertTrue($r["ok"], json_encode($r["errores"]));
        $this->assertSame("Panamá", $r["datos"]["provincia_nombre"]);
        $this->assertSame("Panamá", $r["datos"]["distrito_nombre"]);
        $this->assertSame("Bella Vista", $r["datos"]["corregimiento_nombre"]);
        $this->assertSame("Calle 50, Casa 5-B, Bella Vista, Bella Vista, Panamá, Panamá. Ref.: Frente al supermercado", $r["datos"]["texto"]);
    }

    public function testLosNombresNuncaSalenDelFormulario(): void {
        $r = $this->validar(["provincia_nombre" => "Inventada", "distrito_nombre" => "Falso", "corregimiento_nombre" => "Nada"]);
        $this->assertTrue($r["ok"]);
        $this->assertSame("Panamá", $r["datos"]["provincia_nombre"]);
        $this->assertSame("Bella Vista", $r["datos"]["corregimiento_nombre"]);
    }

    public function testDistritoDeOtraProvinciaSeRechaza(): void {
        [, $dColon] = $this->codigos("Cristóbal", "Colón", "Colón");
        $r = $this->validar(["distrito" => $dColon]);
        $this->assertFalse($r["ok"]);
        $this->assertArrayHasKey("corregimiento", $r["errores"]);
        $this->assertStringContainsString("combinación", $r["errores"]["corregimiento"]);
    }

    public function testCorregimientoDeOtroDistritoSeRechaza(): void {
        [, , $cOtro] = $this->codigos("Cristóbal", "Colón", "Colón");
        $r = $this->validar(["corregimiento" => $cOtro]);
        $this->assertFalse($r["ok"]);
        $this->assertArrayHasKey("corregimiento", $r["errores"]);
    }

    public function testProvinciaDeOtraAunqueDistritoYCorregimientoExistan(): void {
        $this->assertFalse($this->validar(["provincia" => "01"])["ok"]);
    }

    public function testCodigosInexistentesOMalFormadosSeRechazan(): void {
        foreach ([["provincia" => "99"], ["distrito" => "9999"], ["corregimiento" => "999999"], ["provincia" => "8"], ["distrito" => "080"], ["corregimiento" => "08ab01"],
                  ["provincia" => "08' OR '1'='1"], ["distrito" => "0808 "], ["corregimiento" => "0808 01"]] as $cambio) {
            $r = $this->validar($cambio);
            $k = array_key_first($cambio);
            // Un espacio al final se recorta: ese caso sí es válido
            if ($cambio === ["distrito" => "0808 "]) { $this->assertTrue($r["ok"]); continue; }
            $this->assertFalse($r["ok"], "debía rechazar " . json_encode($cambio));
        }
    }

    public function testLosTresSonObligatoriosConSuMensaje(): void {
        $r = $this->validar(["provincia" => ""]);
        $this->assertSame("Elige la provincia o comarca.", $r["errores"]["provincia"]);
        $r = $this->validar(["distrito" => ""]);
        $this->assertSame("Elige el distrito.", $r["errores"]["distrito"]);
        $r = $this->validar(["corregimiento" => ""]);
        $this->assertSame("Elige el corregimiento.", $r["errores"]["corregimiento"]);
    }

    public function testCodigosQueLlegaCoNArreglosNoRompenNada(): void {
        $r = \DireccionPanama::validar(["provincia" => ["08"], "distrito" => ["0808"], "corregimiento" => ["080801"], "calle" => ["Calle 50"], "casa" => ["1"], "barriada" => ["x"], "referencia" => ["y"]], $this->geo);
        $this->assertFalse($r["ok"]);
        $this->assertArrayHasKey("provincia", $r["errores"]);
        $this->assertArrayHasKey("calle", $r["errores"]);
    }

    // ── Guna Yala y Naso Tjër Di ─────────────────────────────────────

    public function testGunaYalaNoPideNiMuestraDistrito(): void {
        $this->assertSame([], $this->geo->distritos("10"), "el cliente no ve el distrito ficticio");
        $this->assertSame("1001", $this->geo->distritoImplicito("10"), "el formulario pide sus corregimientos con ese código interno");
        $this->assertNull($this->geo->distritoImplicito("08"));
        $this->assertSame(["Ailigandí", "Narganá", "Puerto Obaldía", "Tubualá"], array_column($this->geo->corregimientos("1001"), "nombre"));
        // Sin distrito en el formulario: el servidor lo completa solo
        $r = \DireccionPanama::validar(["provincia" => "10", "distrito" => "", "corregimiento" => "100101", "calle" => "Calle Principal", "casa" => "Casa 3", "barriada" => "Narganá", "referencia" => ""], $this->geo);
        $this->assertTrue($r["ok"], json_encode($r["errores"]));
        $this->assertSame("1001", $r["datos"]["distrito"]);
        $this->assertSame("Comarca Guna Yala", $r["datos"]["provincia_nombre"]);
        $this->assertNull($r["datos"]["distrito_nombre"], "el distrito ficticio no va a la copia del pedido");
        $this->assertSame("Narganá", $r["datos"]["corregimiento_nombre"]);
        $this->assertSame("Calle Principal, Casa 3, Narganá, Narganá, Comarca Guna Yala", $r["datos"]["texto"]);
        $this->assertNull(\DireccionPanama::paraPedido($r["datos"])["entrega_distrito"]);
    }

    public function testGunaYalaAceptaTambienElCodigoDelDistritoSiLoManda(): void {
        $r = \DireccionPanama::validar(["provincia" => "10", "distrito" => "1001", "corregimiento" => "100102", "calle" => "Calle Principal", "casa" => "1", "barriada" => "Ailigandí", "referencia" => ""], $this->geo);
        $this->assertTrue($r["ok"]);
    }

    public function testNasoTjerDiMuestraSuDistritoYNoPideCorregimiento(): void {
        $this->assertContains("Comarca Naso Tjër Di", array_column($this->geo->distritos("01"), "nombre"), "el distrito sí se ve");
        $this->assertSame([], $this->geo->corregimientos("0105"), "su único corregimiento es implícito: no se muestra");
        $r = \DireccionPanama::validar(["provincia" => "01", "distrito" => "0105", "corregimiento" => "", "calle" => "Camino Principal", "casa" => "Casa 2", "barriada" => "Siey", "referencia" => ""], $this->geo);
        $this->assertTrue($r["ok"], json_encode($r["errores"]));
        $this->assertSame("010501", $r["datos"]["corregimiento"]);
        $this->assertSame("Comarca Naso Tjër Di", $r["datos"]["distrito_nombre"]);
        $this->assertNull($r["datos"]["corregimiento_nombre"], "el área única no va a la copia del pedido");
        $this->assertSame("Camino Principal, Casa 2, Siey, Comarca Naso Tjër Di, Bocas del Toro", $r["datos"]["texto"]);
    }

    public function testElDistritoNoSeCompletaSoloSiLaProvinciaTieneVarios(): void {
        $r = \DireccionPanama::validar(["provincia" => "08", "distrito" => "", "corregimiento" => "", "calle" => "Calle 50", "casa" => "1", "barriada" => "Bella Vista", "referencia" => ""], $this->geo);
        $this->assertFalse($r["ok"]);
        $this->assertSame("Elige el distrito.", $r["errores"]["distrito"]);
    }

    // ── Direcciones reales de Panamá: todas deben pasar ──────────────

    public static function callesReales(): array {
        return array_map(fn($c) => [$c], [
            "Calle 50", "Vía España", "Av. 4ta Sur", "Avenida Balboa", "Calle 13 Sur", "Vía Transístmica km 12", "Av. Ricardo J. Alfaro", "Calle Uruguay",
            "Vía Argentina", "Calle 5ta Este", "Av. Perú", "Calle Los Pinos", "Camino Real de Chilibre", "Carretera Panamericana km 45", "Calle A Norte",
            "Av. Cuba", "Calle 24 de Diciembre", "Vía Israel", "Calle Primera", "Av. Justo Arosemena", "Calle 9 Norte", "Vía Liberty Plaza", "Calle Ñandú",
        ]);
    }

    public static function barriadasReales(): array {
        return array_map(fn($c) => [$c], [
            "Urb. El Dorado", "Altos de Cerro Viento", "Barriada San Antonio", "Residencial Villa Lucre", "Brisas del Golf", "Villa de las Fuentes II", "Costa del Este",
            "PH Torres del Mar", "Betania", "Las Cumbres", "PH Oasis", "Urb. Los Ángeles", "Marbella", "El Cangrejo", "Monterrico", "Vista Hermosa", "Santa Isabel",
            "Rincón Largo", "Ph Tower Plaza", "Barrio Chino",
        ]);
    }

    public static function casasReales(): array {
        return array_map(fn($c) => [$c], ["Casa 5-B", "12", "Edificio Torre Azul, apto 4B", "Casa 22", "Apto 3-C", "Lote 15", "S/N", "PH Mirador, piso 3, apto 3B", "Casa E-33", "N° 45", "Casa u227", "Casa 5-B (esquina)"]);
    }

    #[DataProvider("callesReales")]
    public function testCallesRealesPasan(string $calle): void {
        $r = $this->validar(["calle" => $calle]);
        $this->assertTrue($r["ok"], "$calle → " . json_encode($r["errores"]));
    }

    #[DataProvider("barriadasReales")]
    public function testBarriadasRealesPasan(string $b): void {
        $r = $this->validar(["barriada" => $b]);
        $this->assertTrue($r["ok"], "$b → " . json_encode($r["errores"]));
    }

    #[DataProvider("casasReales")]
    public function testCasasRealesPasan(string $c): void {
        $r = $this->validar(["casa" => $c]);
        $this->assertTrue($r["ok"], "$c → " . json_encode($r["errores"]));
    }

    public function testReferenciasRealesPasan(): void {
        foreach (["Frente al supermercado Rey", "Al lado de la iglesia", "Casa azul con portón negro", "Detrás de la escuela", "Cerca de la parada del bus, 2da casa", "Portón blanco"] as $ref) {
            $r = $this->validar(["referencia" => $ref]);
            $this->assertTrue($r["ok"], "$ref → " . json_encode($r["errores"]));
            $this->assertSame($ref, $r["datos"]["referencia"]);
        }
    }

    public function testLaReferenciaEsOpcional(): void {
        $r = $this->validar(["referencia" => "   "]);
        $this->assertTrue($r["ok"]);
        $this->assertNull($r["datos"]["referencia"]);
        $this->assertStringNotContainsString("Ref.", $r["datos"]["texto"]);
    }

    public function testLosEspaciosSeLimpian(): void {
        $r = $this->validar(["calle" => "  Calle    50  ", "barriada" => "Bella\n\tVista"]);
        $this->assertTrue($r["ok"]);
        $this->assertSame("Calle 50", $r["datos"]["calle"]);
        $this->assertSame("Bella Vista", $r["datos"]["barriada"]);
    }

    // ── Texto basura ─────────────────────────────────────────────────

    public static function basuraCalle(): array {
        return array_map(fn($c) => [$c], [
            "!!!!", "....", "aaaaaa", "AAAAAAA", "asdfgh", "123456", "ñññññ", "xxx", "ninguno", "N/A", "kjhkjhkjh", "qwerty calle", "aabaabaa", "a", "ab",
            "-----", "###", "Calle <script>", "Calle; DROP TABLE", "Calle 50 \u{1F600}", "bbbbb", "zxcvbn", "No aplica", "prueba", "sdfghjkl", "uuuuu uuuuu",
            "Calle Los Pinossss", "Calle Pinooooo", "aabaabaac", "Calle Aaaaab",
        ]);
    }

    #[DataProvider("basuraCalle")]
    public function testCalleBasuraSeRechaza(string $calle): void {
        $r = $this->validar(["calle" => $calle]);
        $this->assertFalse($r["ok"], "debía rechazar «{$calle}»");
        $this->assertArrayHasKey("calle", $r["errores"]);
    }

    public function testBarriadaBasuraSeRechaza(): void {
        foreach (["!!!!", "aaaaaa", "asdfgh", "123456", "xxxxx", "ninguna", "nada", "ñññññ", "hjklñ", "ccccc bbbbb"] as $b) {
            $this->assertArrayHasKey("barriada", $this->validar(["barriada" => $b])["errores"], "debía rechazar «{$b}»");
        }
    }

    public function testCasaBasuraSeRechaza(): void {
        foreach (["", "!!", "...", "0", "000", "n/a", "ninguno", "<b>", "Casa\0 5"] as $c) {
            $this->assertArrayHasKey("casa", $this->validar(["casa" => $c])["errores"], "debía rechazar «{$c}»");
        }
    }

    public function testReferenciaBasuraSeRechazaPeroVaciaSePermite(): void {
        foreach (["!!!!", "aaaaaa", "asdfgh", "ninguna", "123", "<script>alert(1)</script>"] as $c) {
            $this->assertArrayHasKey("referencia", $this->validar(["referencia" => $c])["errores"], "debía rechazar «{$c}»");
        }
    }

    public function testLosLimitesDeLargo(): void {
        $s79 = rtrim(str_repeat("Calle Los Pinos ", 5));
        $this->assertArrayNotHasKey("calle", $this->validar(["calle" => $s79 . "s"])["errores"], "80 caracteres entran");
        $this->assertArrayHasKey("calle", $this->validar(["calle" => $s79 . "ss"])["errores"], "81 no");
        $this->assertArrayHasKey("calle", $this->validar(["calle" => "Calle " . str_repeat("Norte ", 14)])["errores"], "más de 80");
        $this->assertArrayHasKey("casa", $this->validar(["casa" => str_repeat("Casa 5 ", 10)])["errores"], "más de 60");
        $this->assertArrayHasKey("referencia", $this->validar(["referencia" => str_repeat("Frente al parque ", 8)])["errores"], "más de 120");
    }

    public function testUtf8InvalidoYControlesSeRechazan(): void {
        $this->assertArrayHasKey("calle", $this->validar(["calle" => "Calle \xC3\x28 50"])["errores"]);
        $this->assertArrayHasKey("calle", $this->validar(["calle" => "Calle\x00 50"])["errores"]);
        $this->assertArrayHasKey("calle", $this->validar(["calle" => "Calle\x07 50"])["errores"]);
    }

    public function testVariosErroresJuntosParaMostrarlosTodos(): void {
        $r = $this->validar(["calle" => "!!!", "barriada" => "", "provincia" => ""]);
        $this->assertEqualsCanonicalizing(["calle", "barriada", "provincia"], array_keys($r["errores"]));
        $this->assertNull($r["datos"]);
    }

    // ── Columnas ─────────────────────────────────────────────────────

    public function testLasColumnasParaPedidoYUsuarioExistenYGuardan(): void {
        $r = $this->validar(["referencia" => "Portón blanco"]);
        $ped = \DireccionPanama::paraPedido($r["datos"]);
        $this->db->prepare("INSERT INTO pedidos (cliente_nombre, cliente_telefono, cliente_email, " . implode(", ", array_keys($ped)) . ") VALUES ('A', '1', 'a@example.invalid', " . implode(", ", array_fill(0, count($ped), "?")) . ")")->execute(array_values($ped));
        $f = $this->db->query("SELECT * FROM pedidos ORDER BY id DESC LIMIT 1")->fetch();
        $this->assertSame("Bella Vista", $f["entrega_corregimiento"]);
        $this->assertSame("Portón blanco", $f["entrega_referencia"]);
        $this->assertSame($r["datos"]["texto"], $f["cliente_direccion"]);

        $uid = \Tests\Support\Datos::usuario($this->db);
        (new \UserModel())->update($uid, \DireccionPanama::paraUsuario($r["datos"]));
        $u = (new \UserModel())->findById($uid);
        $this->assertSame($r["datos"]["corregimiento"], $u["dir_corregimiento"]);
        $this->assertSame("Calle 50", $u["dir_calle"]);
    }
}
