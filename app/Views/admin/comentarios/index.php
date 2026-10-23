<?php $title="Comentarios"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="page-header mb-4">
  <div><h4 class="fw-bold text-white mb-0"><i class="bi bi-chat-dots me-2 text-warning"></i>Moderación de Comentarios</h4></div>
</div>
<div class="dark-card"><div class="table-responsive">
<table class="table dark-table mb-0">
  <thead><tr><th>#</th><th>Parte</th><th>Visitante</th><th>Comentario</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr></thead>
  <tbody>
  <?php foreach($comentarios as $c): ?>
  <tr>
    <td class="small text-secondary"><?=$c["id"]?></td>
    <td class="small fw-semibold text-warning"><?=htmlspecialchars($c["parte_nombre"]??"—")?></td>
    <td class="text-white"><?=htmlspecialchars($c["nombre_visitante"])?></td>
    <td class="small text-secondary" style="max-width:280px"><?=htmlspecialchars(mb_substr($c["comentario"],0,90)).(mb_strlen($c["comentario"])>90?"…":"")?></td>
    <td><?=$c["publicado"]
      ? "<span class=\"chip-success\"><i class=\"bi bi-eye me-1\"></i>Publicado</span>"
      : "<span class=\"chip-warning\"><i class=\"bi bi-hourglass me-1\"></i>Pendiente</span>"?></td>
    <td class="small text-secondary"><?=date("d/m/Y H:i",strtotime($c["creado_en"]))?></td>
    <td>
      <form method="POST" action="<?=APP_URL?>/admin/?mod=comentarios&action=toggle&id=<?=$c["id"]?>" class="d-inline">
        <?=CsrfToken::field()?>
        <button type="submit" class="icon-btn" title="<?=$c["publicado"]?"Ocultar":"Publicar"?>">
          <i class="bi bi-<?=$c["publicado"]?"eye-slash text-warning":"check-circle text-success"?>"></i>
        </button>
      </form>
      <form method="POST" action="<?=APP_URL?>/admin/?mod=comentarios&action=eliminar&id=<?=$c["id"]?>" class="d-inline" onsubmit="return confirm('¿Eliminar este comentario?')">
        <?=CsrfToken::field()?>
        <button type="submit" class="icon-btn" title="Eliminar"><i class="bi bi-trash text-danger"></i></button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if(empty($comentarios)): ?><tr><td colspan="7" class="text-center text-secondary py-4">No hay comentarios.</td></tr><?php endif; ?>
  </tbody>
</table>
</div></div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
