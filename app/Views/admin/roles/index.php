<?php $title="Roles"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="page-header mb-4">
  <div><h1 class="h4 fw-bold text-white mb-0"><i aria-hidden="true" class="bi bi-shield-lock me-2 text-warning"></i>Roles y Permisos</h1></div>
  <a href="<?=APP_URL?>/admin/?mod=roles&action=crear" class="btn btn-gold btn-sm"><i aria-hidden="true" class="bi bi-plus-lg me-1"></i>Nuevo Rol</a>
</div>
<div class="row g-3">
<?php foreach($roles as $r): ?>
<div class="col-md-4">
  <div class="dark-card h-100 p-4">
    <div class="d-flex align-items-start justify-content-between mb-3">
      <div>
        <span class="badge-role"><i aria-hidden="true" class="bi bi-shield-check me-1"></i><?=htmlspecialchars($r["nombre"])?></span>
        <p class="text-secondary small mt-2 mb-0"><?=htmlspecialchars($r["descripcion"]??"")?></p>
      </div>
    </div>
    <a href="<?=APP_URL?>/admin/?mod=roles&action=editar&id=<?=$r["id"]?>" class="btn btn-outline-warning btn-sm w-100">
      <i aria-hidden="true" class="bi bi-pencil me-1"></i>Editar Permisos
    </a>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
