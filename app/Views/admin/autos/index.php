<?php $title="Autos"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="panel-cabeza">
  <h1 class="panel-titulo">Catálogo de autos <span class="panel-titulo-cuenta">(<?=count($autos)?>)</span></h1>
  <a href="<?=APP_URL?>/admin/?mod=autos&action=crear" class="btn btn-gold"><i aria-hidden="true" class="bi bi-plus-lg me-1"></i>Nuevo auto</a>
</div>
<div class="lista-card">
<table class="lista-tabla">
  <thead><tr><th scope="col">Auto</th><th scope="col" class="col-num">Año</th><th scope="col">Estado</th><th scope="col" class="col-acciones">Acciones</th></tr></thead>
  <tbody>
  <?php foreach($autos as $a): ?>
  <tr>
    <td class="celda-principal"><span class="celda-titulo"><?=htmlspecialchars($a["marca"])?></span><span class="celda-sub"><?=htmlspecialchars($a["modelo"])?></span></td>
    <td class="col-num" data-label="Año"><span class="num"><?=(int)$a["anio"]?></span></td>
    <td data-label="Estado"><?=$a["activo"]?"<span class=\"chip-success\">Activo</span>":"<span class=\"chip-neutral\">Inactivo</span>"?></td>
    <td class="col-acciones">
      <div class="lista-acciones">
        <a href="<?=APP_URL?>/admin/?mod=autos&action=editar&id=<?=$a["id"]?>" class="accion-btn" title="Editar" aria-label="Editar <?=htmlspecialchars($a["marca"]." ".$a["modelo"]." ".$a["anio"])?>"><i aria-hidden="true" class="bi bi-pencil-square"></i><span class="accion-texto">Editar</span></a>
        <form method="POST" action="<?=APP_URL?>/admin/?mod=autos&action=eliminar&id=<?=$a["id"]?>"<?=Confirmar::attrs(["titulo"=>"Desactivar «".$a["marca"]." ".$a["modelo"]." ".$a["anio"]."»","texto"=>"El auto dejará de mostrarse como activo.","boton"=>"Desactivar","cancelar"=>"Conservar","peligro"=>true])?>>
          <?=CsrfToken::field()?>
          <button type="submit" class="accion-btn accion-peligro" title="Desactivar" aria-label="Desactivar <?=htmlspecialchars($a["marca"]." ".$a["modelo"]." ".$a["anio"])?>"><i aria-hidden="true" class="bi bi-trash"></i><span class="accion-texto">Desactivar</span></button>
        </form>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($autos)): $vaciaColspan=4; $vaciaTitulo="Aún no hay autos"; $vaciaTexto="Registra los autos para asociarles piezas."; $vaciaAccion=[APP_URL."/admin/?mod=autos&action=crear","Nuevo auto"]; include BASE_PATH."/app/Views/layouts/lista_vacia.php"; endif; ?>
  </tbody>
</table>
</div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
