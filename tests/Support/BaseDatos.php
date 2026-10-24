<?php
namespace Tests\Support;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base de las pruebas de integración: cada prueba corre dentro de una transacción
 * que se deshace al terminar, así no queda nada en rastroinventario_test.
 */
abstract class BaseDatos extends TestCase {
    protected PDO $db;

    protected function setUp(): void {
        parent::setUp();
        $this->db = \Database::get();
        self::verificarBase($this->db);
        $this->db->beginTransaction();
    }

    protected function tearDown(): void {
        if ($this->db->inTransaction()) $this->db->rollBack();
        parent::tearDown();
    }

    /** Comprobación extra en cada prueba: jamás se trabaja sobre otra base */
    public static function verificarBase(PDO $db): void {
        $activa = $db->query("SELECT DATABASE()")->fetchColumn();
        if ($activa !== "rastroinventario_test") {
            fwrite(STDERR, "\n[PRUEBAS ABORTADAS] La conexión no es la de rastroinventario_test.\n");
            exit(2);
        }
    }
}
