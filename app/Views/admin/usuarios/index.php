<?php $title="Usuarios"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="panel-cabeza">
  <h1 class="panel-titulo">Usuarios del sistema <span class="panel-titulo-cuenta">(<?=(int)$pag->total?>)</span></h1>
  <a href="<?=APP_URL?>/admin/?mod=usuarios&action=crear" class="btn btn-gold"><i aria-hidden="true" class="bi bi-person-plus me-1"></i>Nuevo usuario</a>
</div>
<div class="lista-card">
<table class="lista-tabla">
  <thead><tr><th scope="col">Usuario</th><th scope="col">Roles</th><th scope="col">Estado</th><th scope="col">Creado</th><th scope="col" class="col-acciones">Acciones</th></tr></thead>
  <tbody>
  <?php foreach($usuarios as $u): ?>
  <tr>
    <td class="celda-principal"><span class="celda-titulo"><?=htmlspecialchars($u["nombre"])?></span><span class="celda-sub"><?=htmlspecialchars($u["email"])?></span></td>
    <td data-label="Roles"><span class="chip-info"><?=htmlspecialchars($u["roles"]??"Sin rol")?></span></td>
    <td data-label="Estado"><?=$u["bloqueado"]
        ? "<span class=\"chip-danger\"><i aria-hidden=\"true\" class=\"bi bi-lock-fill me-1\"></i>Bloqueado</span>"
        : ($u["activo"]
            ? "<span class=\"chip-success\"><i aria-hidden=\"true\" class=\"bi bi-check-circle me-1\"></i>Activo</span>"
            : "<span class=\"chip-neutral\">Inactivo</span>")
    ?></td>
    <td data-label="Creado"><span class="celda-sub"><?=date("d/m/Y", strtotime($u["creado_en"]))?></span></td>
    <td class="col-acciones">
      <div class="lista-acciones">
        <a href="<?=APP_URL?>/admin/?mod=usuarios&action=editar&id=<?=$u["id"]?>" class="accion-btn" title="Editar" aria-label="Editar a <?=htmlspecialchars($u["nombre"])?>"><i aria-hidden="true" class="bi bi-pencil-square"></i><span class="accion-texto">Editar</span></a>
        <form method="POST" action="<?=APP_URL?>/admin/?mod=usuarios&action=toggle&id=<?=$u["id"]?>"<?=$u["activo"]?Confirmar::attrs(["titulo"=>"Desactivar a «".$u["nombre"]."»","texto"=>"No podrá iniciar sesión hasta que lo vuelvas a activar.","boton"=>"Desactivar","cancelar"=>"Conservar","peligro"=>true]):""?>>
          <?=CsrfToken::field()?>
          <button type="submit" class="accion-btn<?=$u["activo"]?" accion-peligro":""?>" title="<?=$u["activo"]?"Desactivar":"Activar"?>" aria-label="<?=$u["activo"]?"Desactivar":"Activar"?> a <?=htmlspecialchars($u["nombre"])?>"><i aria-hidden="true" class="bi bi-power"></i><span class="accion-texto"><?=$u["activo"]?"Desactivar":"Activar"?></span></button>
        </form>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($usuarios)): ?><tr class="lista-vacia"><td colspan="5">No hay usuarios.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php $pagBase=APP_URL."/admin/"; include BASE_PATH."/app/Views/layouts/paginacion.php"; ?>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
