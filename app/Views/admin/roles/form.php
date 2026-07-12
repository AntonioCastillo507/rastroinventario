<?php
$edit=!empty($rol); $title=$edit?"Editar Rol":"Nuevo Rol";
include BASE_PATH."/app/Views/layouts/admin_header.php";
$ML=["usuarios"=>"Usuarios","roles"=>"Roles","partes"=>"Partes/Inventario","autos"=>"Autos","secciones"=>"Secciones","ventas"=>"Ventas","comentarios"=>"Comentarios"];
?>
<div class="page-header mb-4">
  <div class="d-flex align-items-center gap-3">
    <a href="<?=APP_URL?>/admin/?mod=roles" class="btn-back"><i class="bi bi-arrow-left"></i></a>
    <h4 class="fw-bold text-white mb-0"><?=$title?></h4>
  </div>
</div>
<?php if($error??""): ?><div class="alert-dark-danger d-flex gap-2 mb-4"><i class="bi bi-exclamation-triangle-fill text-danger"></i><?=htmlspecialchars($error)?></div><?php endif; ?>
<div class="dark-card"><div class="card-body p-4">
<form action="<?=APP_URL?>/admin/?mod=roles&action=<?=$edit?"editar&id=".$id:"crear"?>" method="POST">
  <?=CsrfToken::field()?>
  <div class="row g-4 mb-4">
    <div class="col-md-5">
      <label class="form-label-dark">Nombre del Rol *</label>
      <input type="text" name="nombre" class="form-control dark-input" required value="<?=htmlspecialchars($rol["nombre"]??"")?>">
    </div>
    <div class="col-md-7">
      <label class="form-label-dark">Descripción</label>
      <input type="text" name="descripcion" class="form-control dark-input" value="<?=htmlspecialchars($rol["descripcion"]??"")?>">
    </div>
  </div>
  <h6 class="text-warning mb-3"><i class="bi bi-table me-2"></i>Matriz de Permisos por Módulo</h6>
  <div class="table-responsive">
  <table class="table dark-table mb-4">
    <thead><tr><th>Módulo</th><th class="text-center">Ver</th><th class="text-center">Crear</th><th class="text-center">Editar</th><th class="text-center">Borrar</th></tr></thead>
    <tbody>
    <?php foreach($modulos as $m): $p=$permisos[$m]??[]; ?>
    <tr>
      <td class="fw-semibold text-white"><?=$ML[$m]??$m?></td>
      <?php foreach(["ver","crear","editar","borrar"] as $a): ?>
      <td class="text-center">
        <input type="checkbox" class="dark-check" name="permisos[<?=$m?>][<?=$a?>]" value="1" <?=(!empty($p["puede_".$a]))?"checked":""?>>
      </td>
      <?php endforeach; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <div class="d-flex gap-3">
    <button type="submit" class="btn btn-gold fw-bold px-4"><i class="bi bi-save me-2"></i>Guardar Rol</button>
    <a href="<?=APP_URL?>/admin/?mod=roles" class="btn btn-outline-secondary">Cancelar</a>
  </div>
</form>
</div></div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
