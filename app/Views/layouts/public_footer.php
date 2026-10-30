</main>
<?php
// Categorías del pie: las mismas secciones activas de «Compra por categoría»; si la base no responde, el pie sale sin ellas
try { $pieSecciones = (new SeccionModel())->findActive(); } catch (Throwable $e) { $pieSecciones = []; }
?>
<footer class="public-footer pie-sitio">
  <div class="container-xl">
    <div class="pie-fila">
      <div class="pie-marca">
        <div class="marca marca-pie mb-3"><?php $marcaLema=false; include __DIR__."/marca_bloque.php"; ?></div>
        <p class="pie-texto pie-descripcion"><?=Marca::h("descripcion")?></p>
        <?php $redes=Marca::redes(); if($redes): ?>
        <ul class="pie-redes" aria-label="Redes y mensajería">
          <?php foreach($redes as $r): ?>
          <li><a class="pie-red" href="<?=htmlspecialchars($r["url"])?>" target="_blank" rel="noopener noreferrer" aria-label="<?=htmlspecialchars($r["nombre"])?> (se abre en otra pestaña)" title="<?=htmlspecialchars($r["nombre"])?>"><i aria-hidden="true" class="bi bi-<?=htmlspecialchars($r["icono"])?>"></i></a></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
      <div class="pie-col">
        <h2 class="pie-titulo">Comprar</h2>
        <ul class="pie-lista">
          <li><a href="<?=APP_URL?>/?ver=catalogo">Catálogo</a></li>
          <?php foreach($pieSecciones as $s): ?>
          <li><a href="<?=APP_URL?>/?ver=catalogo&amp;seccion=<?=(int)$s["id"]?>"><?=htmlspecialchars($s["nombre"])?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="pie-col">
        <h2 class="pie-titulo">Empresa</h2>
        <ul class="pie-lista">
          <li><a href="<?=APP_URL?>/?action=nosotros">Quiénes somos</a></li>
          <li><a href="<?=APP_URL?>/?action=contacto">Contacto</a></li>
          <li><a href="<?=empty($_SESSION["user"])?APP_URL."/login.php":APP_URL."/?action=mi_perfil"?>">Mi cuenta</a></li>
          <li><a href="<?=APP_URL?>/?action=carrito">Carrito</a></li>
        </ul>
      </div>
      <div class="pie-col pie-col-contacto">
        <h2 class="pie-titulo">Contacto</h2>
        <ul class="pie-lista pie-contacto">
          <?php if(Marca::get("direccion")!==""): ?><li><span class="pie-icono" aria-hidden="true"><i aria-hidden="true" class="bi bi-geo-alt"></i></span><a class="pie-mapa" href="<?=htmlspecialchars(Marca::mapa())?>" target="_blank" rel="noopener noreferrer"><?=Marca::h("direccion")?><span class="visually-hidden"> (abre Google Maps en otra pestaña)</span></a></li><?php endif; ?>
          <?php if(Marca::get("telefono")!==""): ?><li><span class="pie-icono" aria-hidden="true"><i aria-hidden="true" class="bi bi-telephone"></i></span><a href="tel:<?=htmlspecialchars(preg_replace('/[^\d+]/','',(string)Marca::get("telefono")))?>"><?=Marca::h("telefono")?></a></li><?php endif; ?>
          <?php if(Marca::get("correo")!==""): ?><li><span class="pie-icono" aria-hidden="true"><i aria-hidden="true" class="bi bi-envelope"></i></span><a href="mailto:<?=Marca::h("correo")?>"><?=Marca::h("correo")?></a></li><?php endif; ?>
          <?php $horario=Marca::get("horario",[]); if($horario): ?>
          <li><span class="pie-icono" aria-hidden="true"><i aria-hidden="true" class="bi bi-clock"></i></span><span class="pie-horario"><span class="visually-hidden">Horario: </span><?php foreach($horario as $h): ?><span class="pie-horario-fila"><span class="pie-horario-dia"><?=htmlspecialchars($h["dia"]??"")?></span> <span class="pie-horario-horas"><?=htmlspecialchars($h["horas"]??"")?></span></span><?php endforeach; ?></span></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
  <div class="pie-barra">
    <div class="container-xl pie-barra-fila">
      <p class="pie-copy mb-0"><?=htmlspecialchars(Marca::pie())?></p>
      <a class="pie-arriba" href="#"><span>Volver arriba</span><i aria-hidden="true" class="bi bi-arrow-up"></i></a>
    </div>
  </div>
</footer>
<div id="avisos-carrito" class="avisos-carrito" role="status" aria-live="polite" aria-atomic="true"></div>
<script src="<?=APP_URL?>/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?=Assets::url("js/main.js")?>"></script>
<script src="<?=Assets::url("js/carrito.js")?>"></script>
<script src="<?=Assets::url("js/envio.js")?>"></script>
<?php if(!empty($cargarDireccionJs)): ?><script src="<?=Assets::url("js/direccion.js")?>"></script><?php endif; ?>
</body></html>
