<?php
$title=Marca::get("portada.titulo_pagina"); $navActivo="inicio"; include BASE_PATH."/app/Views/layouts/public_header.php";
$confianza=Marca::confianza();
$tarjetaVolver = $_SERVER["REQUEST_URI"] ?? "";
?>

<?php /* HERO: título, subtítulo y buscador en una columna. Con foto (portada.foto en marca.php) lleva la capa por modo; sin ella es tipográfico */ ?>
<section id="inicio" class="portada-hero" aria-labelledby="hero-titulo">
  <div class="container-xl hero-texto">
    <h1 id="hero-titulo" class="hero-titulo"><?=Marca::h("portada.titulo")?> <span class="hero-resalte"><?=Marca::h("portada.titulo_resalte")?></span></h1>
    <p class="hero-sub"><?=Marca::h("portada.subtitulo")?></p>
    <form class="hero-buscar" method="GET" action="<?=APP_URL?>/" role="search">
      <input type="hidden" name="ver" value="catalogo">
      <label class="visually-hidden" for="hero-q">Buscar piezas por nombre o por marca</label>
      <input id="hero-q" type="search" name="q" class="form-control dark-input" placeholder="<?=Marca::h("portada.buscador")?>" autocomplete="off">
      <button class="btn btn-gold" type="submit">Buscar</button>
    </form>
  </div>
</section>

<?php if($confianza): ?>
<?php /* franja de portada.confianza (marca.php): con la lista vacía no se pinta nada */ ?>
<section class="portada-confianza" aria-label="Por qué comprar aquí">
  <div class="container-xl">
    <ul class="confianza-lista">
      <?php foreach($confianza as $c): ?>
      <li class="confianza-item"><span class="confianza-titulo"><?=htmlspecialchars($c["titulo"])?></span><?php if($c["texto"]!==""): ?><span class="confianza-texto"><?=htmlspecialchars($c["texto"])?></span><?php endif; ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<section class="portada-seccion" aria-labelledby="t-cats">
  <div class="container-xl">
    <h2 id="t-cats" class="seccion-titulo"><?=Marca::h("portada.categorias_titulo")?></h2>
    <ul class="cat-lista">
      <?php foreach($secciones as $s): ?>
      <li><a class="cat-baldosa" href="<?=APP_URL?>/?ver=catalogo&amp;seccion=<?=(int)$s["id"]?>">
        <span class="cat-nombre"><?=htmlspecialchars($s["nombre"])?></span>
        <?php if(trim((string)($s["descripcion"]??""))!==""): ?><span class="cat-desc"><?=htmlspecialchars($s["descripcion"])?></span><?php endif; ?>
        <span class="cat-cuenta num"><?=(int)$s["total"]?> piezas</span>
      </a></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<section class="portada-seccion portada-nuevas" aria-labelledby="t-nuevas">
  <div class="container-xl">
    <div class="seccion-cabeza">
      <h2 id="t-nuevas" class="seccion-titulo"><?=Marca::h("portada.recientes_titulo")?></h2>
      <a class="seccion-ver" href="<?=APP_URL?>/?ver=catalogo"><?=htmlspecialchars(str_replace("{total}",(string)(int)$stats["partes"],(string)Marca::get("portada.recientes_ver")))?> →</a>
    </div>
    <?php if(empty($recientes)): ?>
    <p class="text-secondary text-center py-4">No hay piezas disponibles aún.</p>
    <?php else: ?>
    <div class="rejilla-piezas">
      <?php foreach($recientes as $p): $tarjetaParte=$p; include BASE_PATH."/app/Views/layouts/tarjeta_pieza.php"; endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php if(!empty($marcasConteo)): ?>
<section class="portada-seccion" aria-labelledby="t-marcas">
  <div class="container-xl">
    <h2 id="t-marcas" class="seccion-titulo"><?=Marca::h("portada.marcas_titulo")?></h2>
    <ul class="marca-lista">
      <?php foreach($marcasConteo as $m): ?>
      <li><a class="marca-chip" href="<?=APP_URL?>/?ver=catalogo&amp;marca=<?=htmlspecialchars(rawurlencode($m["marca"]))?>"><span><?=htmlspecialchars($m["marca"])?></span><span class="marca-chip-n num"><?=(int)$m["total"]?></span></a></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>

<section class="portada-seccion portada-cta" aria-labelledby="t-cta">
  <div class="container-xl">
    <div class="cta-banda">
      <div>
        <h2 id="t-cta" class="cta-titulo"><?=Marca::h("portada.cta_titulo")?></h2>
        <p class="cta-texto"><?=Marca::h("portada.cta_texto")?></p>
      </div>
      <a class="btn btn-gold" href="<?=APP_URL?>/?action=contacto"><?=Marca::h("portada.cta_boton")?></a>
    </div>
  </div>
</section>

<?php include BASE_PATH."/app/Views/layouts/public_footer.php"; ?>
