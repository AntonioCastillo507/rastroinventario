<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\AnalizadorVistas;

/**
 * Guarda estática (hallazgo 17): los datos se guardan tal cual y se escapan al imprimir. Toda salida
 * de las vistas que lleve una variable tiene que pasar por htmlspecialchars, salvo las de esta lista,
 * que son números, valores controlados por el programa o textos que el encabezado ya escapa.
 * Si una vista nueva rompe esta prueba: escapa la salida, o añade aquí la clave con su motivo.
 */
final class VistasEscapadoTest extends TestCase {
    /** Claves de arreglo que solo contienen números, fechas o valores fijos del sistema */
    private const CLAVES = [
        "id", "cantidad", "anio", "total_items", "total", "subtotal", "precio", "precio_unitario", "precio_venta", // números
        "fecha", "creado_en",                                            // fechas del servidor
        "estado",                                                        // enum de pedidos: pendiente, procesando, entregado, cancelado
        "usuario_id",                                                    // número
        "type",                                                          // tipo del aviso: success, danger o warning, siempre literal en el código
        "max", "min", "marcas", "partes", "secciones",                   // rangos y listas que la vista recorre; sus textos se escapan al imprimirlos
    ];
    /**
     * Variables enteras: contadores, copias de un número o de un estado, valores de listas literales
     * de la propia vista (módulos y acciones de permisos), o el título de página (literal en el panel;
     * en la tienda lo escapa el encabezado).
     */
    private const VARIABLES = [
        "id", "title", "cartCount", "pedidosPendientes", "totalPartes", "totalPedidos", "totalRating",
        "totalStockBajo", "totalVentas", "sz", "i", "n", "ic",
        "c", "e", "m", "a", "ML",
        "pMin", "pMax", // precios del filtro; PublicController los convierte a float
    ];

    public static function permitida(string $variable, ?string $clave): bool {
        return in_array($variable, self::VARIABLES, true) || ($clave !== null && in_array($clave, self::CLAVES, true));
    }

    /** @return array<string,array{0:string}> */
    public static function vistas(): array {
        $out = [];
        $raiz = BASE_PATH . "/app/Views";
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->getExtension() === "php") $out[substr($f->getPathname(), strlen($raiz) + 1)] = [$f->getPathname()];
        }
        ksort($out);
        return $out;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("vistas")]
    public function testNingunaSalidaImprimeUnaVariableSinEscapar(string $ruta): void {
        $fallos = AnalizadorVistas::analizar((string)file_get_contents($ruta), [self::class, "permitida"]);
        $texto = array_map(fn($f) => basename($ruta) . ":" . $f["linea"] . " " . $f["variable"], $fallos);
        $this->assertSame([], $texto, "Salida sin htmlspecialchars: " . implode(", ", $texto));
    }

    public function testHayVistasQueRevisar(): void {
        $this->assertGreaterThan(20, count(self::vistas()));
    }

    // ── La propia guarda tiene que detectar lo que dice detectar ────────────────

    private static function fallos(string $php): array {
        return AnalizadorVistas::analizar($php, [self::class, "permitida"]);
    }

    public function testDetectaUnTextoDeUsuarioSinEscapar(): void {
        $this->assertCount(1, self::fallos('<p><?= $p["nombre"] ?></p>'));
        $this->assertCount(1, self::fallos('<p><?php echo $comentario["comentario"]; ?></p>'));
        $this->assertCount(1, self::fallos('<a href="?q=<?= $q ?>">'));
        $this->assertCount(1, self::fallos('<?= "Hola " . $u->nombre ?>'));
        $this->assertCount(1, self::fallos('<?= "Hola $p[nombre]" ?>'));
        $this->assertCount(1, self::fallos('<?= $x ? htmlspecialchars($a) : $p["descripcion"] ?>'));
    }

    public function testAceptaLoEscapadoYLoNumerico(): void {
        $this->assertSame([], self::fallos('<p><?= htmlspecialchars($p["nombre"]) ?></p>'));
        $this->assertSame([], self::fallos('<?= number_format($p["precio"] * $p["cantidad"], 2) ?>'));
        $this->assertSame([], self::fallos('<?= (int)$p["nombre"] ?>'));
        $this->assertSame([], self::fallos('<?= $p["id"] ?>'));
        $this->assertSame([], self::fallos('<?= $p["activo"] ? "Sí" : "No" ?>'));
        $this->assertSame([], self::fallos('<?= urlencode($q) ?>'));
        $this->assertSame([], self::fallos('<?= APP_URL ?>'));
    }
}
