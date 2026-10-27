<?php $title="Inventario"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="panel-cabeza">
  <h1 class="panel-titulo">Inventario de partes <span class="panel-titulo-cuenta">(<?=(int)$pag->total?>)</span></h1>
  <a href="<?=APP_URL?>/admin/?mod=partes&action=crear" class="btn btn-gold"><i aria-hidden="true" class="bi bi-plus-lg me-1"></i>Nueva parte</a>
</div>
<!-- Filtros admin -->
<form class="lista-filtros" method="GET" id="fAdm">
  <input type="hidden" name="mod" value="partes">
  <div class="input-group lista-filtros-buscar">
    <input type="search" name="q" class="form-control dark-input" placeholder="Buscar nombre, marca..." aria-label="Buscar parte por nombre o marca" value="<?=htmlspecialchars($q??"")?>">
    <button type="submit" class="btn btn-gold" aria-label="Buscar"><i aria-hidden="true" class="bi bi-search"></i></button>
  </div>
  <select name="filtro" class="form-select dark-input" aria-label="Filtrar por existencias">
    <option value="all"       <?=($filtro??"all")==="all"       ?"selected":""?>>Todas</option>
    <option value="con_stock" <?=($filtro??"")==="con_stock" ?"selected":""?>>Con stock</option>
    <option value="stock_bajo"<?=($filtro??"")==="stock_bajo"?"selected":""?>>Stock bajo (≤5)</option>
    <option value="agotado"   <?=($filtro??"")==="agotado"   ?"selected":""?>>Agotadas</option>
  </select>
  <select name="orden" class="form-select dark-input" aria-label="Ordenar por">
    <option value="recientes"  <?=($orden??"recientes")==="recientes"  ?"selected":""?>>Más recientes</option>
    <option value="precio_asc" <?=($orden??"")==="precio_asc" ?"selected":""?>>Precio ↑</option>
    <option value="precio_desc"<?=($orden??"")==="precio_desc"?"selected":""?>>Precio ↓</option>
    <option value="stock_asc"  <?=($orden??"")==="stock_asc"  ?"selected":""?>>Stock ↑</option>
    <option value="stock_desc" <?=($orden??"")==="stock_desc" ?"selected":""?>>Stock ↓</option>
    <option value="nombre"     <?=($orden??"")==="nombre"     ?"selected":""?>>Nombre A–Z</option>
    <option value="mas_vendidas"<?=($orden??"")==="mas_vendidas"?"selected":""?>>Más vendidas</option>
  </select>
  <?php if(($q??"")!==""||($filtro??"all")!=="all"||($orden??"recientes")!=="recientes"): ?>
  <a href="<?=APP_URL?>/admin/?mod=partes" class="lista-filtros-limpiar">Limpiar filtros</a>
  <?php endif; ?>
</form>
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
          <span class="celda-titulo"><?=htmlspecialchars($p["nombre"])?></span>
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
  <?php if(empty($partes)): ?><tr class="lista-vacia"><td colspan="4">Sin resultados con esos filtros.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php $pagBase=APP_URL."/admin/"; include BASE_PATH."/app/Views/layouts/paginacion.php"; ?>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
