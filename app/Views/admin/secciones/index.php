<?php $title="Secciones"; include BASE_PATH."/app/Views/layouts/admin_header.php"; ?>
<div class="page-header mb-4">
  <div><h1 class="h4 fw-bold text-white mb-0"><i aria-hidden="true" class="bi bi-diagram-3 me-2 text-warning"></i>Secciones del Almacén</h1></div>
  <a href="<?=APP_URL?>/admin/?mod=secciones&action=crear" class="btn btn-gold btn-sm"><i aria-hidden="true" class="bi bi-plus-lg me-1"></i>Nueva Sección</a>
</div>
<div class="row g-3">
<?php foreach($secciones as $s): ?>
<div class="col-md-4 col-sm-6">
  <div class="dark-card h-100 p-4">
    <div class="d-flex align-items-center gap-3 mb-3">
      <!-- Ícono clickeable → filtra partes por esta sección -->
      <a href="<?=APP_URL?>/admin/?mod=partes&seccion=<?=$s["id"]?>" title="Ver partes de <?=htmlspecialchars($s["nombre"])?>" aria-label="Ver partes de <?=htmlspecialchars($s["nombre"])?>"
         style="width:44px;height:44px;background:var(--accent-dim);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;color:var(--accent);font-size:1.25rem;flex-shrink:0;text-decoration:none;transition:background .15s"
         onmouseover="this.style.background='var(--accent)';this.style.color='#000'"
         onmouseout="this.style.background='var(--accent-dim)';this.style.color='var(--accent)'">
        <i aria-hidden="true" class="bi bi-archive-fill"></i>
      </a>
      <div>
        <h2 class="h6 fw-bold text-white mb-0"><?=htmlspecialchars($s["nombre"])?></h2>
        <span class="text-secondary small"><?=$s["activo"]?"Activa":"Inactiva"?></span>
      </div>
    </div>
    <p class="text-secondary small mb-3"><?=htmlspecialchars($s["descripcion"]??"")?></p>
    <div class="d-flex gap-2">
      <a href="<?=APP_URL?>/admin/?mod=partes&seccion=<?=$s["id"]?>" class="btn btn-outline-secondary btn-sm flex-fill">
        <i aria-hidden="true" class="bi bi-box-seam me-1"></i>Ver piezas
      </a>
      <a href="<?=APP_URL?>/admin/?mod=secciones&action=editar&id=<?=$s["id"]?>" class="btn btn-outline-warning btn-sm flex-fill">
        <i aria-hidden="true" class="bi bi-pencil me-1"></i>Editar
      </a>
      <form method="POST" action="<?=APP_URL?>/admin/?mod=secciones&action=eliminar&id=<?=$s["id"]?>" class="d-inline" onsubmit="return confirm('¿Desactivar?')">
        <?=CsrfToken::field()?>
        <button type="submit" class="btn btn-outline-danger btn-sm" aria-label="Desactivar la sección <?=htmlspecialchars($s["nombre"])?>"><i aria-hidden="true" class="bi bi-trash"></i></button>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php include BASE_PATH."/app/Views/layouts/admin_footer.php"; ?>
