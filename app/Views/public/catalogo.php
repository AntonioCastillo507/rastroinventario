<?php
$title="Catálogo"; $navActivo="catalogo";
include BASE_PATH."/app/Views/layouts/public_header.php";
// Filtros activos como chips que se quitan de uno en uno (el resto se conserva en la dirección)
$urlSin = function(array $quitar) use ($pagParams): string {
    $p = array_filter(array_diff_key($pagParams, array_flip($quitar)), fn($v) => $v !== "" && $v !== null && $v !== 0);
    return APP_URL."/?".http_build_query($p, "", "&", PHP_QUERY_RFC3986);
};
$nombreSeccion = "";
foreach ($secciones as $s) if (($secId??0) == $s["id"]) { $nombreSeccion = $s["nombre"]; break; }
$dinero = fn(float $v): string => "$".rtrim(rtrim(number_format($v, 2, ".", ""), "0"), ".");
// cada chip: texto, valor numérico (va en la fuente de cifras; null si no hay), dirección que lo quita y qué filtro es
$chips = [];
if ($q??"")        $chips[] = ["Búsqueda: «".$q."»", null, $urlSin(["q","page"]), "búsqueda"];
if ($secId??0)     $chips[] = ["Sección: ".($nombreSeccion ?: "n.º ".$secId), null, $urlSin(["seccion","page"]), "sección"];
if ($marca??"")    $chips[] = ["Marca: ".$marca, null, $urlSin(["marca","page"]), "marca"];
if (($pMin??0)>0)  $chips[] = ["Desde ", $dinero($pMin), $urlSin(["precio_min","page"]), "precio mínimo"];
if (($pMax??0)>0)  $chips[] = ["Hasta ", $dinero($pMax), $urlSin(["precio_max","page"]), "precio máximo"];
$tarjetaVolver = $_SERVER["REQUEST_URI"] ?? "";
$total = $pag->total;
?>
<div class="container-xl catalogo-pagina">
  <div class="catalogo-cabecera">
    <h1 class="catalogo-titulo"><?=($q??"")?"Resultados para «".htmlspecialchars($q)."»":"Catálogo"?></h1>
    <p class="catalogo-conteo num" id="catalogo-conteo"><?=$total?> pieza<?=$total!==1?"s":""?></p>
  </div>

  <div class="catalogo-barra d-lg-none">
    <button type="button" class="btn btn-outline-secondary btn-filtros" data-bs-toggle="collapse" data-bs-target="#filtros" aria-expanded="false" aria-controls="filtros">
      <i aria-hidden="true" class="bi bi-sliders"></i><span>Filtros (<?=count($chips)?>)</span>
    </button>
  </div>

  <?php if($chips): ?>
  <ul class="chips-filtro" aria-label="Filtros activos">
    <?php foreach($chips as [$texto, $valor, $url, $que]): ?>
    <li><a class="chip-filtro" href="<?=htmlspecialchars($url)?>" aria-label="Quitar filtro de <?=htmlspecialchars($que)?>: <?=htmlspecialchars($texto.$valor)?>"><span><?=htmlspecialchars($texto)?><?php if($valor!==null): ?><span class="num"><?=htmlspecialchars($valor)?></span><?php endif; ?></span><i aria-hidden="true" class="bi bi-x-lg"></i></a></li>
    <?php endforeach; ?>
    <?php if(count($chips)>1): ?><li><a class="chip-limpiar" href="<?=APP_URL?>/?ver=catalogo">Quitar todos</a></li><?php endif; ?>
  </ul>
  <?php endif; ?>

  <div class="catalogo-layout">
    <aside id="filtros" class="catalogo-filtros collapse d-lg-block" aria-label="Filtros del catálogo">
      <form method="GET" action="<?=APP_URL?>/" id="frmFiltro" class="filtros-form">
        <input type="hidden" name="ver" value="catalogo">
        <?php if($q??""): ?><input type="hidden" name="q" value="<?=htmlspecialchars($q)?>"><?php endif; ?>
        <fieldset class="filtro-grupo">
          <legend class="filter-label">Sección</legend>
          <div class="form-check"><input class="form-check-input" type="radio" name="seccion" id="f-sec-0" value="" <?=!($secId??0)?"checked":""?>><label class="form-check-label" for="f-sec-0">Todas</label></div>
          <?php foreach($secciones as $s): ?>
          <div class="form-check"><input class="form-check-input" type="radio" name="seccion" id="f-sec-<?=(int)$s["id"]?>" value="<?=(int)$s["id"]?>" <?=($secId??0)==$s["id"]?"checked":""?>><label class="form-check-label" for="f-sec-<?=(int)$s["id"]?>"><?=htmlspecialchars($s["nombre"])?> <span class="num filtro-cuenta">(<?=(int)$s["total"]?>)</span></label></div>
          <?php endforeach; ?>
        </fieldset>
        <div class="filtro-grupo">
          <label class="filter-label d-block" for="f-marca">Marca</label>
          <select name="marca" id="f-marca" class="form-select dark-input">
            <option value="">Todas las marcas</option>
            <?php foreach($marcas as $m): ?><option value="<?=htmlspecialchars($m)?>" <?=($marca??"")===($m??"")?"selected":""?>><?=htmlspecialchars($m)?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="filtro-grupo filtro-precios">
          <div>
            <label class="filter-label d-block" for="f-precio-min">Precio mín ($)</label>
            <input type="number" name="precio_min" id="f-precio-min" class="form-control dark-input" placeholder="<?=number_format($precioMin??0,0)?>" value="<?=($pMin??0)>0?$pMin:"" ?>" min="0" step="1" inputmode="decimal">
          </div>
          <div>
            <label class="filter-label d-block" for="f-precio-max">Precio máx ($)</label>
            <input type="number" name="precio_max" id="f-precio-max" class="form-control dark-input" placeholder="<?=number_format($precioMax??9999,0)?>" value="<?=($pMax??0)>0?$pMax:"" ?>" min="0" step="1" inputmode="decimal">
          </div>
        </div>
        <div class="filtro-grupo">
          <label class="filter-label d-block" for="f-orden">Ordenar</label>
          <select name="orden" id="f-orden" class="form-select dark-input">
            <option value="recientes"   <?=($orden??"recientes")==="recientes"  ?"selected":""?>>Más recientes</option>
            <option value="precio_asc"  <?=($orden??"")==="precio_asc"  ?"selected":""?>>Precio: menor a mayor</option>
            <option value="precio_desc" <?=($orden??"")==="precio_desc" ?"selected":""?>>Precio: mayor a menor</option>
            <option value="stock"       <?=($orden??"")==="stock"       ?"selected":""?>>Mayor stock</option>
            <option value="nombre"      <?=($orden??"")==="nombre"      ?"selected":""?>>Nombre A–Z</option>
          </select>
        </div>
        <div class="filtro-acciones">
          <button type="submit" class="btn btn-gold">Aplicar filtros</button>
          <a href="<?=APP_URL?>/?ver=catalogo" class="btn btn-outline-secondary">Limpiar</a>
        </div>
      </form>
    </aside>

    <section class="catalogo-resultados" aria-labelledby="catalogo-conteo">
      <?php if(empty($partes)): ?>
      <div class="catalogo-vacio">
        <h2 class="h5">No hay piezas con esos filtros</h2>
        <p class="text-secondary">Quita algún filtro o mira todo el catálogo.</p>
        <a href="<?=APP_URL?>/?ver=catalogo" class="btn btn-gold">Ver todo el catálogo</a>
      </div>
      <?php else: ?>
      <h2 class="visually-hidden">Piezas</h2>
      <div class="rejilla-piezas">
        <?php foreach($partes as $p): $tarjetaParte=$p; include BASE_PATH."/app/Views/layouts/tarjeta_pieza.php"; endforeach; ?>
      </div>
      <?php $pagBase=APP_URL."/"; include BASE_PATH."/app/Views/layouts/paginacion.php"; ?>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php include BASE_PATH."/app/Views/layouts/public_footer.php"; ?>
