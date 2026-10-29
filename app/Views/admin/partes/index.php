<?php $title="Inventario"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="panel-cabeza">
  <h1 class="panel-titulo">Inventario de partes <span class="panel-titulo-cuenta">(<?=(int)$pag->total?>)</span></h1>
  <a href="<?=APP_URL?>/admin/?mod=partes&action=crear" class="btn btn-gold"><i aria-hidden="true" class="bi bi-plus-lg me-1"></i>Nueva parte</a>
</div>
<!-- Filtros admin -->
<form class="lista-filtros lista-filtros-partes" method="GET" id="fAdm">
  <input type="hidden" name="mod" value="partes">
  <div class="input-group lista-filtros-buscar">
    <input type="search" name="q" class="form-control dark-input" placeholder="Buscar nombre, marca..." aria-label="Buscar parte por nombre o marca" value="<?=htmlspecialchars($q??"")?>">
    <button type="submit" class="btn btn-gold" aria-label="Buscar"><i aria-hidden="true" class="bi bi-search"></i></button>
  </div>
  <select name="seccion" class="form-select dark-input" aria-label="Filtrar por sección">
    <option value="">Todas las secciones</option>
    <?php foreach(($secciones??[]) as $sec): ?>
    <option value="<?=(int)$sec["id"]?>" <?=(int)($seccion??0)===(int)$sec["id"]?"selected":""?>><?=htmlspecialchars($sec["nombre"])?></option>
    <?php endforeach; ?>
  </select>
  <select name="filtro" class="form-select dark-input" aria-label="Filtrar por estado de stock">
    <?php foreach(ParteController::FILTROS_STOCK as $fk=>$ft): ?>
    <option value="<?=htmlspecialchars($fk)?>" <?=($filtro??"all")===$fk?"selected":""?>><?=htmlspecialchars($fk==="all"?"Todo el stock":$ft)?></option>
    <?php endforeach; ?>
  </select>
  <select name="orden" class="form-select dark-input" aria-label="Ordenar por">
    <?php foreach(ParteController::ORDENES as $ok=>$ot): ?>
    <option value="<?=htmlspecialchars($ok)?>" <?=($orden??"recientes")===$ok?"selected":""?>><?=htmlspecialchars($ot)?></option>
    <?php endforeach; ?>
  </select>
</form>
<?php if(!empty($fichas)): ?>
<ul class="lista-fichas" aria-label="Filtros activos">
  <?php foreach($fichas as [$ftexto,$furl]): ?>
  <li><a class="lista-ficha" href="<?=htmlspecialchars($furl)?>" aria-label="Quitar filtro: <?=htmlspecialchars($ftexto)?>"><span><?=htmlspecialchars($ftexto)?></span><i aria-hidden="true" class="bi bi-x-lg"></i></a></li>
  <?php endforeach; ?>
  <li><a href="<?=APP_URL?>/admin/?mod=partes" class="lista-filtros-limpiar">Limpiar filtros</a></li>
</ul>
<?php endif; ?>
<div class="lista-card">
<table class="lista-tabla">
  <thead><tr><th scope="col">Pieza</th><th scope="col" class="col-num">Precio</th><th scope="col">Stock</th><th scope="col" class="col-acciones">Acciones</th></tr></thead>
  <tbody>
  <?php foreach($partes as $p): ?>
  <tr>
    <td class="celda-principal">
      <div class="celda-con-foto">
        <?php $fotoParte=$p; $fotoTam="thumbnails"; $fotoClase="foto-48"; $fotoAlt=""; include BASE_PATH."/app/Views/layouts/foto_pieza.php"; ?>
        <span>
          <span class="celda-titulo"><?=htmlspecialchars($p["nombre"])?></span><?php if(!empty($p["destacado"])): ?> <span class="chip-info chip-portada">Portada</span><?php endif; ?>
          <span class="celda-sub"><?=htmlspecialchars(trim(($p["marca"]??"")." ".($p["modelo"]??"")))?> · <?=htmlspecialchars($p["seccion"]??"Sin sección")?></span>
        </span>
      </div>
    </td>
    <td class="col-num" data-label="Precio"><span class="celda-precio text-precio num">$<?=number_format($p["precio"],2)?></span></td>
    <td data-label="Stock"><?php $c=$p["cantidad"]; if($c>5) echo"<span class=\"chip-success\">$c</span>"; elseif($c>0) echo"<span class=\"chip-warning\">$c</span>"; else echo"<span class=\"chip-danger\">Agotada</span>"; ?></td>
    <td class="col-acciones">
      <div class="lista-acciones">
        <a href="<?=APP_URL?>/admin/?mod=partes&action=editar&id=<?=$p["id"]?>" class="accion-btn" title="Editar" aria-label="Editar <?=htmlspecialchars($p["nombre"])?>"><i aria-hidden="true" class="bi bi-pencil-square"></i><span class="accion-texto">Editar</span></a>
        <form method="POST" action="<?=APP_URL?>/admin/?mod=partes&action=eliminar&id=<?=$p["id"]?>"<?=Confirmar::attrs(["titulo"=>"Desactivar «".$p["nombre"]."»","texto"=>"La pieza dejará de mostrarse en la tienda. Los pedidos y ventas anteriores se conservan.","boton"=>"Desactivar","cancelar"=>"Conservar","peligro"=>true])?>>
          <?=CsrfToken::field()?>
          <button type="submit" class="accion-btn accion-peligro" title="Desactivar" aria-label="Desactivar <?=htmlspecialchars($p["nombre"])?>"><i aria-hidden="true" class="bi bi-trash"></i><span class="accion-texto">Desactivar</span></button>
        </form>
        <a href="<?=APP_URL?>/?action=detalle&id=<?=$p["id"]?>" class="accion-btn" title="Ver público" aria-label="Ver <?=htmlspecialchars($p["nombre"])?> en la tienda"><i aria-hidden="true" class="bi bi-eye"></i><span class="accion-texto">Ver</span></a>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($partes)): $conFiltros=!empty($fichas); $vaciaColspan=4; $vaciaTitulo=$conFiltros?"Sin resultados":"Aún no hay partes"; $vaciaTexto=$conFiltros?"Prueba con otro nombre o marca, o quita los filtros.":"Agrega la primera pieza del inventario."; $vaciaAccion=$conFiltros?[APP_URL."/admin/?mod=partes","Limpiar filtros"]:[APP_URL."/admin/?mod=partes&action=crear","Nueva parte"]; include BASE_PATH."/app/Views/layouts/lista_vacia.php"; endif; ?>
  </tbody>
</table></div>
<?php $pagBase=APP_URL."/admin/"; include BASE_PATH."/app/Views/layouts/paginacion.php"; ?>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
