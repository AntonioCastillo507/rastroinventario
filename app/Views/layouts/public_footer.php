</main>
<footer class="public-footer pie-sitio">
  <div class="container-xl">
    <div class="row pie-fila">
      <div class="col-12 col-md-6 col-lg-3">
        <div class="marca marca-pie mb-2"><?php $marcaLema=false; include __DIR__."/marca_bloque.php"; ?></div>
        <p class="pie-texto mb-0"><?=Marca::h("descripcion")?></p>
      </div>
      <div class="col-6 col-md-3 col-lg-2">
        <h2 class="pie-titulo">Comprar</h2>
        <ul class="pie-lista">
          <li><a href="<?=APP_URL?>/?ver=catalogo">Catálogo</a></li>
          <li><a href="<?=APP_URL?>/?action=carrito">Carrito</a></li>
          <li><a href="<?=empty($_SESSION["user"])?APP_URL."/login.php":APP_URL."/?action=mi_perfil"?>">Mi cuenta</a></li>
        </ul>
      </div>
      <div class="col-6 col-md-3 col-lg-2">
        <h2 class="pie-titulo">Empresa</h2>
        <ul class="pie-lista">
          <li><a href="<?=APP_URL?>/?action=nosotros">Quiénes somos</a></li>
          <li><a href="<?=APP_URL?>/?action=contacto">Contacto</a></li>
        </ul>
      </div>
      <div class="col-12 col-lg-5">
        <h2 class="pie-titulo">Contacto y horario</h2>
        <ul class="pie-lista pie-contacto">
          <?php if(Marca::get("direccion")!==""): ?><li><span class="pie-icono" aria-hidden="true"><i aria-hidden="true" class="bi bi-geo-alt"></i></span><span><?=Marca::h("direccion")?></span></li><?php endif; ?>
          <?php if(Marca::get("telefono")!==""): ?><li><span class="pie-icono" aria-hidden="true"><i aria-hidden="true" class="bi bi-telephone"></i></span><a href="tel:<?=htmlspecialchars(preg_replace('/[^\d+]/','',(string)Marca::get("telefono")))?>"><?=Marca::h("telefono")?></a></li><?php endif; ?>
          <?php if(Marca::get("correo")!==""): ?><li><span class="pie-icono" aria-hidden="true"><i aria-hidden="true" class="bi bi-envelope"></i></span><a href="mailto:<?=Marca::h("correo")?>"><?=Marca::h("correo")?></a></li><?php endif; ?>
          <?php $horario=Marca::get("horario",[]); if($horario): ?>
          <li><span class="pie-icono" aria-hidden="true"><i aria-hidden="true" class="bi bi-clock"></i></span><span class="pie-horario"><span class="visually-hidden">Horario: </span><?php foreach($horario as $h): ?><span class="pie-horario-fila"><?=htmlspecialchars($h["dia"]??"")?>: <?=htmlspecialchars($h["horas"]??"")?></span><?php endforeach; ?></span></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
  <div class="pie-barra">
    <div class="container-xl pie-barra-fila">
      <p class="pie-copy mb-0"><?=htmlspecialchars(Marca::pie())?></p>
      <?php $redes=Marca::redes(); if($redes): ?>
      <ul class="pie-redes" aria-label="Redes y mensajería">
        <?php foreach($redes as $r): ?>
        <li><a class="pie-red" href="<?=htmlspecialchars($r["url"])?>" target="_blank" rel="noopener noreferrer"><i aria-hidden="true" class="bi bi-<?=htmlspecialchars($r["icono"])?>"></i><span><?=htmlspecialchars($r["nombre"])?></span><span class="visually-hidden"> (se abre en otra pestaña)</span></a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</footer>
<div id="avisos-carrito" class="avisos-carrito" role="status" aria-live="polite" aria-atomic="true"></div>
<script src="<?=APP_URL?>/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?=Assets::url("js/main.js")?>"></script>
<script src="<?=Assets::url("js/carrito.js")?>"></script>
<script src="<?=Assets::url("js/envio.js")?>"></script>
</body></html>
