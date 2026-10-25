<?php $title="Autos"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="page-header mb-4">
  <div><h1 class="h4 fw-bold text-white mb-0"><i aria-hidden="true" class="bi bi-car-front me-2 text-warning"></i>Catálogo de Autos</h1></div>
  <a href="<?=APP_URL?>/admin/?mod=autos&action=crear" class="btn btn-gold btn-sm"><i aria-hidden="true" class="bi bi-plus-lg me-1"></i>Nuevo Auto</a>
</div>
<div class="dark-card"><div class="table-responsive">
<table class="table dark-table mb-0">
  <thead><tr><th>#</th><th>Marca</th><th>Modelo</th><th>Año</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody>
  <?php foreach($autos as $a): ?>
  <tr>
    <td class="small text-secondary"><?=$a["id"]?></td>
    <td class="fw-semibold text-white"><?=htmlspecialchars($a["marca"])?></td>
    <td class="text-secondary"><?=htmlspecialchars($a["modelo"])?></td>
    <td class="text-secondary"><?=$a["anio"]?></td>
    <td><?=$a["activo"]?"<span class=\"chip-success\">Activo</span>":"<span class=\"chip-neutral\">Inactivo</span>"?></td>
    <td>
      <a href="<?=APP_URL?>/admin/?mod=autos&action=editar&id=<?=$a["id"]?>" class="icon-btn" title="Editar" aria-label="Editar <?=htmlspecialchars($a["marca"]." ".$a["modelo"]." ".$a["anio"])?>"><i aria-hidden="true" class="bi bi-pencil-square text-warning"></i></a>
      <form method="POST" action="<?=APP_URL?>/admin/?mod=autos&action=eliminar&id=<?=$a["id"]?>" class="d-inline" onsubmit="return confirm('¿Desactivar este auto?')">
        <?=CsrfToken::field()?>
        <button type="submit" class="icon-btn" title="Desactivar" aria-label="Desactivar <?=htmlspecialchars($a["marca"]." ".$a["modelo"]." ".$a["anio"])?>"><i aria-hidden="true" class="bi bi-trash text-danger"></i></button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div></div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
