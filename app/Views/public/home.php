<?php $title=Marca::get("portada.titulo_pagina"); $navActivo="inicio"; include BASE_PATH."/app/Views/layouts/public_header.php"; ?>

<!-- HERO  ── hero-bg.jpg en assets/img/ (1920×800 px) -->
<section id="inicio" class="hero-section" style="background-image:url('<?=APP_URL?>/assets/img/hero-bg.jpg')">
  <div class="hero-overlay"></div>
  <div class="hero-content container-xl text-center">
    <p class="text-warning small fw-bold text-uppercase mb-2" style="letter-spacing:.15em"><?=Marca::h("portada.etiqueta")?></p>
    <h1 class="hero-title mb-3"><?=Marca::h("portada.titulo")?><br><span><?=Marca::h("portada.titulo_resalte")?></span></h1>
    <p class="mb-4 text-secondary mx-auto" style="max-width:500px;font-size:1.05rem"><?=Marca::h("portada.subtitulo")?></p>
    <form class="d-flex justify-content-center mb-4" method="GET" action="<?=APP_URL?>/" role="search">
      <input type="hidden" name="ver" value="catalogo">
      <div class="input-group input-group-lg" style="max-width:540px">
        <input type="search" name="q" class="form-control dark-input" placeholder="<?=Marca::h("portada.buscador")?>" aria-label="Buscar piezas">
        <button class="btn btn-gold px-4" type="submit"><i aria-hidden="true" class="bi bi-search me-1"></i>Buscar</button>
      </div>
    </form>
    <div class="d-flex justify-content-center gap-3 flex-wrap">
      <a href="<?=APP_URL?>/?ver=catalogo" class="btn btn-outline-warning px-4"><i aria-hidden="true" class="bi bi-grid me-2"></i>Ver todo el catálogo</a>
      <a href="#secciones" class="btn btn-outline-secondary px-4"><i aria-hidden="true" class="bi bi-diagram-3 me-2"></i>Explorar secciones</a>
    </div>
  </div>
</section>

<!-- STATS -->
<div class="stats-bar">
  <div class="container-xl">
    <div class="row g-0 text-center">
      <div class="col-4 py-3 border-end" style="border-color:var(--border)!important">
        <div class="stat-pill-num"><?=$stats["partes"]?>+</div>
        <div class="stat-pill-lbl">Piezas</div>
      </div>
      <div class="col-4 py-3 border-end" style="border-color:var(--border)!important">
        <div class="stat-pill-num"><?=$stats["marcas"]?></div>
        <div class="stat-pill-lbl">Marcas</div>
      </div>
      <div class="col-4 py-3">
        <div class="stat-pill-num"><?=$stats["secciones"]?></div>
        <div class="stat-pill-lbl">Secciones</div>
      </div>
    </div>
  </div>
</div>

<!-- SECCIONES -->
<section id="secciones" class="py-5">
  <div class="container-xl">
    <div class="text-center mb-4">
      <h2 class="fw-bold text-white"><?=Marca::h("portada.secciones_titulo")?> <span class="text-warning"><?=Marca::h("portada.secciones_resalte")?></span></h2>
      <p class="text-secondary"><?=Marca::h("portada.secciones_subtitulo")?></p>
    </div>
    <div class="row g-3">
      <?php $sicones=["Sección A"=>"bi-door-open","Sección B"=>"bi-gear-wide-connected","Sección C"=>"bi-eyeglasses","Sección D"=>"bi-lightning-charge","default"=>"bi-archive"]; ?>
      <?php foreach($secciones as $s): $ic=$sicones[$s["nombre"]]??$sicones["default"]; ?>
      <div class="col-6 col-md-3">
        <a href="<?=APP_URL?>/?ver=catalogo&seccion=<?=$s["id"]?>" class="sec-card h-100">
          <div class="sec-icon"><i aria-hidden="true" class="bi <?=$ic?>"></i></div>
          <div class="sec-name"><?=htmlspecialchars($s["nombre"])?></div>
          <div class="text-secondary small"><?=htmlspecialchars($s["descripcion"]??"")?></div>
          <div class="sec-count"><i aria-hidden="true" class="bi bi-box-seam me-1"></i><?=$s["total"]?> piezas</div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- PIEZAS DESTACADAS — mayor stock -->
<section style="background:var(--bg-1);border-top:1px solid var(--border);border-bottom:1px solid var(--border)" class="py-5">
  <div class="container-xl">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
      <div>
        <h2 class="fw-bold text-white mb-0"><?=Marca::h("portada.destacadas_titulo")?> <span class="text-warning"><?=Marca::h("portada.destacadas_resalte")?></span></h2>
        <p class="text-secondary small mb-0"><?=Marca::h("portada.destacadas_subtitulo")?></p>
      </div>
      <a href="<?=APP_URL?>/?ver=catalogo" class="btn btn-gold px-4 fw-bold">
        <i aria-hidden="true" class="bi bi-grid me-2"></i>Ver todo el catálogo
      </a>
    </div>
    <?php if(empty($partesDestacadas)): ?>
    <p class="text-secondary text-center py-4">No hay piezas disponibles aún.</p>
    <?php else: ?>
    <div class="partes-grid">
      <?php foreach($partesDestacadas as $p): ?>
      <div class="pub-card">
        <a href="<?=APP_URL?>/?action=detalle&id=<?=$p["id"]?>" class="pub-card-img-wrap">
          <?php if(ImageHelper::existe($p["thumbnail"],"thumbnails")): ?>
          <img src="<?=APP_URL?>/uploads/thumbnails/<?=htmlspecialchars($p["thumbnail"])?>" class="pub-card-img" alt="<?=htmlspecialchars($p["nombre"])?>">
          <?php endif; ?>
          <span class="pub-card-badge <?=$p["cantidad"]>5?"badge-stock-ok":($p["cantidad"]>0?"badge-stock-low":"badge-stock-out")?>">
            <?=$p["cantidad"]>0?$p["cantidad"]." en stock":"Agotado"?>
          </span>
        </a>
        <div class="pub-card-body">
          <h3 class="h6 pub-card-title"><?=htmlspecialchars($p["nombre"])?></h3>
          <?php if($p["marca"]??""): ?><p class="pub-card-meta"><i aria-hidden="true" class="bi bi-car-front me-1"></i><?=htmlspecialchars($p["marca"]." ".$p["modelo"])?></p><?php endif; ?>
          <?php if($p["seccion"]??""): ?><p class="pub-card-meta"><i aria-hidden="true" class="bi bi-archive me-1"></i><?=htmlspecialchars($p["seccion"])?></p><?php endif; ?>
          <div class="pub-card-footer">
            <span class="pub-price num">$<?=number_format($p["precio"],2)?></span>
            <a href="<?=APP_URL?>/?action=detalle&id=<?=$p["id"]?>" class="btn btn-gold btn-sm">Ver</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-4">
      <a href="<?=APP_URL?>/?ver=catalogo" class="btn btn-outline-warning px-5 py-2">
        <i aria-hidden="true" class="bi bi-grid me-2"></i>Ver las <?=$stats["partes"]?> piezas disponibles
      </a>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- QUIÉNES SOMOS -->
<section id="nosotros" class="about-section py-5">
  <div class="container-xl">
    <div class="row align-items-center g-4 gx-xl-5">
      <div class="col-md-5">
        <img src="<?=htmlspecialchars(Marca::url("nosotros.imagen"))?>" class="img-fluid rounded-3" alt="<?=Marca::h("nombre")?>" onerror="this.style.display='none'" style="border:1px solid var(--border);width:100%">
      </div>
      <div class="col-md-7">
        <h2 class="fw-bold text-white mb-3"><?=Marca::h("nosotros.titulo")?> <span class="text-warning"><?=Marca::h("nosotros.titulo_resalte")?></span></h2>
        <?php foreach(Marca::get("nosotros.parrafos",[]) as $i=>$parrafo): ?>
        <p class="text-secondary <?=$i===count(Marca::get("nosotros.parrafos",[]))-1?"mb-4":"mb-3"?>"><?=htmlspecialchars($parrafo)?></p>
        <?php endforeach; ?>
        <div class="row g-3">
          <?php foreach(Marca::get("nosotros.destacados",[]) as $d): ?>
          <div class="col-6"><div class="d-flex align-items-center gap-2"><i aria-hidden="true" class="bi bi-<?=htmlspecialchars($d["icono"]??"check-circle")?> text-warning fs-4"></i><div><div class="text-white small fw-semibold"><?=htmlspecialchars($d["titulo"]??"")?></div><div class="text-secondary" style="font-size:.75rem"><?=htmlspecialchars($d["texto"]??"")?></div></div></div></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include BASE_PATH."/app/Views/layouts/public_footer.php"; ?>
