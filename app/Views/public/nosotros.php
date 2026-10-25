<?php
$title="Quiénes somos"; $navActivo="nosotros";
include BASE_PATH."/app/Views/layouts/public_header.php";
$parrafos = Marca::get("nosotros.parrafos", []);
?>
<div class="container-xl py-5">
  <div class="row align-items-center g-4 gx-xl-5">
    <?php if(Marca::url("nosotros.imagen")!==""): ?>
    <div class="col-md-5">
      <img src="<?=htmlspecialchars(Marca::url("nosotros.imagen"))?>" class="img-fluid pagina-foto" alt="<?=Marca::h("nombre")?>" onerror="this.style.display='none'">
    </div>
    <?php endif; ?>
    <div class="col-md-7">
      <h1 class="h2 fw-bold text-white mb-3"><?=Marca::h("nosotros.titulo")?> <span class="text-warning"><?=Marca::h("nosotros.titulo_resalte")?></span></h1>
      <?php foreach($parrafos as $parrafo): ?>
      <p class="text-secondary mb-3"><?=htmlspecialchars($parrafo)?></p>
      <?php endforeach; ?>
    </div>
  </div>

  <?php $destacados = Marca::get("nosotros.destacados", []); if($destacados): ?>
  <div class="row g-3 mt-4">
    <?php foreach($destacados as $d): ?>
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="trust-badge h-100">
        <i aria-hidden="true" class="bi bi-<?=htmlspecialchars($d["icono"]??"check-circle")?>"></i>
        <div>
          <div class="trust-badge-titulo"><?=htmlspecialchars($d["titulo"]??"")?></div>
          <div class="trust-badge-texto"><?=htmlspecialchars($d["texto"]??"")?></div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="cta-band text-center mt-5">
    <h2 class="h3 fw-bold text-white mb-3"><?=Marca::h("portada.titulo")?> <?=Marca::h("portada.titulo_resalte")?></h2>
    <a href="<?=APP_URL?>/?ver=catalogo" class="btn btn-gold px-4 fw-bold">Ver todo el catálogo</a>
  </div>
</div>

<?php include BASE_PATH."/app/Views/layouts/public_footer.php"; ?>
