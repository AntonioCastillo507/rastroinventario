<?php
$title = "Dashboard";
include BASE_PATH . "/app/Views/layouts/admin_header.php";
$money  = fn($n) => "$" . number_format((float)$n, 2);
$pct    = fn($n) => str_replace(".", ",", number_format(abs((float)$n), 1)) . " %";
$rangoTexto = [7 => "7 días", 30 => "30 días", 90 => "90 días", 365 => "Año", 0 => "Todo"];
$rangoLargo = [7 => "los últimos 7 días", 30 => "los últimos 30 días", 90 => "los últimos 90 días", 365 => "el último año", 0 => "todo el historial"];
$hayVentas  = !empty($serie) && array_sum($serie["actual"]) > 0;
$grafica = [];
if ($w["ventas"]) {
  $grafica["serie"]      = $serie;
  $grafica["categorias"] = $categorias;
  $grafica["partes"]     = $partes;
  $grafica["marcas"]     = $marcas;
}
if ($w["pedidos"]) $grafica["estados"] = $estados;
$pendientes = $w["pedidos"] ? (int)$totalPorAtender : 0;
$pocoStock  = $w["partes"] ? (int)$totalStockBajo : 0;
$agotadas   = $w["partes"] ? (int)$totalAgotadas : 0;
$nComent    = $w["comentarios"] ? (int)$comentariosSemana : 0;
// Avisos de la franja: cada uno es una acción. estado = adv (aviso), err (error), info, neu (neutro), ok (éxito)
$avisos = [];
if ($pendientes) $avisos[] = ["estado" => "adv", "icono" => "bag-check", "n" => $pendientes, "texto" => $pendientes === 1 ? "pedido por atender" : "pedidos por atender", "extra" => 0, "url" => APP_URL . "/admin/?mod=pedidos", "boton" => "Atender pedidos"];
elseif ($w["pedidos"]) $avisos[] = ["estado" => "neu", "icono" => "bag-check", "n" => 0, "texto" => "pedidos por atender", "extra" => 0, "url" => "", "boton" => ""];
if ($pocoStock) $avisos[] = ["estado" => $agotadas ? "err" : "adv", "icono" => $agotadas ? "exclamation-octagon" : "exclamation-triangle", "n" => $pocoStock, "texto" => $pocoStock === 1 ? "pieza con poco stock" : "piezas con poco stock", "extra" => $agotadas, "url" => APP_URL . "/admin/?mod=partes&filtro=stock_bajo", "boton" => "Reponer"];
if ($nComent) $avisos[] = ["estado" => "info", "icono" => "chat-dots", "n" => $nComent, "texto" => $nComent === 1 ? "comentario en los últimos 7 días" : "comentarios en los últimos 7 días", "extra" => 0, "url" => APP_URL . "/admin/?mod=comentarios", "boton" => "Moderar"];
if (($w["pedidos"] || $w["partes"] || $w["comentarios"]) && !$pendientes && !$pocoStock && !$nComent) $avisos = [["estado" => "ok", "icono" => "check-circle", "n" => null, "texto" => "Todo al día", "extra" => 0, "url" => "", "boton" => ""]];
?>
<h1 class="visually-hidden">Dashboard</h1>
<div class="panel-cabeza dash-cabeza">
  <p class="dash-sub"><span class="num"><?=htmlspecialchars($desdeTexto)?></span> a <span class="num"><?=htmlspecialchars($hastaTexto)?></span> · <?=$todo?"historial completo, sin comparar con otro periodo":"frente al periodo anterior de igual duración"?></p>
  <div class="dash-acciones">
    <?php if($w["ventas"] || $w["pedidos"]): ?>
    <nav class="dash-rango" aria-label="Rango de fechas">
      <?php foreach($rangos as $r): ?><a href="<?=APP_URL?>/admin/?r=<?=$r===0?"todo":(int)$r?>" class="dash-rango-op<?=$r===$dias?" activo":""?>"<?=$r===$dias?' aria-current="true"':""?>><?=htmlspecialchars($rangoTexto[$r])?></a><?php endforeach; ?>
    </nav>
    <?php endif; ?>
    <?php if($botones["venta"]): ?><a class="btn btn-outline-secondary" href="<?=APP_URL?>/admin/?mod=ventas&action=crear"><i aria-hidden="true" class="bi bi-plus-lg me-1"></i>Nueva venta</a><?php endif; ?>
    <?php if($botones["parte"]): ?><a class="btn btn-gold" href="<?=APP_URL?>/admin/?mod=partes&action=crear"><i aria-hidden="true" class="bi bi-plus-lg me-1"></i>Nueva parte</a><?php endif; ?>
  </div>
</div>

<?php if($avisos): ?>
<ul class="dash-avisos dash-rv" style="--i:1" aria-label="Avisos del día">
  <?php foreach($avisos as $a): ?>
  <li class="dash-aviso dash-aviso-<?=htmlspecialchars($a["estado"])?>">
    <i aria-hidden="true" class="bi bi-<?=htmlspecialchars($a["icono"])?> dash-aviso-icono"></i>
    <span class="dash-aviso-texto"><?php if($a["n"] !== null): ?><b class="num"><?=(int)$a["n"]?></b> <?php endif; ?><?=htmlspecialchars($a["texto"])?><?php if($a["extra"]): ?> <span class="dash-aviso-extra">· <b class="num"><?=(int)$a["extra"]?></b> <?=$a["extra"]===1?"agotada":"agotadas"?></span><?php endif; ?></span>
    <?php if($a["url"] !== ""): ?><a class="btn btn-outline-secondary dash-aviso-btn" href="<?=htmlspecialchars($a["url"])?>"><?=htmlspecialchars($a["boton"])?></a><?php endif; ?>
  </li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if($kpis): ?>
<div class="dash-kpis">
  <?php foreach($kpis as $i => $k): ?>
  <div class="dash-card dash-kpi dash-rv" style="--i:<?=$i+2?>">
    <p class="dash-kpi-etiqueta"><?=htmlspecialchars($k["etiqueta"])?></p>
    <p class="dash-kpi-valor num" data-contar="<?=htmlspecialchars($k["valor"])?>" data-formato="<?=$k["dinero"]?"dinero":"entero"?>"><?=htmlspecialchars($k["dinero"]?$money($k["valor"]):(string)(int)$k["valor"])?></p>
    <?php if($k["var"] !== null): ?>
      <p class="dash-var <?=$k["var"]>=0?"sube":"baja"?> num"><i aria-hidden="true" class="bi bi-caret-<?=$k["var"]>=0?"up":"down"?>-fill"></i><?=$k["var"]>=0?"Sube":"Baja"?> <?=htmlspecialchars($pct($k["var"]))?></p>
    <?php endif; ?>
    <p class="dash-kpi-nota"><?=htmlspecialchars($k["nota"])?></p>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if($w["ventas"]): ?>
<div class="dash-fila dash-fila-2">
  <section class="dash-card dash-rv" style="--i:6" aria-labelledby="dgVentas">
    <div class="dash-card-cabeza">
      <div><h2 class="dash-card-titulo" id="dgVentas">Ventas por periodo</h2><p class="dash-card-sub">Pedidos entregados, <?=htmlspecialchars($rangoLargo[$dias])?></p></div>
      <?php if($hayVentas): ?><ul class="dash-leyenda"><li><i class="dash-trazo"></i><?=$todo?"Ventas por mes":"Periodo actual"?></li><?php if(!$todo): ?><li><i class="dash-trazo punteado"></i>Periodo anterior</li><?php endif; ?></ul><?php endif; ?>
    </div>
    <?php if($hayVentas): ?>
    <div class="dash-lienzo"><canvas id="dgLinea" role="img" aria-label="Ventas de <?=htmlspecialchars($rangoLargo[$dias])?>: <?=htmlspecialchars($money($serie["total"]))?>. <?=$serie["totalAnterior"] !== null ? "Periodo anterior: ".htmlspecialchars($money($serie["totalAnterior"])).".":""?>"></canvas></div>
    <table class="visually-hidden"><caption>Ventas por periodo</caption><tr><th scope="col">Periodo</th><th scope="col">Actual</th><th scope="col">Anterior</th></tr>
      <?php foreach($serie["labels"] as $i => $l): ?><tr><td><?=htmlspecialchars($l)?></td><td class="num"><?=htmlspecialchars($money($serie["actual"][$i]))?></td><td class="num"><?=isset($serie["anterior"][$i])?htmlspecialchars($money($serie["anterior"][$i])):"—"?></td></tr><?php endforeach; ?>
    </table>
    <?php else: ?>
    <div class="vacio dash-vacio"><p class="vacio-titulo">Sin ventas en este periodo</p><p class="vacio-texto">Cuando haya pedidos entregados, la línea aparece aquí.<?=$dias!==365?" Prueba con «Año».":""?></p></div>
    <?php endif; ?>
  </section>

  <section class="dash-card dash-rv" style="--i:7" aria-labelledby="dgCat">
    <h2 class="dash-card-titulo" id="dgCat">Ventas por categoría</h2><p class="dash-card-sub">Parte de las ventas del periodo</p>
    <?php if($categorias): $totCat = array_sum(array_column($categorias, "total")); ?>
    <div class="dash-dona"><canvas id="dgDona" role="img" aria-label="Ventas por categoría: <?=htmlspecialchars(implode(", ", array_map(fn($c) => $c["nombre"] . " " . round($c["total"] / max($totCat, 0.01) * 100) . " %", $categorias)))?>."></canvas>
      <div class="dash-dona-centro"><div><b class="num"><?=htmlspecialchars($money($totCat))?></b><span>total</span></div></div></div>
    <ul class="dash-lista-dona">
      <?php foreach($categorias as $c): $pcCat = (int)round($c["total"]/max($totCat,0.01)*100); ?><li><i class="dash-muestra dash-c<?=(int)$c["color"]?>"></i><span class="dash-lista-nombre"><?=htmlspecialchars($c["nombre"])?></span><span class="num dash-lista-valor"><?=(int)$pcCat?> % · <?=htmlspecialchars($money($c["total"]))?></span></li><?php endforeach; ?>
    </ul>
    <?php else: ?>
    <div class="vacio dash-vacio"><p class="vacio-titulo">Sin datos</p><p class="vacio-texto">Aparece cuando haya ventas en el periodo.</p></div>
    <?php endif; ?>
  </section>
</div>
<?php endif; ?>

<?php if($w["pedidos"] || $w["partes"] || $w["comentarios"]): ?>
<div class="dash-fila dash-fila-3">
  <?php if($w["pedidos"]): ?>
  <section class="dash-card dash-rv" style="--i:8" aria-labelledby="dgAtender">
    <div class="dash-card-cabeza"><h2 class="dash-card-titulo" id="dgAtender">Pedidos por atender</h2><a class="dash-ver" href="<?=APP_URL?>/admin/?mod=pedidos">Ver pedidos</a></div>
    <?php if($porAtender): ?>
    <ul class="dash-filas"><?php foreach($porAtender as $p): ?>
      <li><div class="dash-fila-txt"><b>#<?=(int)$p["id"]?> · <?=htmlspecialchars($p["cliente_nombre"])?></b><small class="num"><?=htmlspecialchars(date("d/m/Y H:i", strtotime($p["creado_en"])))?> · <?=htmlspecialchars($money($p["total"]))?></small></div>
        <span class="chip-<?=$p["estado"]==="pendiente"?"warning":"info"?> dash-chip"><?=$p["estado"]==="pendiente"?"Pendiente":"Procesando"?></span>
        <a class="dash-fila-enlace" href="<?=APP_URL?>/admin/?mod=pedidos&action=detalle&id=<?=(int)$p["id"]?>" aria-label="Abrir el pedido #<?=(int)$p["id"]?>">Abrir</a></li>
    <?php endforeach; ?></ul>
    <?php else: ?><div class="vacio dash-vacio"><p class="vacio-titulo">Todo al día</p><p class="vacio-texto">No hay pedidos pendientes ni en proceso.</p></div><?php endif; ?>
  </section>
  <?php endif; ?>
  <?php if($w["partes"]): ?>
  <section class="dash-card dash-rv" style="--i:9" aria-labelledby="dgStock">
    <div class="dash-card-cabeza"><h2 class="dash-card-titulo" id="dgStock">Piezas con poco stock</h2><a class="dash-ver" href="<?=APP_URL?>/admin/?mod=partes&filtro=stock_bajo">Ver las <?=(int)$pocoStock?></a></div>
    <?php if($stockBajo): ?>
    <ul class="dash-filas"><?php foreach($stockBajo as $p): ?>
      <li><div class="dash-fila-txt"><b><?=htmlspecialchars($p["nombre"])?></b><small><?=htmlspecialchars($p["seccion"]??"Sin categoría")?></small></div>
        <span class="chip-<?=(int)$p["cantidad"]===0?"danger":"warning"?> dash-chip num"><?=(int)$p["cantidad"]===0?"Agotada":"Quedan ".(int)$p["cantidad"]?></span>
        <a class="dash-fila-enlace" href="<?=APP_URL?>/admin/?mod=partes&action=editar&id=<?=(int)$p["id"]?>" aria-label="Editar <?=htmlspecialchars($p["nombre"])?>">Editar</a></li>
    <?php endforeach; ?></ul>
    <?php else: ?><div class="vacio dash-vacio"><p class="vacio-titulo">Stock al día</p><p class="vacio-texto">Ninguna pieza activa tiene 5 unidades o menos.</p></div><?php endif; ?>
  </section>
  <?php endif; ?>
  <?php if($w["comentarios"]): ?>
  <section class="dash-card dash-rv" style="--i:10" aria-labelledby="dgCom">
    <div class="dash-card-cabeza"><div><h2 class="dash-card-titulo" id="dgCom">Comentarios recientes</h2><p class="dash-card-sub"><span class="num"><?=(int)$comentariosSemana?></span> en los últimos 7 días</p></div><a class="dash-ver" href="<?=APP_URL?>/admin/?mod=comentarios">Ver todos</a></div>
    <?php if($comentarios): ?>
    <ul class="dash-filas"><?php foreach($comentarios as $c): ?>
      <li><div class="dash-fila-txt"><b><?=htmlspecialchars($c["nombre_visitante"])?><?php if($c["rating"]): ?> · <span class="num"><?=(int)$c["rating"]?> de 5</span><?php endif; ?></b><small><?=htmlspecialchars(mb_strimwidth((string)$c["comentario"], 0, 70, "…"))?> — <?=htmlspecialchars($c["parte"])?></small></div></li>
    <?php endforeach; ?></ul>
    <?php else: ?><div class="vacio dash-vacio"><p class="vacio-titulo">Sin comentarios</p><p class="vacio-texto">Los comentarios de la tienda aparecen aquí.</p></div><?php endif; ?>
  </section>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if($w["ventas"] || $w["pedidos"]): ?>
<div class="dash-fila dash-fila-3">
  <?php
  $barras = [];
  if($w["ventas"]) { $barras[] = ["dgPartes","Piezas más vendidas","Unidades en el periodo","partes",$partes,11]; $barras[] = ["dgMarcas","Marcas más vendidas","Unidades en el periodo","marcas",$marcas,12]; }
  if($w["pedidos"]) { $barras[] = ["dgEstados","Pedidos por estado","Pedidos creados en el periodo","estados",$estados,13]; }
  foreach($barras as [$id,$tit,$sub,$clave,$datos,$ix]):
    $nombres = array_column($datos, "nombre"); $vals = array_column($datos, $clave==="estados"?"n":"unidades");
    $hay = $vals && array_sum($vals) > 0;
  ?>
  <section class="dash-card dash-rv" style="--i:<?=(int)$ix?>" aria-labelledby="<?=$id?>T">
    <h2 class="dash-card-titulo" id="<?=$id?>T"><?=htmlspecialchars($tit)?></h2><p class="dash-card-sub"><?=htmlspecialchars($sub)?></p>
    <?php if($hay): ?>
    <div class="dash-lienzo dash-lienzo-barras"><canvas id="<?=$id?>" role="img" aria-label="<?=htmlspecialchars($tit)?>: <?=htmlspecialchars(implode(", ", array_map(fn($n,$v)=>"$n $v", $nombres, $vals)))?>."></canvas></div>
    <table class="visually-hidden"><caption><?=htmlspecialchars($tit)?></caption><tr><th scope="col">Nombre</th><th scope="col">Cantidad</th></tr>
      <?php foreach($nombres as $i=>$n): ?><tr><td><?=htmlspecialchars($n)?></td><td><?=(int)$vals[$i]?></td></tr><?php endforeach; ?></table>
    <?php else: ?><div class="vacio dash-vacio"><p class="vacio-titulo">Sin datos en este periodo</p><p class="vacio-texto">Aparece cuando haya <?=$clave==="estados"?"pedidos":"ventas"?>.</p></div><?php endif; ?>
  </section>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<script type="application/json" id="dash-datos"><?=json_encode($grafica, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE)?></script>
<script src="<?=Assets::url("js/vendor/chart.umd.min.js")?>"></script>
<script src="<?=Assets::url("js/dashboard.js")?>"></script>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
