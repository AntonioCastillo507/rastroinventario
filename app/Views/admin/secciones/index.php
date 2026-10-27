<?php $title="Secciones"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="panel-cabeza">
  <h1 class="panel-titulo">Secciones del almacén <span class="panel-titulo-cuenta">(<?=count($secciones)?>)</span></h1>
  <a href="<?=APP_URL?>/admin/?mod=secciones&action=crear" class="btn btn-gold"><i aria-hidden="true" class="bi bi-plus-lg me-1"></i>Nueva sección</a>
</div>
<div class="lista-card">
<table class="lista-tabla">
  <thead><tr><th scope="col">Sección</th><th scope="col">Estado</th><th scope="col" class="col-acciones">Acciones</th></tr></thead>
  <tbody>
  <?php foreach($secciones as $s): ?>
  <tr>
    <td class="celda-principal celda-texto"><span class="celda-titulo"><?=htmlspecialchars($s["nombre"])?></span><span class="celda-sub"><?=htmlspecialchars($s["descripcion"]??"")?></span></td>
    <td data-label="Estado"><?=$s["activo"]?"<span class=\"chip-success\">Activa</span>":"<span class=\"chip-neutral\">Inactiva</span>"?></td>
    <td class="col-acciones">
      <div class="lista-acciones">
        <a href="<?=APP_URL?>/admin/?mod=partes&seccion=<?=$s["id"]?>" class="accion-btn" title="Ver piezas" aria-label="Ver piezas de <?=htmlspecialchars($s["nombre"])?>"><i aria-hidden="true" class="bi bi-box-seam"></i><span class="accion-texto">Ver piezas</span></a>
        <a href="<?=APP_URL?>/admin/?mod=secciones&action=editar&id=<?=$s["id"]?>" class="accion-btn" title="Editar" aria-label="Editar la sección <?=htmlspecialchars($s["nombre"])?>"><i aria-hidden="true" class="bi bi-pencil-square"></i><span class="accion-texto">Editar</span></a>
        <form method="POST" action="<?=APP_URL?>/admin/?mod=secciones&action=eliminar&id=<?=$s["id"]?>"<?=Confirmar::attrs(["titulo"=>"Desactivar «".$s["nombre"]."»","texto"=>"La sección dejará de mostrarse en la tienda.","boton"=>"Desactivar","cancelar"=>"Conservar","peligro"=>true])?>>
          <?=CsrfToken::field()?>
          <button type="submit" class="accion-btn accion-peligro" title="Desactivar" aria-label="Desactivar la sección <?=htmlspecialchars($s["nombre"])?>"><i aria-hidden="true" class="bi bi-trash"></i><span class="accion-texto">Desactivar</span></button>
        </form>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($secciones)): $vaciaColspan=3; $vaciaTitulo="Aún no hay secciones"; $vaciaTexto="Las secciones organizan las piezas y sirven de categorías en la tienda."; $vaciaAccion=[APP_URL."/admin/?mod=secciones&action=crear","Nueva sección"]; include BASE_PATH."/app/Views/layouts/lista_vacia.php"; endif; ?>
  </tbody>
</table>
</div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
