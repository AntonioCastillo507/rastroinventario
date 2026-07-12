<?php $title="Usuarios"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="page-header mb-4">
  <div><h4 class="fw-bold text-white mb-0"><i class="bi bi-people me-2 text-warning"></i>Usuarios del Sistema</h4></div>
  <a href="<?=APP_URL?>/admin/?mod=usuarios&action=crear" class="btn btn-gold btn-sm"><i class="bi bi-person-plus me-1"></i>Nuevo Usuario</a>
</div>
<div class="dark-card">
<div class="table-responsive">
<table class="table dark-table mb-0">
  <thead><tr><th>#</th><th>Nombre</th><th>Email</th><th>Roles</th><th>Estado</th><th>Creado</th><th>Acciones</th></tr></thead>
  <tbody>
  <?php foreach($usuarios as $u): ?>
  <tr>
    <td class="small text-secondary"><?=$u["id"]?></td>
    <td class="fw-semibold text-white"><?=htmlspecialchars($u["nombre"])?></td>
    <td class="text-secondary small"><?=htmlspecialchars($u["email"])?></td>
    <td><span class="chip-info"><?=htmlspecialchars($u["roles"]??"Sin rol")?></span></td>
    <td><?=$u["bloqueado"]
        ? "<span class=\"chip-danger\"><i class=\"bi bi-lock-fill me-1\"></i>Bloqueado</span>"
        : ($u["activo"]
            ? "<span class=\"chip-success\"><i class=\"bi bi-check-circle me-1\"></i>Activo</span>"
            : "<span class=\"chip-neutral\">Inactivo</span>")
    ?></td>
    <td class="small text-secondary"><?=date("d/m/Y", strtotime($u["creado_en"]))?></td>
    <td>
      <a href="<?=APP_URL?>/admin/?mod=usuarios&action=editar&id=<?=$u["id"]?>" class="icon-btn" title="Editar"><i class="bi bi-pencil-square text-warning"></i></a>
      <a href="<?=APP_URL?>/admin/?mod=usuarios&action=toggle&id=<?=$u["id"]?>" class="icon-btn" title="<?=$u["activo"]?"Desactivar":"Activar"?>">
        <i class="bi bi-power <?=$u["activo"]?"text-danger":"text-success"?>"></i>
      </a>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
</div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
