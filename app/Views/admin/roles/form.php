<?php
$edit=!empty($rol); $title=$edit?"Editar Rol":"Nuevo Rol";
include BASE_PATH."/app/Views/layouts/admin_header.php";
$ML=["usuarios"=>"Usuarios","roles"=>"Roles","partes"=>"Partes/Inventario","autos"=>"Autos","secciones"=>"Secciones","ventas"=>"Ventas","comentarios"=>"Comentarios","pedidos"=>"Pedidos","seguridad"=>"Seguridad"];
$MS=["usuarios"=>"Cuentas del personal y de clientes","roles"=>"Roles y esta matriz","partes"=>"Piezas, fotos y stock","ventas"=>"Registro de ventas","comentarios"=>"Moderación de reseñas","pedidos"=>"Pedidos de la tienda y su estado","seguridad"=>"Bitácora de accesos"];
?>
<div class="panel-cabeza">
  <div>
    <a href="<?=APP_URL?>/admin/?mod=roles" class="enlace-volver"><i aria-hidden="true" class="bi bi-arrow-left"></i>Volver a roles</a>
    <h1 class="panel-titulo"><?=$title?></h1>
  </div>
</div>
<?php if($error??""): ?><div role="alert" class="alert alert-danger d-flex align-items-center gap-2 mb-4"><i aria-hidden="true" class="bi bi-exclamation-triangle-fill"></i><span><?=htmlspecialchars($error)?></span></div><?php endif; ?>
<div class="form-panel"><div class="form-card">
<form action="<?=APP_URL?>/admin/?mod=roles&action=<?=$edit?"editar&id=".$id:"crear"?>" method="POST">
  <?=CsrfToken::field()?>
  <div class="row g-4 mb-4">
    <div class="col-md-5">
      <label for="f-nombre" class="form-label-dark">Nombre del Rol *</label>
      <input id="f-nombre" type="text" name="nombre" class="form-control dark-input" required value="<?=htmlspecialchars($rol["nombre"]??"")?>">
    </div>
    <div class="col-md-7">
      <label for="f-descripcion" class="form-label-dark">Descripción</label>
      <input id="f-descripcion" type="text" name="descripcion" class="form-control dark-input" value="<?=htmlspecialchars($rol["descripcion"]??"")?>">
    </div>
  </div>
  <h2 class="form-subtitulo">Matriz de permisos por módulo</h2>
  <div class="matriz-permisos">
  <table class="matriz-tabla">
    <thead><tr><th scope="col">Módulo</th><th scope="col">Ver</th><th scope="col">Crear</th><th scope="col">Editar</th><th scope="col">Borrar</th></tr></thead>
    <tbody>
    <?php foreach($modulos as $m): $p=$permisos[$m]??[]; ?>
    <tr>
      <th scope="row"><?=htmlspecialchars($ML[$m]??$m)?><?php if(!empty($MS[$m])): ?><span class="celda-sub"><?=htmlspecialchars($MS[$m])?></span><?php endif; ?></th>
      <?php foreach(["ver","crear","editar","borrar"] as $a): ?>
      <td>
        <input type="checkbox" class="dark-check" name="permisos[<?=$m?>][<?=$a?>]" value="1" aria-label="<?=["ver"=>"Ver","crear"=>"Crear","editar"=>"Editar","borrar"=>"Borrar"][$a]?> en <?=htmlspecialchars($ML[$m]??$m)?>" <?=(!empty($p["puede_".$a]))?"checked":""?>>
      </td>
      <?php endforeach; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <div class="form-acciones">
    <button type="submit" class="btn btn-gold fw-bold px-4"><i aria-hidden="true" class="bi bi-save me-2"></i>Guardar Rol</button>
    <a href="<?=APP_URL?>/admin/?mod=roles" class="btn btn-outline-secondary">Cancelar</a>
  </div>
</form>
</div></div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
