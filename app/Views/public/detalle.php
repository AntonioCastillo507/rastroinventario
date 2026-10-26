<?php
$navActivo="catalogo";
$title=$parte["nombre"]; // el encabezado ya escapa el título y añade la marca
include BASE_PATH."/app/Views/layouts/public_header.php";
if (!function_exists("starsHtml")) { // la vista puede incluirse más de una vez en un proceso (pruebas)
function starsHtml(float $n,int $sz=16): string {
    $out="<span role=\"img\" aria-label=\"Calificación: ".number_format($n,1)." de 5\">";
    for($i=1;$i<=5;$i++) $out.="<i aria-hidden=\"true\" class=\"bi bi-star".($i<=$n?"-fill":($i-0.5<=$n?"-half":" text-secondary")).($i<=$n?" filled":" empty")."\" style=\"font-size:{$sz}px\"></i>";
    return $out."</span>";
}
}
?>

<div class="container-xl py-4">
  <!-- Breadcrumb -->
  <nav class="mb-4" aria-label="Ruta de navegación"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?=APP_URL?>/" class="text-warning">Inicio</a></li>
    <li class="breadcrumb-item"><a href="<?=APP_URL?>/?ver=catalogo" class="text-warning">Catálogo</a></li>
    <li class="breadcrumb-item active text-secondary" aria-current="page"><?=htmlspecialchars($parte["nombre"])?></li>
  </ol></nav>

  <!-- FICHA PRODUCTO: galería (esqueleto reservado, sin zoom ni varias fotos todavía) y cuadro de compra -->
  <?php
    $cant = (int)$parte["cantidad"];
    [$stClase, $stTexto] = $cant <= 0 ? ["stock-out", "Agotado"] : ($cant <= 5 ? ["stock-low", "Quedan ".$cant] : ["stock-ok", "Disponible"]);
    $compat = trim(($parte["marca"] ?? "")." ".($parte["modelo"] ?? ""));
    if ($compat !== "" && !empty($parte["anio"])) $compat .= " · ".$parte["anio"];
    $volver = $_SERVER["REQUEST_URI"] ?? "";
    $fotosGaleria = [$parte]; // hoy una foto por pieza; la galería múltiple es otra sesión
  ?>
  <div class="detalle-grid">
    <figure class="galeria" data-galeria>
      <div class="galeria-principal">
        <?php $fotoParte=$parte; $fotoTam="medium"; $fotoClase="foto-ancha"; $fotoAlt=$parte["nombre"]; $fotoLazy=false; include BASE_PATH."/app/Views/layouts/foto_pieza.php"; ?>
        <button type="button" class="galeria-ampliar" aria-label="Ampliar la imagen (todavía no disponible)" title="Ampliar (próximamente)" disabled><i aria-hidden="true" class="bi bi-arrows-fullscreen"></i></button>
      </div>
      <ul class="galeria-miniaturas" role="list"<?=count($fotosGaleria)<2?" hidden":""?>>
        <?php foreach($fotosGaleria as $i=>$f): ?>
        <li><button type="button" aria-label="Ver imagen <?=$i+1?> de <?=count($fotosGaleria)?>"<?=$i===0?' aria-current="true"':''?>><?php $fotoParte=$f; $fotoTam="thumbnails"; $fotoClase="foto-64"; $fotoAlt=""; include BASE_PATH."/app/Views/layouts/foto_pieza.php"; ?></button></li>
        <?php endforeach; ?>
      </ul>
    </figure>

    <div class="detalle-info">
      <?php if($parte["seccion"]??""): ?><span class="chip-warning detalle-seccion"><?=htmlspecialchars($parte["seccion"])?></span><?php endif; ?>
      <h1 class="detalle-titulo"><?=htmlspecialchars($parte["nombre"])?></h1>
      <?php if($compat!==""): ?><p class="detalle-compat"><?=htmlspecialchars($compat)?> <span class="text-secondary">(compatible)</span></p><?php endif; ?>
      <?php if($totalRating>0): ?>
      <div class="d-flex align-items-center gap-2 mb-2"><?=starsHtml($promedio,16)?><span class="fw-bold num"><?=number_format($promedio,1)?></span><span class="text-secondary small">(<?=$totalRating?> reseña<?=$totalRating !== 1 ? "s" : ""?>)</span></div>
      <?php endif; ?>
      <div class="detalle-precio">
        <span class="detail-price num">$<?=number_format($parte["precio"],2)?></span>
        <span class="stock-insignia <?=$stClase?>"><?=$stTexto?></span>
      </div>
      <p class="text-secondary small mb-3">Precio por unidad</p>

      <?php if($cant>0): ?>
      <div class="detalle-compra">
        <?php if($cant>1): ?>
        <div class="cantidad-fila" data-cantidad data-max="<?=$cant?>">
          <label for="f-cantidad" class="form-label-dark mb-0">Cantidad</label>
          <div class="cantidad-control">
            <button type="button" class="cantidad-menos" aria-label="Disminuir cantidad" data-cantidad-menos><i aria-hidden="true" class="bi bi-dash-lg"></i></button>
            <input id="f-cantidad" form="form-agregar" type="number" name="cantidad" class="cantidad-campo" value="1" min="1" max="<?=$cant?>" step="1" inputmode="numeric" data-cantidad-campo>
            <button type="button" class="cantidad-mas" aria-label="Aumentar cantidad" data-cantidad-mas><i aria-hidden="true" class="bi bi-plus-lg"></i></button>
          </div>
        </div>
        <?php endif; ?>
        <form id="form-agregar" method="POST" action="<?=APP_URL?>/?action=agregar_carrito&id=<?=(int)$parte["id"]?>" class="detalle-agregar-form" data-agregar-carrito data-nombre="<?=htmlspecialchars($parte["nombre"])?>">
          <?=CsrfToken::field()?>
          <input type="hidden" name="volver" value="<?=htmlspecialchars($volver)?>">
          <button type="submit" class="btn btn-gold btn-lg detalle-agregar">Añadir al carrito</button>
        </form>
        <a href="<?=APP_URL?>/?action=comprar&id=<?=(int)$parte["id"]?>" class="btn btn-outline-secondary btn-lg detalle-comprar" data-comprar-ahora>Comprar ahora</a>
      </div>
      <?php else: ?>
      <div role="alert" class="alert alert-danger mt-3">Pieza agotada. <a href="<?=APP_URL?>/?ver=catalogo" class="alert-link">Ver más piezas</a></div>
      <?php endif; ?>

      <?php if($parte["descripcion"]): ?><h2 class="detalle-subtitulo">Descripción</h2><p class="text-secondary lh-relaxed"><?=nl2br(htmlspecialchars($parte["descripcion"]))?></p><?php endif; ?>
      <?php if($parte["firma_digital"]): ?><div class="firma-badge"><i aria-hidden="true" class="bi bi-shield-check text-success"></i>Verificado con firma digital HMAC-SHA256</div><?php endif; ?>
    </div>
  </div>

  <?php if(!empty($relacionadas)): ?>
  <section class="detalle-relacionadas" aria-labelledby="relacionadas-titulo">
    <h2 id="relacionadas-titulo" class="detalle-subtitulo">Piezas relacionadas</h2>
    <div class="rejilla-piezas">
      <?php $tarjetaVolver=$volver; foreach($relacionadas as $r): $tarjetaParte=$r; include BASE_PATH."/app/Views/layouts/tarjeta_pieza.php"; endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ════ RESEÑAS Y COMENTARIOS ════════════════════════════════════ -->
  <hr style="border-color:var(--border)" class="mb-5">
  <div style="max-width:820px;margin:0 auto">

    <!-- Encabezado -->
    <div class="text-center mb-4">
      <h2 class="h4 fw-bold text-white"><i aria-hidden="true" class="bi bi-star me-2 text-warning"></i>Reseñas y Comentarios</h2>
      <?php if($totalRating>0): ?>
      <div class="d-flex align-items-center justify-content-center gap-2 mt-1">
        <?=starsHtml($promedio,20)?><span class="text-warning fs-5 fw-bold"><?=number_format($promedio,1)?></span>
        <span class="text-secondary">/5 &nbsp;·&nbsp; <?=$totalRating?> reseña<?=$totalRating!==1?"s":""?></span>
      </div>
      <?php endif; ?>
    </div>

    <!-- Formulario -->
    <div class="dark-card p-4 mb-4">
      <?php if($isCliente): ?>
      <h3 class="h6 fw-bold text-white mb-1"><i aria-hidden="true" class="bi bi-star me-2 text-warning"></i>Deja tu reseña</h3>
      <p class="text-secondary small mb-3">Tu reseña se publica de inmediato.</p>
      <?php if($error??""): ?><div role="alert" class="alert-dark-danger d-flex gap-2 mb-3 small"><i aria-hidden="true" class="bi bi-exclamation-circle text-danger"></i><?=htmlspecialchars($error)?></div><?php endif; ?>
      <form action="<?=APP_URL?>/?action=detalle&id=<?=$parte["id"]?>" method="POST">
        <?=CsrfToken::field()?>
        <div class="mb-3">
          <div class="form-label-dark mb-1">Calificación (opcional)</div>
          <div class="star-rate" role="radiogroup" aria-label="Calificación">
            <input type="radio" name="rating" id="s5" value="5"><label for="s5" title="Excelente"><i aria-hidden="true" class="bi bi-star-fill"></i><span class="visually-hidden">5 estrellas: excelente</span></label>
            <input type="radio" name="rating" id="s4" value="4"><label for="s4" title="Muy bueno"><i aria-hidden="true" class="bi bi-star-fill"></i><span class="visually-hidden">4 estrellas: muy bueno</span></label>
            <input type="radio" name="rating" id="s3" value="3"><label for="s3" title="Bueno"><i aria-hidden="true" class="bi bi-star-fill"></i><span class="visually-hidden">3 estrellas: bueno</span></label>
            <input type="radio" name="rating" id="s2" value="2"><label for="s2" title="Regular"><i aria-hidden="true" class="bi bi-star-fill"></i><span class="visually-hidden">2 estrellas: regular</span></label>
            <input type="radio" name="rating" id="s1" value="1"><label for="s1" title="Malo"><i aria-hidden="true" class="bi bi-star-fill"></i><span class="visually-hidden">1 estrella: malo</span></label>
          </div>
        </div>
        <div class="mb-3"><label for="f-nombre-visitante" class="form-label-dark">Tu nombre *</label><input id="f-nombre-visitante" type="text" name="nombre_visitante" class="form-control dark-input" required autocomplete="name" value="<?=htmlspecialchars($_SESSION["user"]["nombre"]??"")?>" maxlength="100"></div>
        <div class="mb-3"><label for="f-comentario" class="form-label-dark">Tu reseña *</label><textarea id="f-comentario" name="comentario" class="form-control dark-input auto-exp" rows="3" required minlength="5" placeholder="Cuéntanos tu experiencia con esta pieza..."></textarea></div>
        <button type="submit" class="btn btn-gold fw-bold w-100"><i aria-hidden="true" class="bi bi-star me-2"></i>Publicar reseña</button>
      </form>
      <?php else: ?>
      <h3 class="h6 fw-bold text-white mb-1"><i aria-hidden="true" class="bi bi-chat-dots me-2 text-warning"></i>Deja un comentario</h3>
      <p class="text-secondary small mb-3">
        <a href="<?=APP_URL?>/login.php" class="text-warning">Inicia sesión como Cliente</a> para dejar una reseña con calificación. Los comentarios son públicos inmediatamente.
      </p>
      <?php if($error??""): ?><div role="alert" class="alert-dark-danger d-flex gap-2 mb-3 small"><i aria-hidden="true" class="bi bi-exclamation-circle text-danger"></i><?=htmlspecialchars($error)?></div><?php endif; ?>
      <form action="<?=APP_URL?>/?action=detalle&id=<?=$parte["id"]?>" method="POST">
        <?=CsrfToken::field()?>
        <div class="mb-3"><label for="f-nombre-visitante" class="form-label-dark">Tu nombre *</label><input id="f-nombre-visitante" type="text" name="nombre_visitante" class="form-control dark-input" required maxlength="100" autocomplete="name"></div>
        <div class="mb-3"><label for="f-comentario" class="form-label-dark">Comentario *</label><textarea id="f-comentario" name="comentario" class="form-control dark-input auto-exp" rows="3" required minlength="5" placeholder="Escribe tu comentario..."></textarea></div>
        <button type="submit" class="btn btn-outline-warning fw-bold w-100"><i aria-hidden="true" class="bi bi-send me-2"></i>Publicar comentario</button>
      </form>
      <?php endif; ?>
    </div>

    <!-- Lista de comentarios -->
    <?php if(empty($comentarios)): ?>
    <div class="review-empty"><i aria-hidden="true" class="bi bi-chat-square-text display-4 d-block mb-3 opacity-25"></i>No hay comentarios aún. ¡Sé el primero en opinar!</div>
    <?php else: ?>
    <div id="lista-reviews">
      <?php foreach($comentarios as $c): ?>
      <div class="review-card">
        <div class="d-flex justify-content-between align-items-start mb-1">
          <div>
            <span class="review-author"><i aria-hidden="true" class="bi bi-person-circle me-1"></i><?=htmlspecialchars($c["nombre_visitante"])?></span>
            <?php if(!is_null($c["rating"])): ?><span class="ms-2"><?=starsHtml((float)$c["rating"],14)?></span><?php endif; ?>
          </div>
          <span class="review-date"><?=date("d/m/Y",strtotime($c["creado_en"]))?></span>
        </div>
        <p class="review-body mb-0"><?=nl2br(htmlspecialchars($c["comentario"]))?></p>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php if(($parte["cantidad"]??0)>0): ?>
<div class="barra-compra" role="region" aria-label="Comprar esta pieza">
  <span class="precio num" aria-hidden="true">$<?=number_format($parte["precio"],2)?></span>
  <button type="submit" form="form-agregar" class="btn btn-gold barra-compra-boton">Añadir al carrito</button>
</div>
<?php endif; ?>
<script src="<?=Assets::url("js/detalle.js")?>"></script>
<?php include BASE_PATH."/app/Views/layouts/public_footer.php"; ?>
