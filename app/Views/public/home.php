<?php
$title=Marca::get("portada.titulo_pagina"); $navActivo="inicio"; include BASE_PATH."/app/Views/layouts/public_header.php";
$confianza=Marca::confianza();
// Fotos del hero: solo si el archivo está en disco (la plantilla no las trae y usa el hero tipográfico)
$fotoEscritorio=Marca::urlSiExiste("portada.foto"); $fotoMovil=Marca::urlSiExiste("portada.foto_movil");
$conFoto=$fotoEscritorio!==""||$fotoMovil!=="";
if($fotoEscritorio==="") $fotoEscritorio=$fotoMovil;
if($fotoMovil==="") $fotoMovil=$fotoEscritorio;
$tarjetaVolver = $_SERVER["REQUEST_URI"] ?? "";
// Carrusel del hero: solo piezas cuya foto está en disco. Una sola pieza se muestra únicamente si es destacada (tarjeta fija, sin controles)
$heroDiapos=[];
foreach(($heroPartes??[]) as $hp){
  $f640=ImageHelper::fuente($hp["imagen_grande"]??null,$hp["thumbnail"]??null,"thumbnails");
  if($f640==="") continue;
  $f1200=ImageHelper::existe(ImageHelper::derivada($hp["imagen_grande"]??null,"medium"),"medium")?"medium/".ImageHelper::derivada($hp["imagen_grande"],"medium"):"";
  $hp["_src"]=$f640; $hp["_src1200"]=$f1200; $heroDiapos[]=$hp;
}
$variasDiapos=count($heroDiapos)>=2;
$conCarrusel=$variasDiapos||(count($heroDiapos)===1&&!empty($heroDiapos[0]["destacado"]));
?>

<?php /* HERO: título, subtítulo y buscador en una columna. Con foto (portada.foto en marca.php) lleva la capa por modo; sin ella es tipográfico */ ?>
<section id="inicio" class="portada-hero<?=$conFoto?" portada-hero--foto":""?>" aria-labelledby="hero-titulo">
  <?php if($conFoto): ?>
  <picture class="hero-fondo" aria-hidden="true"><source media="(min-width: 768px)" srcset="<?=htmlspecialchars($fotoEscritorio)?>"><img src="<?=htmlspecialchars($fotoMovil)?>" alt="" decoding="async" fetchpriority="high"></picture>
  <div class="hero-capa" aria-hidden="true"></div>
  <?php endif; ?>
  <div class="container-xl hero-texto<?=$conCarrusel?" hero-con-carrusel":""?>">
    <div class="hero-columna">
    <h1 id="hero-titulo" class="hero-titulo"><?=Marca::h("portada.titulo")?> <span class="hero-resalte"><?=Marca::h("portada.titulo_resalte")?></span></h1>
    <p class="hero-sub"><?=Marca::h("portada.subtitulo")?></p>
    <form class="hero-buscar" method="GET" action="<?=APP_URL?>/" role="search">
      <input type="hidden" name="ver" value="catalogo">
      <label class="visually-hidden" for="hero-q">Buscar piezas por nombre o por marca</label>
      <input id="hero-q" type="search" name="q" class="form-control dark-input" placeholder="<?=Marca::h("portada.buscador")?>" autocomplete="off">
      <button class="btn btn-gold" type="submit">Buscar</button>
    </form>
    </div>
    <?php if($conCarrusel): ?>
    <?php /* Carrusel de piezas (assets/js/hero_carrusel.js): sin JavaScript queda la primera pieza. Se oculta por debajo de 992 px */ ?>
    <div class="hero-carrusel" data-hero-carrusel role="group" aria-roledescription="carrusel" aria-label="<?=Marca::h("portada.hero_carrusel")?>">
      <div class="hero-marco">
        <div class="hero-diapos" data-hero-diapos>
          <?php foreach($heroDiapos as $i=>$hp):
            $compat=trim(($hp["marca"]??"")." ".($hp["modelo"]??""));
            $srcset=APP_URL."/uploads/".$hp["_src"]." 640w".($hp["_src1200"]!==""?", ".APP_URL."/uploads/".$hp["_src1200"]." 1200w":"");
          ?>
          <a class="hero-diapo" data-hero-diapo href="<?=APP_URL?>/?action=detalle&amp;id=<?=(int)$hp["id"]?>">
            <span class="hero-foto"><img src="<?=APP_URL?>/uploads/<?=htmlspecialchars($hp["_src"])?>" srcset="<?=htmlspecialchars($srcset)?>" sizes="(min-width: 1200px) 444px, 400px" alt="" width="640" height="320" decoding="async"<?=$i>0?' loading="lazy"':''?>></span>
            <?php if($variasDiapos): ?><span class="hero-progreso" aria-hidden="true"><span class="hero-progreso-relleno"></span></span><?php endif; ?>
            <span class="hero-leyenda">
              <span class="hero-datos">
                <span class="hero-nombre"><?=htmlspecialchars($hp["nombre"])?></span>
                <?php if($compat!==""): ?><span class="hero-compat"><?=htmlspecialchars($compat)?></span><?php endif; ?>
                <span class="hero-precio num">$<?=number_format((float)$hp["precio"],2)?></span>
              </span>
              <span class="hero-ver"><?=Marca::h("portada.hero_ver")?> →</span>
            </span>
          </a>
          <?php endforeach; ?>
        </div>
        <?php if($variasDiapos): ?>
        <div class="hero-flechas">
        <button type="button" class="hero-flecha hero-flecha-ant" data-hero-anterior hidden aria-label="<?=Marca::h("portada.hero_anterior")?>"><i aria-hidden="true" class="bi bi-chevron-left"></i></button>
        <button type="button" class="hero-flecha hero-flecha-sig" data-hero-siguiente hidden aria-label="<?=Marca::h("portada.hero_siguiente")?>"><i aria-hidden="true" class="bi bi-chevron-right"></i></button>
        </div>
        <?php endif; ?>
      </div>
      <?php if($variasDiapos): ?>
      <div class="hero-puntos" data-hero-puntos hidden>
        <?php $nTotal=count($heroDiapos); foreach($heroDiapos as $i=>$hp): ?>
        <button type="button" class="hero-punto" data-hero-punto aria-label="<?=htmlspecialchars(str_replace(["{n}","{total}"],[(string)($i+1),(string)$nTotal],(string)Marca::get("portada.hero_barra")))?>"><span class="hero-punto-marca"></span></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <script src="<?=Assets::url("js/hero_carrusel.js")?>"></script>
    <?php endif; ?>
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
        <span class="cat-icono" aria-hidden="true"><i aria-hidden="true" class="bi bi-<?=htmlspecialchars(Marca::iconoCategoria((int)$s["id"]))?>"></i></span>
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
<?php /* Carrusel de logos (assets/js/marcas_carrusel.js): sin JavaScript queda una fila con scroll. Cada logo lleva al catálogo de esa marca */ ?>
<section class="portada-seccion portada-marcas" aria-labelledby="t-marcas">
  <div class="container-xl">
    <div class="seccion-cabeza">
      <h2 id="t-marcas" class="seccion-titulo"><?=Marca::h("portada.marcas_titulo")?></h2>
      <div class="marcas-controles" data-marcas-controles hidden>
        <button type="button" class="marcas-nav" data-marcas-anterior aria-label="<?=Marca::h("portada.marcas_anterior")?>"><i aria-hidden="true" class="bi bi-chevron-left"></i></button>
        <button type="button" class="marcas-nav" data-marcas-siguiente aria-label="<?=Marca::h("portada.marcas_siguiente")?>"><i aria-hidden="true" class="bi bi-chevron-right"></i></button>
        </div>
      </div>
    <div class="marcas-carrusel" data-marcas-carrusel>
      <div class="marcas-ventana" data-marcas-ventana>
        <ul class="marcas-pista" data-marcas-pista>
          <?php foreach($marcasConteo as $m): $mlNombre=$m["nombre"]; $mlLogo=$m["logo"]; $mlClase="marca-casilla--tarjeta"; $mlNombreVisible=true; $n=(int)$m["total"]; ?>
          <li><a class="marca-enlace" href="<?=APP_URL?>/?ver=catalogo&amp;marca=<?=htmlspecialchars(rawurlencode($m["nombre"]))?>">
            <?php include BASE_PATH."/app/Views/layouts/marca_logo.php"; ?>
            <span class="visually-hidden"><?=htmlspecialchars($m["nombre"])?>, <?=$n?> <?=$n===1?"pieza":"piezas"?></span>
          </a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</section>
<script src="<?=Assets::url("js/marcas_carrusel.js")?>"></script>
<?php endif; ?>

<?php
$nosFoto=Marca::urlSiExiste("nosotros.imagen");
?>
<section class="portada-seccion portada-porque" aria-labelledby="t-porque">
  <div class="container-xl">
    <div class="porque-tarjeta<?=$nosFoto!==""?"":" porque-sin-foto"?>">
      <?php if($nosFoto!==""): ?><div class="porque-foto"><img src="<?=htmlspecialchars($nosFoto)?>" alt="" loading="lazy" decoding="async"></div><?php endif; ?>
      <div class="porque-texto">
        <span class="porque-insignia"><?=Marca::h("portada.porque_insignia")?></span>
        <h2 id="t-porque" class="porque-titulo"><?=Marca::h("portada.porque_titulo")?></h2>
        <p class="porque-sub"><?=Marca::h("portada.porque_subtitulo")?></p>
        <p class="porque-parrafo"><?=Marca::h("portada.porque_texto")?></p>
        <a class="btn btn-gold porque-boton" href="<?=APP_URL?>/?action=nosotros"><?=Marca::h("portada.porque_boton")?></a>
      </div>
    </div>
  </div>
</section>

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
