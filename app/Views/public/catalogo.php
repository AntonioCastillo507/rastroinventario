<?php
$title="Catálogo"; $navActivo="catalogo";
include BASE_PATH."/app/Views/layouts/public_header.php";
?>
<div class="container-xl py-4">
  <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
    <a href="<?=APP_URL?>/" class="btn-back" aria-label="Ir al inicio"><i aria-hidden="true" class="bi bi-house"></i></a>
    <div>
      <h1 class="h4 fw-bold text-white mb-0">
        <?php if($q??""): ?><i aria-hidden="true" class="bi bi-search me-2 text-warning"></i>Resultados: <em class="text-warning">"<?=htmlspecialchars($q)?>"</em>
        <?php elseif($secId??0): $sn=""; foreach($secciones as $s){if($s["id"]==$secId){$sn=$s["nombre"].($s["descripcion"]?" — ".$s["descripcion"]:"");break;}} ?><i aria-hidden="true" class="bi bi-archive me-2 text-warning"></i><?=htmlspecialchars($sn)?>
        <?php else: ?><i aria-hidden="true" class="bi bi-grid me-2 text-warning"></i>Catálogo Completo<?php endif; ?>
      </h1>
      <p class="text-secondary small mb-0"><?=count($partes)?> pieza<?=count($partes)!==1?"s":""?> encontrada<?=count($partes)!==1?"s":""?></p>
    </div>
  </div>

  <!-- FILTROS -->
  <div class="filter-bar mb-4">
    <form method="GET" action="<?=APP_URL?>/" id="frmFiltro">
      <input type="hidden" name="ver" value="catalogo">
      <?php if($q??""): ?><input type="hidden" name="q" value="<?=htmlspecialchars($q)?>"> <?php endif; ?>
      <div class="row g-3 align-items-end">
        <div class="col-12">
          <div class="filter-label" id="f-seccion-etiqueta">Sección</div>
          <div class="d-flex flex-wrap gap-2" role="group" aria-labelledby="f-seccion-etiqueta">
            <a href="<?=APP_URL?>/?ver=catalogo<?=$q?"&q=".urlencode($q):""?>" class="sec-pill <?=!($secId??0)?"active":""?>">Todas</a>
            <?php foreach($secciones as $s): ?>
            <a href="<?=APP_URL?>/?ver=catalogo&seccion=<?=$s["id"]?><?=$q?"&q=".urlencode($q):""?><?=($marca??"")?"&marca=".urlencode($marca??""):""?>" class="sec-pill <?=($secId??0)==$s["id"]?"active":""?>">
              <strong><?=htmlspecialchars($s["nombre"])?></strong><?php if($s["descripcion"]??""): ?> — <?=htmlspecialchars($s["descripcion"])?><?php endif; ?> <span style="opacity:.55">(<?=$s["total"]?>)</span>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="col-md-3 col-6">
          <label class="filter-label d-block" for="f-marca">Marca</label>
          <select name="marca" id="f-marca" class="form-select dark-input form-select-sm" onchange="this.form.submit()">
            <option value="">Todas las marcas</option>
            <?php foreach($marcas as $m): ?><option value="<?=htmlspecialchars($m)?>" <?=($marca??"")===($m??"")?"selected":""?>><?=htmlspecialchars($m)?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 col-6">
          <label class="filter-label d-block" for="f-precio-min">Precio mín ($)</label>
          <input type="number" name="precio_min" id="f-precio-min" class="form-control dark-input form-control-sm" placeholder="<?=number_format($precioMin??0,0)?>" value="<?=($pMin??0)>0?$pMin:"" ?>" min="0" step="1">
        </div>
        <div class="col-md-2 col-6">
          <label class="filter-label d-block" for="f-precio-max">Precio máx ($)</label>
          <input type="number" name="precio_max" id="f-precio-max" class="form-control dark-input form-control-sm" placeholder="<?=number_format($precioMax??9999,0)?>" value="<?=($pMax??0)>0?$pMax:"" ?>" min="0" step="1">
        </div>
        <div class="col-md-3 col-6">
          <label class="filter-label d-block" for="f-orden">Ordenar</label>
          <select name="orden" id="f-orden" class="form-select dark-input form-select-sm" onchange="this.form.submit()">
            <option value="recientes"   <?=($orden??"recientes")==="recientes"  ?"selected":""?>>Más recientes</option>
            <option value="precio_asc"  <?=($orden??"")==="precio_asc"  ?"selected":""?>>Precio ↑</option>
            <option value="precio_desc" <?=($orden??"")==="precio_desc" ?"selected":""?>>Precio ↓</option>
            <option value="stock"       <?=($orden??"")==="stock"       ?"selected":""?>>Mayor stock</option>
            <option value="nombre"      <?=($orden??"")==="nombre"      ?"selected":""?>>Nombre A–Z</option>
          </select>
        </div>
        <div class="col-md-2 col-12 d-flex gap-2">
          <button type="submit" class="btn btn-gold btn-sm flex-fill"><i aria-hidden="true" class="bi bi-funnel me-1"></i>Filtrar</button>
          <a href="<?=APP_URL?>/?ver=catalogo" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros" aria-label="Limpiar filtros"><i aria-hidden="true" class="bi bi-x"></i></a>
        </div>
      </div>
    </form>
  </div>

  <?php if(empty($partes)): ?>
  <div class="text-center py-5"><i aria-hidden="true" class="bi bi-search display-1 text-secondary opacity-25"></i>
    <h2 class="h5 text-secondary mt-3">No hay piezas con esos filtros</h2>
    <a href="<?=APP_URL?>/?ver=catalogo" class="btn btn-gold mt-3"><i aria-hidden="true" class="bi bi-arrow-counterclockwise me-2"></i>Ver todo el catálogo</a>
  </div>
  <?php else: ?>
  <div class="partes-grid">
    <?php foreach($partes as $p): ?>
    <div class="pub-card">
      <a href="<?=APP_URL?>/?action=detalle&id=<?=$p["id"]?>" class="pub-card-img-wrap">
        <?php $fotoParte=$p; $fotoTam="thumbnails"; $fotoClase="foto-ancha"; $fotoAlt=$p["nombre"]; include BASE_PATH."/app/Views/layouts/foto_pieza.php"; ?>
        <span class="pub-card-badge <?=$p["cantidad"]>5?"badge-stock-ok":($p["cantidad"]>0?"badge-stock-low":"badge-stock-out")?>"><?=$p["cantidad"]>0?$p["cantidad"]." en stock":"Agotado"?></span>
      </a>
      <div class="pub-card-body">
        <h2 class="h6 pub-card-title"><?=htmlspecialchars($p["nombre"])?></h2>
        <?php if($p["marca"]??""): ?><p class="pub-card-meta"><i aria-hidden="true" class="bi bi-car-front me-1"></i><?=htmlspecialchars($p["marca"]." ".$p["modelo"]." ".$p["anio"])?></p><?php endif; ?>
        <?php if($p["seccion"]??""): ?><p class="pub-card-meta"><i aria-hidden="true" class="bi bi-archive me-1"></i><?=htmlspecialchars($p["seccion"])?></p><?php endif; ?>
        <div class="pub-card-footer">
          <span class="pub-price num">$<?=number_format($p["precio"],2)?></span>
          <div class="d-flex gap-1">
            <a href="<?=APP_URL?>/?action=detalle&id=<?=$p["id"]?>" class="btn btn-outline-secondary btn-sm" title="Ver detalle" aria-label="Ver detalle de <?=htmlspecialchars($p["nombre"])?>"><i aria-hidden="true" class="bi bi-eye"></i></a>
            <?php if($p["cantidad"]>0): ?><form method="POST" action="<?=APP_URL?>/?action=agregar_carrito&id=<?=$p["id"]?>" class="d-inline"><?=CsrfToken::field()?><button type="submit" class="btn btn-gold btn-sm" title="Añadir al carrito" aria-label="Añadir <?=htmlspecialchars($p["nombre"])?> al carrito"><i aria-hidden="true" class="bi bi-cart-plus"></i></button></form><?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php $pagBase=APP_URL."/"; include BASE_PATH."/app/Views/layouts/paginacion.php"; ?>
  <?php endif; ?>
</div>
<?php include BASE_PATH."/app/Views/layouts/public_footer.php"; ?>
