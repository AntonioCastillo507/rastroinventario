<?php
$title="Pedido #".$pedido["id"]; include BASE_PATH."/app/Views/layouts/admin_header.php";
$chip=match($pedido["estado"]){"pendiente"=>"warning","procesando"=>"info","entregado"=>"success",default=>"danger"};
$siguientes=PedidoModel::transicionesPermitidas($pedido["estado"]);
$monto=Itbms::dePedido($pedido);
$tel=preg_replace('/[^0-9+]/',"",(string)$pedido["cliente_telefono"]);
$pasos=["pendiente"=>"Pendiente","procesando"=>"Procesando","entregado"=>"Entregado"];
$cancelado=$pedido["estado"]==="cancelado";
$pasoActual=array_search($pedido["estado"],array_keys($pasos),true);
?>
<div class="panel-cabeza pedido-cabeza">
  <div>
    <a href="<?=APP_URL?>/admin/?mod=pedidos" class="enlace-volver"><i aria-hidden="true" class="bi bi-arrow-left"></i>Volver a pedidos</a>
    <div class="pedido-titulo">
      <h1 class="panel-titulo">Pedido #<?=(int)$pedido["id"]?></h1>
      <span class="estado-<?=$pedido["estado"]?> chip-<?=htmlspecialchars($chip)?> pedido-estado"><?=ucfirst($pedido["estado"])?></span>
    </div>
    <p class="panel-subtitulo"><?=date("d/m/Y H:i",strtotime($pedido["creado_en"]))?> — Panamá</p>
  </div>
  <div class="pedido-acciones">
    <?php if($siguientes): ?>
    <form class="pedido-estado-form" action="<?=APP_URL?>/admin/?mod=pedidos&action=cambiarEstado&id=<?=$pedido["id"]?>" method="POST"<?=Confirmar::attrsPorCampo("estado",["entregado"=>["titulo"=>"Marcar el pedido #".$pedido["id"]." como entregado","texto"=>"Entregado es definitivo: no se podrá cambiar después.","boton"=>"Marcar como entregado","cancelar"=>"Volver"],"cancelado"=>["titulo"=>"Cancelar el pedido #".$pedido["id"],"texto"=>"Se devolverá el stock del pedido.","boton"=>"Cancelar el pedido","cancelar"=>"Conservar el pedido","peligro"=>true]])?>>
      <?=CsrfToken::field()?>
      <label for="f-estado" class="visually-hidden">Nuevo estado del pedido</label>
      <select id="f-estado" name="estado" class="form-select dark-input" required>
        <option value="" disabled selected>Actual: <?=ucfirst($pedido["estado"])?></option>
        <?php foreach($siguientes as $e): ?>
        <option value="<?=$e?>"><?=ucfirst($e)?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-gold fw-bold">Cambiar estado</button>
    </form>
    <?php endif; ?>
    <a href="<?=APP_URL?>/admin/?mod=factura&action=generar&id=<?=$pedido["id"]?>" class="btn btn-outline-secondary" target="_blank" rel="noopener noreferrer">
      <i aria-hidden="true" class="bi bi-file-earmark-pdf me-2"></i>Factura PDF<span class="visually-hidden"> (PDF, se abre en una pestaña nueva)</span>
    </a>
  </div>
</div>
<?php if(!$siguientes): ?>
<p class="pedido-nota"><i aria-hidden="true" class="bi bi-lock"></i><?=$cancelado?"Este pedido fue cancelado.":"Este pedido ya fue entregado y no se puede cambiar."?></p>
<?php endif; ?>
<ol class="pedido-linea" aria-label="Avance del pedido">
  <?php $i=0; foreach($pasos as $clave=>$texto): $hecho=!$cancelado&&$pasoActual!==false&&$i<=$pasoActual; $actual=!$cancelado&&$pedido["estado"]===$clave; ?>
  <li class="pedido-paso<?=$hecho?" hecho":""?><?=$actual?" actual":""?>"<?=$actual?' aria-current="step"':""?>><span class="pedido-paso-num num"><?=$hecho?'<i aria-hidden="true" class="bi bi-check-lg"></i>':($i+1)?></span><span><?=htmlspecialchars($texto)?><?=$hecho&&!$actual?'<span class="visually-hidden"> (completado)</span>':""?></span></li>
  <?php $i++; endforeach; ?>
  <?php if($cancelado): ?><li class="pedido-paso cancelado actual" aria-current="step"><span class="pedido-paso-num"><i aria-hidden="true" class="bi bi-x-lg"></i></span><span>Cancelado</span></li><?php endif; ?>
</ol>
<div class="pedido-grid">
  <div class="pedido-principal">
    <section class="lista-card pedido-card" aria-labelledby="t-piezas">
      <h2 id="t-piezas" class="pedido-card-titulo">Piezas</h2>
      <ul class="pedido-lineas">
        <?php foreach($pedido["items"] as $item):
          $fotoParte=["imagen_grande"=>null,"thumbnail"=>$item["thumbnail"]??null];
          $tieneFoto=ImageHelper::fuente(null,$item["thumbnail"]??null,"thumbnails")!=="";
          $auto=trim(($item["marca"]??"")." ".($item["modelo"]??""));
        ?>
        <li class="pedido-linea-item">
          <?php if($tieneFoto): $fotoTam="thumbnails"; $fotoClase="foto-64"; $fotoAlt=""; include BASE_PATH."/app/Views/layouts/foto_pieza.php"; endif; ?>
          <div class="pedido-linea-texto">
            <span class="celda-titulo"><?=htmlspecialchars($item["parte_nombre"])?></span>
            <?php if($auto!==""): ?><span class="celda-sub"><?=htmlspecialchars($auto)?></span><?php endif; ?>
            <span class="celda-sub"><span class="num"><?=(int)$item["cantidad"]?></span> × <span class="num">$<?=number_format($item["precio_unitario"],2)?></span></span>
          </div>
          <span class="pedido-linea-subtotal text-precio num">$<?=number_format($item["precio_unitario"]*$item["cantidad"],2)?></span>
        </li>
        <?php endforeach; ?>
      </ul>
      <dl class="pedido-resumen">
        <div><dt>Subtotal</dt><dd class="num">$<?=number_format($monto["subtotal"],2)?></dd></div>
        <div><dt><?=htmlspecialchars(Marca::impuestoEtiqueta())?></dt><dd class="num">$<?=number_format($monto["itbms"],2)?></dd></div>
        <div class="pedido-resumen-total"><dt>Total</dt><dd class="text-precio num">$<?=number_format($monto["total"],2)?></dd></div>
      </dl>
    </section>
  </div>
  <aside class="pedido-lateral">
    <section class="lista-card pedido-card" aria-labelledby="t-cliente">
      <h2 id="t-cliente" class="pedido-card-titulo">Cliente</h2>
      <dl class="pedido-datos">
        <div><dt>Nombre</dt><dd><?=htmlspecialchars($pedido["cliente_nombre"])?></dd></div>
        <div><dt>Correo</dt><dd><a href="mailto:<?=htmlspecialchars($pedido["cliente_email"])?>"><?=htmlspecialchars($pedido["cliente_email"])?></a></dd></div>
        <div><dt>Teléfono</dt><dd><a href="tel:<?=htmlspecialchars($tel)?>" class="num"><?=htmlspecialchars($pedido["cliente_telefono"])?></a></dd></div>
        <div><dt>Dirección de entrega</dt><dd><?=htmlspecialchars($pedido["cliente_direccion"])?></dd></div>
        <div><dt>Cuenta</dt><dd><?=$pedido["usuario_id"]?"<span class=\"chip-success\">Usuario #".(int)$pedido["usuario_id"]."</span>":"<span class=\"chip-neutral\">Sin cuenta</span>"?></dd></div>
      </dl>
    </section>
    <?php if(trim((string)$pedido["cliente_notas"])!==""): ?>
    <section class="lista-card pedido-card" aria-labelledby="t-mensaje">
      <h2 id="t-mensaje" class="pedido-card-titulo">Mensaje del cliente</h2>
      <p class="pedido-mensaje"><?=nl2br(htmlspecialchars($pedido["cliente_notas"]))?></p>
    </section>
    <?php endif; ?>
    <?php if($pedido["firma_digital"]): ?><div class="firma-badge"><i aria-hidden="true" class="bi bi-shield-check text-success"></i>Pedido firmado digitalmente</div><?php endif; ?>
  </aside>
</div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
