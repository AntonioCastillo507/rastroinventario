<?php $title="Rastro del Istmo — Partes y Repuestos en Panamá"; include BASE_PATH."/app/Views/layouts/public_header.php"; ?>

<!-- HERO  ── hero-bg.jpg en assets/img/ (1920×800 px) -->
<section id="inicio" class="hero-section" style="background-image:url('<?=APP_URL?>/assets/img/hero-bg.jpg')">
  <div class="hero-overlay"></div>
  <div class="hero-content container-xl text-center">
    <p class="text-warning small fw-bold mb-2" style="letter-spacing:.15em"><i class="bi bi-tools me-1"></i>RASTRO DE AUTOS — PANAMÁ</p>
    <h1 class="hero-title mb-3">Tu pieza al<br><span>mejor precio</span></h1>
    <p class="mb-4 text-secondary mx-auto" style="max-width:500px;font-size:1.05rem">Repuestos originales para las principales marcas del mercado panameño.</p>
    <form class="d-flex justify-content-center mb-4" method="GET" action="<?=APP_URL?>/">
      <input type="hidden" name="ver" value="catalogo">
      <div class="input-group input-group-lg" style="max-width:540px">
        <input type="text" name="q" class="form-control dark-input" placeholder="Buscar: puerta, motor, vidrio, Toyota...">
        <button class="btn btn-gold px-4" type="submit"><i class="bi bi-search me-1"></i>Buscar</button>
      </div>
    </form>
    <div class="d-flex justify-content-center gap-3 flex-wrap">
      <a href="<?=APP_URL?>/?ver=catalogo" class="btn btn-outline-warning px-4"><i class="bi bi-grid me-2"></i>Ver todo el catálogo</a>
      <a href="#secciones" class="btn btn-outline-secondary px-4"><i class="bi bi-diagram-3 me-2"></i>Explorar secciones</a>
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
      <h2 class="fw-bold text-white">Explorar por <span class="text-warning">Sección</span></h2>
      <p class="text-secondary">Inventario organizado por área del rastro</p>
    </div>
    <div class="row g-3">
      <?php $sicones=["Sección A"=>"bi-door-open","Sección B"=>"bi-gear-wide-connected","Sección C"=>"bi-eyeglasses","Sección D"=>"bi-lightning-charge","default"=>"bi-archive"]; ?>
      <?php foreach($secciones as $s): $ic=$sicones[$s["nombre"]]??$sicones["default"]; ?>
      <div class="col-6 col-md-3">
        <a href="<?=APP_URL?>/?ver=catalogo&seccion=<?=$s["id"]?>" class="sec-card h-100">
          <div class="sec-icon"><i class="bi <?=$ic?>"></i></div>
          <div class="sec-name"><?=htmlspecialchars($s["nombre"])?></div>
          <div class="text-secondary small"><?=htmlspecialchars($s["descripcion"]??"")?></div>
          <div class="sec-count"><i class="bi bi-box-seam me-1"></i><?=$s["total"]?> piezas</div>
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
        <h2 class="fw-bold text-white mb-0">Piezas <span class="text-warning">Destacadas</span></h2>
        <p class="text-secondary small mb-0">Las piezas con mayor disponibilidad en stock</p>
      </div>
      <a href="<?=APP_URL?>/?ver=catalogo" class="btn btn-gold px-4 fw-bold">
        <i class="bi bi-grid me-2"></i>Ver todo el catálogo
      </a>
    </div>
    <?php if(empty($partesDestacadas)): ?>
    <p class="text-secondary text-center py-4">No hay piezas disponibles aún.</p>
    <?php else: ?>
    <div class="partes-grid">
      <?php foreach($partesDestacadas as $p): ?>
      <div class="pub-card">
        <a href="<?=APP_URL?>/?action=detalle&id=<?=$p["id"]?>" class="pub-card-img-wrap">
          <?php if($p["thumbnail"]): ?>
          <img src="<?=APP_URL?>/uploads/thumbnails/<?=htmlspecialchars($p["thumbnail"])?>" class="pub-card-img" alt="<?=htmlspecialchars($p["nombre"])?>">
          <?php endif; ?>
          <span class="pub-card-badge <?=$p["cantidad"]>5?"badge-stock-ok":($p["cantidad"]>0?"badge-stock-low":"badge-stock-out")?>">
            <?=$p["cantidad"]>0?$p["cantidad"]." en stock":"Agotado"?>
          </span>
        </a>
        <div class="pub-card-body">
          <h6 class="pub-card-title"><?=htmlspecialchars($p["nombre"])?></h6>
          <?php if($p["marca"]??""): ?><p class="pub-card-meta"><i class="bi bi-car-front me-1"></i><?=htmlspecialchars($p["marca"]." ".$p["modelo"])?></p><?php endif; ?>
          <?php if($p["seccion"]??""): ?><p class="pub-card-meta"><i class="bi bi-archive me-1"></i><?=htmlspecialchars($p["seccion"])?></p><?php endif; ?>
          <div class="pub-card-footer">
            <span class="pub-price">$<?=number_format($p["precio"],2)?></span>
            <a href="<?=APP_URL?>/?action=detalle&id=<?=$p["id"]?>" class="btn btn-gold btn-sm">Ver</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-4">
      <a href="<?=APP_URL?>/?ver=catalogo" class="btn btn-outline-warning px-5 py-2">
        <i class="bi bi-grid me-2"></i>Ver las <?=$stats["partes"]?> piezas disponibles
      </a>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- QUIÉNES SOMOS -->
<section id="nosotros" class="about-section py-5">
  <div class="container-xl">
    <div class="row align-items-center g-5">
      <div class="col-md-5">
        <img src="<?=APP_URL?>/assets/img/carro1.jpg" class="img-fluid rounded-3" alt="Rastro del Istmo" onerror="this.style.display='none'" style="border:1px solid var(--border);width:100%">
      </div>
      <div class="col-md-7">
        <p class="text-warning small fw-bold mb-2" style="letter-spacing:.1em"><i class="bi bi-gear-wide-connected me-1"></i>QUIÉNES SOMOS</p>
        <h2 class="fw-bold text-white mb-3">El rastro de <span class="text-warning">confianza</span> en Panamá</h2>
        <p class="text-secondary mb-3">Somos un rastro de autos especializado en la comercialización de partes y repuestos de calidad para vehículos de las principales marcas del mercado panameño.</p>
        <p class="text-secondary mb-4">Nuestro inventario se actualiza constantemente, garantizando que encuentres la pieza que necesitas al mejor precio.</p>
        <div class="row g-3">
          <div class="col-6"><div class="d-flex align-items-center gap-2"><i class="bi bi-shield-check text-success fs-4"></i><div><div class="text-white small fw-semibold">Calidad garantizada</div><div class="text-secondary" style="font-size:.75rem">Piezas revisadas</div></div></div></div>
          <div class="col-6"><div class="d-flex align-items-center gap-2"><i class="bi bi-truck text-warning fs-4"></i><div><div class="text-white small fw-semibold">Entrega segura</div><div class="text-secondary" style="font-size:.75rem">A todo el país</div></div></div></div>
          <div class="col-6"><div class="d-flex align-items-center gap-2"><i class="bi bi-headset text-info fs-4"></i><div><div class="text-white small fw-semibold">Soporte directo</div><div class="text-secondary" style="font-size:.75rem">Lun–Sáb disponible</div></div></div></div>
          <div class="col-6"><div class="d-flex align-items-center gap-2"><i class="bi bi-cash-coin text-success fs-4"></i><div><div class="text-white small fw-semibold">Mejores precios</div><div class="text-secondary" style="font-size:.75rem">Transparentes y justos</div></div></div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include BASE_PATH."/app/Views/layouts/public_footer.php"; ?>
