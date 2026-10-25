<?php $title="Pedido #".$pedido["id"]; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="page-header mb-4">
  <div class="d-flex align-items-center gap-3">
    <a href="<?=APP_URL?>/admin/?mod=pedidos" class="btn-back" aria-label="Volver"><i aria-hidden="true" class="bi bi-arrow-left"></i></a>
    <div>
      <h1 class="h4 fw-bold text-white mb-0">Pedido #<?=str_pad($pedido["id"],6,"0",STR_PAD_LEFT)?></h1>
      <p class="text-secondary small mb-0"><?=date("d/m/Y H:i",strtotime($pedido["creado_en"]))?> — Panamá</p>
    </div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?=APP_URL?>/admin/?mod=factura&action=generar&id=<?=$pedido["id"]?>" class="btn btn-gold btn-sm" target="_blank">
      <i aria-hidden="true" class="bi bi-file-earmark-pdf me-1"></i>Descargar Factura PDF
    </a>
    <span class="chip-<?=match($pedido["estado"]){"pendiente"=>"warning","procesando"=>"info","entregado"=>"success",default=>"danger"}?> fs-6"><?=ucfirst($pedido["estado"])?></span>
  </div>
</div>
<div class="row g-4">
  <div class="col-md-8">
    <div class="dark-card mb-4">
      <div class="dark-card-header"><i aria-hidden="true" class="bi bi-box-seam me-2"></i>Artículos del Pedido</div>
      <div class="table-responsive"><table class="table dark-table mb-0">
        <thead><tr><th>Pieza</th><th>Precio Unit.</th><th>Cant.</th><th>Subtotal</th></tr></thead>
        <tbody>
        <?php foreach($pedido["items"] as $item): ?>
        <tr>
          <td class="fw-semibold text-white"><?=htmlspecialchars($item["parte_nombre"])?></td>
          <td class="text-secondary num">$<?=number_format($item["precio_unitario"],2)?></td>
          <td class="num"><?=$item["cantidad"]?></td>
          <td class="fw-bold text-success num">$<?=number_format($item["precio_unitario"]*$item["cantidad"],2)?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <div class="p-3 border-top" style="border-color:var(--border)!important">
        <?php $monto=Itbms::dePedido($pedido); ?>
        <div class="d-flex justify-content-end gap-4 text-secondary small mb-1"><span>Subtotal:</span><span class="num">$<?=number_format($monto["subtotal"],2)?></span></div>
        <div class="d-flex justify-content-end gap-4 text-secondary small mb-1"><span><?=htmlspecialchars(Marca::impuestoEtiqueta())?>:</span><span class="num">$<?=number_format($monto["itbms"],2)?></span></div>
        <div class="d-flex justify-content-end gap-4 fw-bold text-white"><span>TOTAL:</span><span class="text-success num">$<?=number_format($monto["total"],2)?></span></div>
      </div>
    </div>
    <div class="dark-card">
      <div class="dark-card-header"><i aria-hidden="true" class="bi bi-person me-2"></i>Datos del Cliente</div>
      <div class="card-body p-4">
        <div class="row g-3">
          <div class="col-md-6"><div class="text-secondary small mb-1">Nombre</div><div class="text-white fw-semibold"><?=htmlspecialchars($pedido["cliente_nombre"])?></div></div>
          <div class="col-md-6"><div class="text-secondary small mb-1">Teléfono</div><div class="text-white fw-semibold"><?=htmlspecialchars($pedido["cliente_telefono"])?></div></div>
          <div class="col-md-6"><div class="text-secondary small mb-1">Correo</div><div class="text-white fw-semibold"><?=htmlspecialchars($pedido["cliente_email"])?></div></div>
          <div class="col-md-6"><div class="text-secondary small mb-1">Cuenta</div><div><?=$pedido["usuario_id"]?"<span class=\"chip-success\">Usuario #".$pedido["usuario_id"]."</span>":"<span class=\"chip-neutral\">Sin cuenta</span>"?></div></div>
          <div class="col-12"><div class="text-secondary small mb-1">Dirección de entrega</div><div class="text-white"><?=htmlspecialchars($pedido["cliente_direccion"])?></div></div>
          <?php if($pedido["cliente_notas"]): ?><div class="col-12"><div class="text-secondary small mb-1">Notas</div><div class="text-secondary"><?=htmlspecialchars($pedido["cliente_notas"])?></div></div><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="dark-card mb-4">
      <div class="dark-card-header"><i aria-hidden="true" class="bi bi-arrow-repeat me-2"></i>Actualizar Estado</div>
      <div class="card-body p-4">
        <?php $siguientes=PedidoModel::transicionesPermitidas($pedido["estado"]); ?>
        <?php if($siguientes): ?>
        <form action="<?=APP_URL?>/admin/?mod=pedidos&action=cambiarEstado&id=<?=$pedido["id"]?>" method="POST"
              onsubmit="var e=this.estado.value;return e==='entregado'?confirm('Entregado es definitivo: no se podrá cambiar después. ¿Continuar?'):(e==='cancelado'?confirm('Se devolverá el stock del pedido. ¿Cancelar el pedido?'):true);">
          <?=CsrfToken::field()?>
          <label for="f-estado" class="form-label-dark">Estado del pedido</label>
          <select id="f-estado" name="estado" class="form-select dark-input mb-3" required>
            <option value="" disabled selected>Actual: <?=ucfirst($pedido["estado"])?></option>
            <?php foreach($siguientes as $e): ?>
            <option value="<?=$e?>"><?=ucfirst($e)?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-gold fw-bold w-100"><i aria-hidden="true" class="bi bi-save me-2"></i>Guardar Estado</button>
        </form>
        <?php else: ?>
        <div class="text-secondary small"><i aria-hidden="true" class="bi bi-lock me-1"></i>Este pedido ya fue entregado y no se puede cambiar.</div>
        <?php endif; ?>
        <div class="mt-3 text-secondary small"><i aria-hidden="true" class="bi bi-info-circle me-1"></i>
          <strong>Entregado</strong> registra la venta y es definitivo. <strong>Cancelado</strong> devuelve el stock; si se reabre, se descuenta de nuevo (si hay stock).
        </div>
      </div>
    </div>
    <a href="<?=APP_URL?>/admin/?mod=factura&action=generar&id=<?=$pedido["id"]?>" class="btn btn-outline-warning w-100 mb-2" target="_blank">
      <i aria-hidden="true" class="bi bi-file-earmark-pdf me-2"></i>Ver Factura PDF
    </a>
    <?php if($pedido["firma_digital"]): ?><div class="firma-badge mt-2"><i aria-hidden="true" class="bi bi-shield-check text-success"></i>Pedido firmado digitalmente</div><?php endif; ?>
  </div>
</div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
