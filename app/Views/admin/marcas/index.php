<?php $title="Marcas"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="panel-cabeza">
  <h1 class="panel-titulo">Marcas de autos <span class="panel-titulo-cuenta">(<?=count($marcas)?>)</span></h1>
  <a href="<?=APP_URL?>/admin/?mod=marcas&action=crear" class="btn btn-gold"><i aria-hidden="true" class="bi bi-plus-lg me-1"></i>Nueva marca</a>
</div>
<div class="lista-card">
<table class="lista-tabla">
  <thead><tr><th scope="col">Marca</th><th scope="col" class="col-num">Piezas</th><th scope="col">Estado</th><th scope="col" class="col-acciones">Acciones</th></tr></thead>
  <tbody>
  <?php foreach($marcas as $m): $nom=htmlspecialchars($m["nombre"]); ?>
  <tr>
    <td class="celda-principal">
      <span class="marca-celda"><?php $mlNombre=$m["nombre"]; $mlLogo=$m["logo"]; $mlClase=""; include BASE_PATH."/app/Views/layouts/marca_logo.php"; ?>
      <span class="celda-texto"><span class="celda-titulo"><?=$nom?></span><span class="celda-sub"><?=$m["logo"]?"Con logo":"Monograma"?> · <?=(int)$m["autos"]?> <?=(int)$m["autos"]===1?"auto":"autos"?></span></span></span>
    </td>
    <td class="col-num" data-label="Piezas"><span class="num"><?=(int)$m["piezas"]?></span></td>
    <td data-label="Estado"><?=$m["activo"]?"<span class=\"chip-success\">Activa</span>":"<span class=\"chip-neutral\">Inactiva</span>"?></td>
    <td class="col-acciones">
      <div class="lista-acciones">
        <a href="<?=APP_URL?>/admin/?mod=marcas&action=editar&id=<?=(int)$m["id"]?>" class="accion-btn" title="Editar" aria-label="Editar la marca <?=$nom?>"><i aria-hidden="true" class="bi bi-pencil-square"></i><span class="accion-texto">Editar</span></a>
        <?php if($m["logo"]): ?>
        <form method="POST" action="<?=APP_URL?>/admin/?mod=marcas&action=quitar_logo&id=<?=(int)$m["id"]?>"<?=Confirmar::attrs(["titulo"=>"Quitar el logo de «".$m["nombre"]."»","texto"=>"La marca usará su monograma hasta que subas otro logo.","boton"=>"Quitar logo","cancelar"=>"Conservar","peligro"=>true])?>>
          <?=CsrfToken::field()?>
          <button type="submit" class="accion-btn accion-peligro" title="Quitar logo" aria-label="Quitar el logo de <?=$nom?>"><i aria-hidden="true" class="bi bi-image"></i><span class="accion-texto">Quitar logo</span></button>
        </form>
        <?php endif; ?>
        <?php if($m["activo"]): ?>
        <form method="POST" action="<?=APP_URL?>/admin/?mod=marcas&action=activar&id=<?=(int)$m["id"]?>"<?=Confirmar::attrs(["titulo"=>"Desactivar «".$m["nombre"]."»","texto"=>"La marca dejará de mostrarse en la portada. Sus autos y piezas no cambian.","boton"=>"Desactivar","cancelar"=>"Conservar","peligro"=>true])?>>
          <?=CsrfToken::field()?><input type="hidden" name="activo" value="0">
          <button type="submit" class="accion-btn accion-peligro" title="Desactivar" aria-label="Desactivar la marca <?=$nom?>"><i aria-hidden="true" class="bi bi-eye-slash"></i><span class="accion-texto">Desactivar</span></button>
        </form>
        <?php else: ?>
        <form method="POST" action="<?=APP_URL?>/admin/?mod=marcas&action=activar&id=<?=(int)$m["id"]?>">
          <?=CsrfToken::field()?><input type="hidden" name="activo" value="1">
          <button type="submit" class="accion-btn" title="Activar" aria-label="Activar la marca <?=$nom?>"><i aria-hidden="true" class="bi bi-eye"></i><span class="accion-texto">Activar</span></button>
        </form>
        <?php endif; ?>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($marcas)): $vaciaColspan=4; $vaciaTitulo="Aún no hay marcas"; $vaciaTexto="Las marcas organizan los autos y aparecen con su logo en la portada de la tienda."; $vaciaAccion=[APP_URL."/admin/?mod=marcas&action=crear","Nueva marca"]; include BASE_PATH."/app/Views/layouts/lista_vacia.php"; endif; ?>
  </tbody>
</table>
</div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
