<?php $title="Seguridad"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="panel-cabeza">
  <h1 class="panel-titulo">Bitácora de accesos <span class="panel-titulo-cuenta">(<?=(int)$pag->total?>)</span></h1>
</div>
<div class="lista-card">
<table class="lista-tabla">
  <thead><tr><th scope="col">Correo</th><th scope="col">IP</th><th scope="col">Resultado</th><th scope="col">Fecha</th></tr></thead>
  <tbody>
  <?php foreach($logs as $l): ?>
  <tr>
    <td class="celda-principal"><span class="celda-titulo"><?=htmlspecialchars($l["email_intento"])?></span><span class="celda-sub"><?=htmlspecialchars((string)($l["nombre"] ?? "Cuenta no encontrada"))?></span></td>
    <td data-label="IP"><code class="num"><?=htmlspecialchars($l["ip"])?></code></td>
    <td data-label="Resultado"><?=$l["exitoso"]
      ? "<span class=\"chip-success\"><i aria-hidden=\"true\" class=\"bi bi-check-circle me-1\"></i>Correcto</span>"
      : "<span class=\"chip-danger\"><i aria-hidden=\"true\" class=\"bi bi-x-circle me-1\"></i>Fallido</span>"?></td>
    <td data-label="Fecha"><span class="celda-sub num"><?=htmlspecialchars((string)$l["fecha"])?></span></td>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($logs)): $vaciaColspan=4; $vaciaTitulo="Aún no hay accesos registrados"; $vaciaTexto="Cada inicio de sesión, correcto o fallido, aparece aquí."; include BASE_PATH."/app/Views/layouts/lista_vacia.php"; endif; ?>
  </tbody>
</table>
</div>
<?php $pagBase=APP_URL."/admin/"; include BASE_PATH."/app/Views/layouts/paginacion.php"; ?>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
