<?php $title="Comentarios"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="panel-cabeza">
  <h1 class="panel-titulo">Moderación de comentarios <span class="panel-titulo-cuenta">(<?=(int)$pag->total?>)</span></h1>
</div>
<div class="lista-card">
<table class="lista-tabla">
  <thead><tr><th scope="col">Comentario</th><th scope="col">Estado</th><th scope="col">Fecha</th><th scope="col" class="col-acciones">Acciones</th></tr></thead>
  <tbody>
  <?php foreach($comentarios as $c): ?>
  <tr>
    <td class="celda-principal celda-texto"><span class="celda-titulo"><?=htmlspecialchars($c["nombre_visitante"])?></span><span class="celda-sub">Sobre <?=htmlspecialchars($c["parte_nombre"]??"—")?></span><span><?=htmlspecialchars(mb_substr($c["comentario"],0,90)).(mb_strlen($c["comentario"])>90?"…":"")?></span></td>
    <td data-label="Estado"><?=$c["publicado"]
      ? "<span class=\"chip-success\"><i aria-hidden=\"true\" class=\"bi bi-eye me-1\"></i>Publicado</span>"
      : "<span class=\"chip-warning\"><i aria-hidden=\"true\" class=\"bi bi-hourglass me-1\"></i>Pendiente</span>"?></td>
    <td data-label="Fecha"><span class="celda-sub"><?=date("d/m/Y H:i",strtotime($c["creado_en"]))?></span></td>
    <td class="col-acciones">
      <div class="lista-acciones">
        <form method="POST" action="<?=APP_URL?>/admin/?mod=comentarios&action=toggle&id=<?=$c["id"]?>">
          <?=CsrfToken::field()?>
          <button type="submit" class="accion-btn" title="<?=$c["publicado"]?"Ocultar":"Publicar"?>" aria-label="<?=$c["publicado"]?"Ocultar":"Publicar"?> el comentario de <?=htmlspecialchars($c["nombre_visitante"])?>"><i aria-hidden="true" class="bi bi-<?=$c["publicado"]?"eye-slash":"check-circle"?>"></i><span class="accion-texto"><?=$c["publicado"]?"Ocultar":"Publicar"?></span></button>
        </form>
        <form method="POST" action="<?=APP_URL?>/admin/?mod=comentarios&action=eliminar&id=<?=$c["id"]?>"<?=Confirmar::attrs(["titulo"=>"Eliminar el comentario de «".$c["nombre_visitante"]."»","texto"=>"Se borra para siempre y no se puede deshacer.","boton"=>"Eliminar","cancelar"=>"Conservar","peligro"=>true])?>>
          <?=CsrfToken::field()?>
          <button type="submit" class="accion-btn accion-peligro" title="Eliminar" aria-label="Eliminar el comentario de <?=htmlspecialchars($c["nombre_visitante"])?>"><i aria-hidden="true" class="bi bi-trash"></i><span class="accion-texto">Eliminar</span></button>
        </form>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($comentarios)): $vaciaColspan=4; $vaciaTitulo="Aún no hay comentarios"; $vaciaTexto="Los comentarios de las piezas aparecerán aquí para moderarlos."; include BASE_PATH."/app/Views/layouts/lista_vacia.php"; endif; ?>
  </tbody>
</table>
</div>
<?php $pagBase=APP_URL."/admin/"; include BASE_PATH."/app/Views/layouts/paginacion.php"; ?>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
