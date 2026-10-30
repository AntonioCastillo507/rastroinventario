<?php
$title="Comentarios"; include BASE_PATH."/app/Views/layouts/admin_header.php";
// Cabeceras que ordenan: la activa lleva flecha y el siguiente clic invierte el sentido; las otras empiezan en descendente. Se conserva la página.
[$ordenCol,$ordenDir]=explode("_",$orden);
$ordenUrl=function(string $col) use ($ordenCol,$ordenDir,$pag): string {
    $nuevo=$col."_".($col===$ordenCol&&$ordenDir==="desc"?"asc":"desc");
    $q=["mod"=>"comentarios"]+($nuevo!==ComentarioModel::ORDEN_POR_DEFECTO?["orden"=>$nuevo]:[])+($pag->pagina>1?["page"=>$pag->pagina]:[]);
    return APP_URL."/admin/?".http_build_query($q);
};
$uFecha=$ordenUrl("fecha"); $uRating=$ordenUrl("rating"); $uEstado=$ordenUrl("estado");
$hayAcciones=$puedeModerar||$puedeEditar;
$cols=$hayAcciones?5:4;
?>
<div class="panel-cabeza">
  <h1 class="panel-titulo">Moderación de comentarios <span class="panel-titulo-cuenta">(<?=(int)$pag->total?>)</span></h1>
  <p class="celda-sub">En la portada: <?=(int)$destacadas?> de <?=ComentarioModel::MAX_DESTACADAS?> reseñas destacadas. Solo esas salen en «Opiniones de nuestros clientes».</p>
</div>
<form class="lista-orden-movil" method="GET" id="fAdm">
  <input type="hidden" name="mod" value="comentarios">
  <label for="f-orden" class="form-label-dark">Ordenar por</label>
  <select id="f-orden" name="orden" class="form-select dark-input">
    <option value="fecha_desc" <?=$orden==="fecha_desc"?"selected":""?>>Más recientes</option>
    <option value="fecha_asc" <?=$orden==="fecha_asc"?"selected":""?>>Más antiguos</option>
    <option value="rating_desc" <?=$orden==="rating_desc"?"selected":""?>>Mejor calificados</option>
    <option value="rating_asc" <?=$orden==="rating_asc"?"selected":""?>>Peor calificados</option>
    <?php if($ordenCol==="estado"): ?><option value="<?=htmlspecialchars($orden)?>" selected>Por estado</option><?php endif; ?>
  </select>
</form>
<div class="lista-card">
<table class="lista-tabla lista-comentarios">
  <thead><tr>
    <th scope="col">Comentario</th>
    <th scope="col" aria-sort="<?=$ordenCol==="rating"?($ordenDir==="asc"?"ascending":"descending"):"none"?>"><a class="orden-enlace" href="<?=htmlspecialchars($uRating)?>">Calificación<?php if($ordenCol==="rating"): ?><i aria-hidden="true" class="bi bi-arrow-<?=$ordenDir==="asc"?"up":"down"?>"></i><?php endif; ?></a></th>
    <th scope="col" aria-sort="<?=$ordenCol==="estado"?($ordenDir==="asc"?"ascending":"descending"):"none"?>"><a class="orden-enlace" href="<?=htmlspecialchars($uEstado)?>">Estado<?php if($ordenCol==="estado"): ?><i aria-hidden="true" class="bi bi-arrow-<?=$ordenDir==="asc"?"up":"down"?>"></i><?php endif; ?></a></th>
    <th scope="col" aria-sort="<?=$ordenCol==="fecha"?($ordenDir==="asc"?"ascending":"descending"):"none"?>"><a class="orden-enlace" href="<?=htmlspecialchars($uFecha)?>">Fecha<?php if($ordenCol==="fecha"): ?><i aria-hidden="true" class="bi bi-arrow-<?=$ordenDir==="asc"?"up":"down"?>"></i><?php endif; ?></a></th>
    <?php if($hayAcciones): ?><th scope="col" class="col-acciones">Acciones</th><?php endif; ?>
  </tr></thead>
  <tbody>
  <?php foreach($comentarios as $c):
    $autoTxt=trim(($c["marca"]??"")." ".($c["modelo"]??"")." ".($c["anio"]??""));
    $piezaTxt=($c["parte_nombre"]??"—").($autoTxt!==""?" · ".$autoTxt:"");
    $pieza=(int)($c["parte_activo"]??0)===1;
    $estrellas=(int)($c["rating"]??0);
  ?>
  <tr class="<?=$pieza?"fila-enlazada":""?>">
    <td class="celda-principal celda-texto">
      <span class="celda-titulo"><?=htmlspecialchars($c["nombre_visitante"])?></span><?php if(!empty($c["destacado"])): ?> <span class="chip-info chip-portada">Portada</span><?php endif; ?>
      <?php if($pieza): ?>
      <a class="celda-sub fila-enlace" href="<?=APP_URL?>/?action=detalle&amp;id=<?=(int)$c["parte_id"]?>#comentarios" aria-label="Ver la pieza <?=htmlspecialchars($piezaTxt)?> en la tienda, sección de comentarios"><?=htmlspecialchars($piezaTxt)?></a>
      <?php else: ?>
      <span class="celda-sub"><?=htmlspecialchars($piezaTxt)?> <span class="chip-neutral">Pieza desactivada</span></span>
      <?php endif; ?>
      <span class="comentario-texto"><?=htmlspecialchars($c["comentario"])?></span>
    </td>
    <td data-label="Calificación"><?php if($estrellas>=1&&$estrellas<=5): ?><span class="estrellas" role="img" aria-label="<?=number_format($estrellas)?> de 5 estrellas"><?php for($i=1;$i<=5;$i++): ?><i aria-hidden="true" class="bi bi-star<?=$i<=$estrellas?"-fill":""?>"></i><?php endfor; ?></span><?php else: ?><span class="celda-sub">Sin calificación</span><?php endif; ?></td>
    <td data-label="Estado"><?=$c["publicado"]
      ? "<span class=\"chip-success\"><i aria-hidden=\"true\" class=\"bi bi-eye me-1\"></i>Publicado</span>"
      : "<span class=\"chip-neutral\"><i aria-hidden=\"true\" class=\"bi bi-eye-slash me-1\"></i>Oculto</span>"?></td>
    <td data-label="Fecha"><span class="celda-sub"><?=date("d/m/Y H:i",strtotime($c["creado_en"]))?></span></td>
    <?php if($hayAcciones): ?>
    <td class="col-acciones">
      <div class="lista-acciones">
        <?php if($puedeEditar && ($c["publicado"] || !empty($c["destacado"]))): $dest=!empty($c["destacado"]); ?>
        <form method="POST" action="<?=APP_URL?>/admin/?mod=comentarios&action=<?=$dest?"quitar_portada":"destacar"?>&id=<?=(int)$c["id"]?>">
          <?=CsrfToken::field()?>
          <button type="submit" class="accion-btn" title="<?=$dest?"Quitar de la portada":"Destacar en portada"?>" aria-label="<?=$dest?"Quitar de la portada":"Destacar en portada"?> el comentario de <?=htmlspecialchars($c["nombre_visitante"])?>"><i aria-hidden="true" class="bi bi-star<?=$dest?"-fill":""?>"></i><span class="accion-texto"><?=$dest?"Quitar de la portada":"Destacar en portada"?></span></button>
        </form>
        <?php endif; ?>
        <?php if($puedeModerar): ?>
        <form method="POST" action="<?=APP_URL?>/admin/?mod=comentarios&action=toggle&id=<?=$c["id"]?>"<?=Confirmar::attrs($c["publicado"]
          ? ["titulo"=>"Ocultar el comentario de «".$c["nombre_visitante"]."»","texto"=>"Dejará de verse en la tienda. Un Administrador puede volver a mostrarlo.","boton"=>"Ocultar","cancelar"=>"Conservar","peligro"=>true]
          : ["titulo"=>"Mostrar el comentario de «".$c["nombre_visitante"]."»","texto"=>"Volverá a verse en la página de la pieza.","boton"=>"Mostrar","cancelar"=>"Cancelar"])?>>
          <?=CsrfToken::field()?>
          <button type="submit" class="accion-btn" title="<?=$c["publicado"]?"Ocultar":"Mostrar"?>" aria-label="<?=$c["publicado"]?"Ocultar":"Mostrar"?> el comentario de <?=htmlspecialchars($c["nombre_visitante"])?>"><i aria-hidden="true" class="bi bi-<?=$c["publicado"]?"eye-slash":"eye"?>"></i><span class="accion-texto"><?=$c["publicado"]?"Ocultar":"Mostrar"?></span></button>
        </form>
        <?php endif; ?>
      </div>
    </td>
    <?php endif; ?>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($comentarios)): $vaciaColspan=$cols; $vaciaTitulo="Aún no hay comentarios"; $vaciaTexto="Los comentarios de las piezas aparecerán aquí."; include BASE_PATH."/app/Views/layouts/lista_vacia.php"; endif; ?>
  </tbody>
</table>
</div>
<?php $pagBase=APP_URL."/admin/"; include BASE_PATH."/app/Views/layouts/paginacion.php"; ?>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
