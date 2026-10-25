</main>
<footer class="public-footer pt-5 pb-4 mt-5">
  <div class="container-xl">
    <div class="row g-4">
      <div class="col-12 col-md-6 col-lg-3">
        <div class="marca marca-pie mb-3"><?php $marcaLema=false; include __DIR__."/marca_bloque.php"; ?></div>
        <p class="text-secondary small mb-0"><?=Marca::h("descripcion")?></p>
      </div>
      <div class="col-6 col-md-3 col-lg-2 offset-lg-1">
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
      <div class="col-12 col-md-6 col-lg-4">
        <h2 class="pie-titulo">Contacto y horario</h2>
        <ul class="pie-lista">
          <?php if(Marca::get("direccion")!==""): ?><li><?=Marca::h("direccion")?></li><?php endif; ?>
          <?php if(Marca::get("telefono")!==""): ?><li><a href="tel:<?=htmlspecialchars(preg_replace('/[^\d+]/','',(string)Marca::get("telefono")))?>"><?=Marca::h("telefono")?></a></li><?php endif; ?>
          <?php if(Marca::get("correo")!==""): ?><li><a href="mailto:<?=Marca::h("correo")?>"><?=Marca::h("correo")?></a></li><?php endif; ?>
          <?php foreach(Marca::get("horario",[]) as $h): ?>
          <li class="pie-horario"><?=htmlspecialchars($h["dia"]??"")?>: <?=htmlspecialchars($h["horas"]??"")?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <hr style="border-color:var(--border);margin-top:2rem">
    <div class="d-flex justify-content-between flex-wrap gap-2">
      <p class="text-secondary small mb-0"><?=htmlspecialchars(Marca::pie())?></p>
    </div>
  </div>
</footer>
<script src="<?=APP_URL?>/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?=Assets::url("js/main.js")?>"></script>
<script src="<?=Assets::url("js/envio.js")?>"></script>
</body></html>
