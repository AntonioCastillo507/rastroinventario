<?php $title="Pedidos"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="panel-cabeza">
  <div>
    <h1 class="panel-titulo">Pedidos de la tienda <span class="panel-titulo-cuenta">(<?=(int)$pag->total?>)</span></h1>
    <p class="panel-subtitulo">Compras realizadas desde la tienda pública</p>
  </div>
</div>
<div class="lista-card">
<table class="lista-tabla">
  <thead><tr><th scope="col">Pedido</th><th scope="col">Cliente</th><th scope="col" class="col-num">Total</th><th scope="col">Estado</th><th scope="col">Fecha</th><th scope="col" class="col-acciones">Acciones</th></tr></thead>
  <tbody>
  <?php foreach($pedidos as $p): ?>
  <tr>
    <td class="celda-principal"><span class="celda-titulo num">#<?=(int)$p["id"]?></span></td>
    <td data-label="Cliente"><span class="celda-titulo"><?=htmlspecialchars($p["cliente_nombre"])?></span><span class="celda-sub"><?=htmlspecialchars($p["cliente_email"])?></span></td>
    <td class="col-num" data-label="Total"><span class="celda-precio text-precio num">$<?=number_format($p["total"],2)?></span></td>
    <td data-label="Estado"><span class="estado-<?=$p["estado"]?> chip-<?=match($p["estado"]){"pendiente"=>"warning","procesando"=>"info","entregado"=>"success",default=>"danger"} ?>"><?=ucfirst($p["estado"])?></span></td>
    <td data-label="Fecha"><span class="celda-sub"><?=date("d/m/Y H:i",strtotime($p["creado_en"]))?></span></td>
    <td class="col-acciones">
      <div class="lista-acciones">
        <a href="<?=APP_URL?>/admin/?mod=pedidos&action=detalle&id=<?=$p["id"]?>" class="accion-btn" title="Ver" aria-label="Ver el pedido #<?=(int)$p["id"]?>"><i aria-hidden="true" class="bi bi-eye"></i><span class="accion-texto">Ver</span></a>
      </div>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($pedidos)): $vaciaColspan=6; $vaciaTitulo="Aún no hay pedidos"; $vaciaTexto="Cuando alguien compre en la tienda, su pedido aparecerá aquí."; include BASE_PATH."/app/Views/layouts/lista_vacia.php"; endif; ?>
  </tbody>
</table>
</div>
<?php $pagBase=APP_URL."/admin/"; include BASE_PATH."/app/Views/layouts/paginacion.php"; ?>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
