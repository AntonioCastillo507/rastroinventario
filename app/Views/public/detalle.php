<?php
$title=htmlspecialchars($parte["nombre"])." — Rastro del Istmo";
include BASE_PATH."/app/Views/layouts/public_header.php";
function starsHtml(float $n,int $sz=16): string {
    $out="<span>";
    for($i=1;$i<=5;$i++) $out.="<i class=\"bi bi-star".($i<=$n?"-fill":($i-0.5<=$n?"-half":" text-secondary")).($i<=$n?" filled":" empty")."\" style=\"font-size:{$sz}px\"></i>";
    return $out."</span>";
}
?>
<a href="javascript:history.back()" class="btn-float-back" title="Volver"><i class="bi bi-arrow-left"></i></a>

<div class="container-xl py-4">
  <!-- Breadcrumb -->
  <nav class="mb-4"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?=APP_URL?>/" class="text-warning">Inicio</a></li>
    <li class="breadcrumb-item"><a href="<?=APP_URL?>/?ver=catalogo" class="text-warning">Catálogo</a></li>
    <li class="breadcrumb-item active text-secondary"><?=htmlspecialchars($parte["nombre"])?></li>
  </ol></nav>

  <!-- FICHA PRODUCTO -->
  <div class="row g-5 mb-5">
    <div class="col-md-5">
      <div style="aspect-ratio:4/3;background:var(--bg-2) url('data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'64\' height=\'64\' fill=\'%23363C45\' viewBox=\'0 0 16 16\'%3E%3Cpath d=\'M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492M5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0\'/%3E%3C/svg%3E') center/64px no-repeat;border-radius:var(--radius);overflow:hidden;border:1px solid var(--border)">
        <?php if($parte["imagen_grande"]||$parte["thumbnail"]): ?>
        <img src="<?=APP_URL?>/uploads/<?=$parte["imagen_grande"]?"parts/".htmlspecialchars($parte["imagen_grande"]):"thumbnails/".htmlspecialchars($parte["thumbnail"])?>" style="width:100%;height:100%;object-fit:contain" alt="<?=htmlspecialchars($parte["nombre"])?>">
        <?php endif; ?>
      </div>
    </div>
    <div class="col-md-7">
      <?php if($parte["seccion"]??""): ?><span class="chip-warning mb-2 d-inline-flex"><i class="bi bi-archive me-1"></i><?=htmlspecialchars($parte["seccion"])?></span><?php endif; ?>
      <h2 class="fw-bold text-white mt-2 mb-1"><?=htmlspecialchars($parte["nombre"])?></h2>
      <?php if($parte["marca"]??""): ?><p class="text-secondary mb-1"><i class="bi bi-car-front-fill me-1 text-warning"></i><?=htmlspecialchars($parte["marca"]." ".$parte["modelo"]." (".$parte["anio"].")")?></p><?php endif; ?>
      <?php if($totalRating>0): ?>
<div class="d-flex align-items-center gap-2 mb-2"><?=starsHtml($promedio,16)?><span class="text-warning fw-bold"><?=number_format($promedio,1)?></span><span class="text-secondary small">(<?=$totalRating?> reseña<?=$totalRating !== 1 ? "s" : ""?>)</span></div>
      <?php endif; ?>
      <?php if($parte["descripcion"]): ?><p class="text-secondary lh-relaxed mt-2"><?=nl2br(htmlspecialchars($parte["descripcion"]))?></p><?php endif; ?>
      <hr style="border-color:var(--border)">
      <div class="d-flex align-items-end gap-4 my-3">
        <div><div style="font-size:2.4rem;font-weight:800;color:var(--success)">$<?=number_format($parte["precio"],2)?></div><div class="text-secondary small">Precio por unidad</div></div>
        <div><?php if($parte["cantidad"]>0): ?><span class="chip-success"><i class="bi bi-check-circle me-1"></i>Disponible</span><div class="text-secondary small mt-1"><?=$parte["cantidad"]?> uds.</div><?php else: ?><span class="chip-danger">Agotado</span><?php endif; ?></div>
      </div>
      <?php if($parte["firma_digital"]): ?><div class="firma-badge mb-3"><i class="bi bi-shield-check text-success"></i>Verificado con firma digital HMAC-SHA256</div><?php endif; ?>
      <!-- Botones de acción -->
      <?php if($parte["cantidad"]>0): ?>
      <div class="d-flex gap-3 flex-wrap mt-3">
        <a href="<?=APP_URL?>/?action=comprar&id=<?=$parte["id"]?>" class="btn btn-gold fw-bold px-5 py-2" style="font-size:1.05rem"><i class="bi bi-bag-plus me-2"></i>Comprar Ahora</a>
        <a href="<?=APP_URL?>/?action=agregar_carrito&id=<?=$parte["id"]?>" class="btn btn-outline-warning py-2 px-4"><i class="bi bi-cart-plus me-1"></i>Añadir al carrito</a>
        <a href="<?=APP_URL?>/?ver=catalogo" class="btn btn-outline-secondary py-2"><i class="bi bi-grid me-1"></i>Catálogo</a>
      </div>
      <?php else: ?>
      <div class="alert alert-danger mt-3"><i class="bi bi-x-circle me-2"></i>Pieza agotada. <a href="<?=APP_URL?>/?ver=catalogo" class="text-white">Ver más piezas</a></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ════ RESEÑAS Y COMENTARIOS ════════════════════════════════════ -->
  <hr style="border-color:var(--border)" class="mb-5">
  <div style="max-width:820px;margin:0 auto">

    <!-- Encabezado -->
    <div class="text-center mb-4">
      <h4 class="fw-bold text-white"><i class="bi bi-star me-2 text-warning"></i>Reseñas y Comentarios</h4>
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
      <h6 class="fw-bold text-white mb-1"><i class="bi bi-star me-2 text-warning"></i>Deja tu reseña</h6>
      <p class="text-secondary small mb-3">Tu reseña se publica de inmediato.</p>
      <?php if($error??""): ?><div class="alert-dark-danger d-flex gap-2 mb-3 small"><i class="bi bi-exclamation-circle text-danger"></i><?=htmlspecialchars($error)?></div><?php endif; ?>
      <form action="<?=APP_URL?>/?action=detalle&id=<?=$parte["id"]?>" method="POST">
        <?=CsrfToken::field()?>
        <div class="mb-3">
          <div class="form-label-dark mb-1">Calificación (opcional)</div>
          <div class="star-rate">
            <input type="radio" name="rating" id="s5" value="5"><label for="s5" title="Excelente">★</label>
            <input type="radio" name="rating" id="s4" value="4"><label for="s4" title="Muy bueno">★</label>
            <input type="radio" name="rating" id="s3" value="3"><label for="s3" title="Bueno">★</label>
            <input type="radio" name="rating" id="s2" value="2"><label for="s2" title="Regular">★</label>
            <input type="radio" name="rating" id="s1" value="1"><label for="s1" title="Malo">★</label>
          </div>
        </div>
        <div class="mb-3"><label class="form-label-dark">Tu nombre *</label><input type="text" name="nombre_visitante" class="form-control dark-input" required value="<?=htmlspecialchars($_SESSION["user"]["nombre"]??"")?>" maxlength="100"></div>
        <div class="mb-3"><label class="form-label-dark">Tu reseña *</label><textarea name="comentario" class="form-control dark-input auto-exp" rows="3" required minlength="5" placeholder="Cuéntanos tu experiencia con esta pieza..."></textarea></div>
        <button type="submit" class="btn btn-gold fw-bold w-100"><i class="bi bi-star me-2"></i>Publicar reseña</button>
      </form>
      <?php else: ?>
      <h6 class="fw-bold text-white mb-1"><i class="bi bi-chat-dots me-2 text-warning"></i>Deja un comentario</h6>
      <p class="text-secondary small mb-3">
        <a href="<?=APP_URL?>/login.php" class="text-warning">Inicia sesión como Cliente</a> para dejar una reseña con calificación. Los comentarios son públicos inmediatamente.
      </p>
      <?php if($error??""): ?><div class="alert-dark-danger d-flex gap-2 mb-3 small"><i class="bi bi-exclamation-circle text-danger"></i><?=htmlspecialchars($error)?></div><?php endif; ?>
      <form action="<?=APP_URL?>/?action=detalle&id=<?=$parte["id"]?>" method="POST">
        <?=CsrfToken::field()?>
        <div class="mb-3"><label class="form-label-dark">Tu nombre *</label><input type="text" name="nombre_visitante" class="form-control dark-input" required maxlength="100"></div>
        <div class="mb-3"><label class="form-label-dark">Comentario *</label><textarea name="comentario" class="form-control dark-input auto-exp" rows="3" required minlength="5" placeholder="Escribe tu comentario..."></textarea></div>
        <button type="submit" class="btn btn-outline-warning fw-bold w-100"><i class="bi bi-send me-2"></i>Publicar comentario</button>
      </form>
      <?php endif; ?>
    </div>

    <!-- Lista de comentarios -->
    <?php if(empty($comentarios)): ?>
    <div class="review-empty"><i class="bi bi-chat-square-text display-4 d-block mb-3 opacity-25"></i>No hay comentarios aún. ¡Sé el primero en opinar!</div>
    <?php else: ?>
    <div id="lista-reviews">
      <?php foreach($comentarios as $c): ?>
      <div class="review-card">
        <div class="d-flex justify-content-between align-items-start mb-1">
          <div>
            <span class="review-author"><i class="bi bi-person-circle me-1"></i><?=htmlspecialchars($c["nombre_visitante"])?></span>
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
<?php include BASE_PATH."/app/Views/layouts/public_footer.php"; ?>
