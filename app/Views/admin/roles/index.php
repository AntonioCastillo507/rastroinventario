<?php $title="Roles"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="panel-cabeza">
  <h1 class="panel-titulo">Roles y permisos <span class="panel-titulo-cuenta">(<?=count($roles)?>)</span></h1>
  <a href="<?=APP_URL?>/admin/?mod=roles&action=crear" class="btn btn-gold"><i aria-hidden="true" class="bi bi-plus-lg me-1"></i>Nuevo rol</a>
</div>
<div class="lista-card">
<table class="lista-tabla">
  <thead><tr><th scope="col">Rol</th><th scope="col">Descripción</th><th scope="col" class="col-acciones">Acciones</th></tr></thead>
  <tbody>
  <?php foreach($roles as $r): ?>
  <tr>
    <td class="celda-principal"><span class="celda-titulo"><?=htmlspecialchars($r["nombre"])?></span></td>
    <td class="celda-texto" data-label="Descripción"><?=htmlspecialchars($r["descripcion"]??"")?></td>
    <td class="col-acciones">
      <div class="lista-acciones">
        <a href="<?=APP_URL?>/admin/?mod=roles&action=editar&id=<?=$r["id"]?>" class="accion-btn" title="Editar permisos" aria-label="Editar permisos del rol <?=htmlspecialchars($r["nombre"])?>"><i aria-hidden="true" class="bi bi-pencil-square"></i><span class="accion-texto">Editar permisos</span></a>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($roles)): ?><tr class="lista-vacia"><td colspan="3">No hay roles.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
