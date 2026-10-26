<?php $title="Inventario"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="page-header mb-4">
  <div><h1 class="h4 fw-bold text-white mb-0"><i aria-hidden="true" class="bi bi-box-seam me-2 text-warning"></i>Inventario de Partes</h1></div>
  <a href="<?=APP_URL?>/admin/?mod=partes&action=crear" class="btn btn-gold btn-sm"><i aria-hidden="true" class="bi bi-plus-lg me-1"></i>Nueva Parte</a>
</div>
<!-- Filtros admin -->
<form class="mb-4" method="GET" id="fAdm">
  <input type="hidden" name="mod" value="partes">
  <div class="row g-2 align-items-end">
    <div class="col-md-4"><input type="search" name="q" class="form-control dark-input form-control-sm" placeholder="Buscar nombre, marca..." aria-label="Buscar parte por nombre o marca" value="<?=htmlspecialchars($q??"")?>"></div>
    <div class="col-md-3">
      <select name="filtro" class="form-select dark-input form-select-sm" aria-label="Filtrar por existencias" onchange="this.form.submit()">
        <option value="all"       <?=($filtro??"all")==="all"       ?"selected":""?>>Todas</option>
        <option value="con_stock" <?=($filtro??"")==="con_stock" ?"selected":""?>>Con stock</option>
        <option value="stock_bajo"<?=($filtro??"")==="stock_bajo"?"selected":""?>>Stock bajo (≤5)</option>
        <option value="agotado"   <?=($filtro??"")==="agotado"   ?"selected":""?>>Agotadas</option>
      </select>
    </div>
    <div class="col-md-3">
      <select name="orden" class="form-select dark-input form-select-sm" aria-label="Ordenar por" onchange="this.form.submit()">
        <option value="recientes"  <?=($orden??"recientes")==="recientes"  ?"selected":""?>>Más recientes</option>
        <option value="precio_asc" <?=($orden??"")==="precio_asc" ?"selected":""?>>Precio ↑</option>
        <option value="precio_desc"<?=($orden??"")==="precio_desc"?"selected":""?>>Precio ↓</option>
        <option value="stock_asc"  <?=($orden??"")==="stock_asc"  ?"selected":""?>>Stock ↑</option>
        <option value="stock_desc" <?=($orden??"")==="stock_desc" ?"selected":""?>>Stock ↓</option>
        <option value="nombre"     <?=($orden??"")==="nombre"     ?"selected":""?>>Nombre A–Z</option>
        <option value="mas_vendidas"<?=($orden??"")==="mas_vendidas"?"selected":""?>>Más vendidas</option>
      </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
      <button type="submit" class="btn btn-gold btn-sm flex-fill" aria-label="Buscar"><i aria-hidden="true" class="bi bi-search"></i></button>
      <a href="<?=APP_URL?>/admin/?mod=partes" class="btn btn-outline-secondary btn-sm" aria-label="Limpiar filtros"><i aria-hidden="true" class="bi bi-x"></i></a>
    </div>
  </div>
</form>
<div class="dark-card"><div class="table-responsive">
<table class="table dark-table mb-0">
  <thead><tr><th>#</th><th>Img</th><th>Nombre</th><th>Auto</th><th>Sección</th><th>Precio</th><th>Stock</th><th>Firma</th><th>Acciones</th></tr></thead>
  <tbody>
  <?php foreach($partes as $p): ?>
  <tr>
    <td class="small text-secondary"><?=$p["id"]?></td>
    <td><?php $fotoParte=$p; $fotoTam="thumbnails"; $fotoClase="foto-48"; $fotoAlt=""; include BASE_PATH."/app/Views/layouts/foto_pieza.php"; ?></td>
    <td class="fw-semibold text-white"><?=htmlspecialchars($p["nombre"])?></td>
    <td class="small text-secondary"><?=htmlspecialchars(($p["marca"]??"")." ".($p["modelo"]??""))?></td>
    <td class="small text-secondary"><?=htmlspecialchars($p["seccion"]??"—")?></td>
    <td class="fw-bold text-precio num">$<?=number_format($p["precio"],2)?></td>
    <td><?php $c=$p["cantidad"]; if($c>5) echo"<span class=\"chip-success\">$c</span>"; elseif($c>0) echo"<span class=\"chip-warning\">$c</span>"; else echo"<span class=\"chip-danger\">Agotada</span>"; ?></td>
    <td><?=$p["firma_digital"]?"<i aria-hidden=\"true\" class=\"bi bi-shield-check text-success fs-5\"></i><span class=\"visually-hidden\">Firmada</span>":"<i aria-hidden=\"true\" class=\"bi bi-shield-x text-secondary\"></i><span class=\"visually-hidden\">Sin firma</span>"?></td>
    <td>
      <a href="<?=APP_URL?>/admin/?mod=partes&action=editar&id=<?=$p["id"]?>" class="icon-btn" title="Editar" aria-label="Editar <?=htmlspecialchars($p["nombre"])?>"><i aria-hidden="true" class="bi bi-pencil-square text-warning"></i></a>
      <form method="POST" action="<?=APP_URL?>/admin/?mod=partes&action=eliminar&id=<?=$p["id"]?>" class="d-inline" onsubmit="return confirm('¿Desactivar?')">
        <?=CsrfToken::field()?>
        <button type="submit" class="icon-btn" title="Desactivar" aria-label="Desactivar <?=htmlspecialchars($p["nombre"])?>"><i aria-hidden="true" class="bi bi-trash text-danger"></i></button>
      </form>
      <a href="<?=APP_URL?>/?action=detalle&id=<?=$p["id"]?>" class="icon-btn" title="Ver público" aria-label="Ver <?=htmlspecialchars($p["nombre"])?> en la tienda"><i aria-hidden="true" class="bi bi-eye icon-info"></i></a>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($partes)): ?><tr><td colspan="9" class="text-center text-secondary py-4">Sin resultados con esos filtros.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<?php $pagBase=APP_URL."/admin/"; include BASE_PATH."/app/Views/layouts/paginacion.php"; ?>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
