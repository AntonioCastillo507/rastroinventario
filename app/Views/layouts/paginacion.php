<?php
// Fragmento de paginación. Variables: $pag (Paginador), $pagParams (filtros activos) y $pagBase (URL sin query).
if (!isset($pag) || !$pag->hayVarias()) return;
$pagParams = $pagParams ?? [];
$pagBase   = $pagBase ?? "";
?>
<nav class="paginacion mt-3" aria-label="Paginación">
  <div class="small text-secondary mb-2">Mostrando <?=(int)$pag->desdeFila()?>–<?=(int)$pag->hastaFila()?> de <?=(int)$pag->total?></div>
  <ul class="pagination pagination-sm flex-wrap mb-0">
    <?php if($pag->pagina > 1): ?>
    <li class="page-item"><a class="page-link" rel="prev" aria-label="Página anterior" href="<?=htmlspecialchars($pag->url($pag->pagina - 1, $pagParams, $pagBase))?>">&laquo; Anterior</a></li>
    <?php else: ?>
    <li class="page-item disabled"><span class="page-link">&laquo; Anterior</span></li>
    <?php endif; ?>
    <?php foreach($pag->ventana() as $n): ?>
      <?php if($n === null): ?>
    <li class="page-item disabled"><span class="page-link">…</span></li>
      <?php elseif($n === $pag->pagina): ?>
    <li class="page-item active" aria-current="page"><span class="page-link"><?=(int)$n?></span></li>
      <?php else: ?>
    <li class="page-item"><a class="page-link" aria-label="Página <?=(int)$n?>" href="<?=htmlspecialchars($pag->url($n, $pagParams, $pagBase))?>"><?=(int)$n?></a></li>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if($pag->pagina < $pag->paginas): ?>
    <li class="page-item"><a class="page-link" rel="next" aria-label="Página siguiente" href="<?=htmlspecialchars($pag->url($pag->pagina + 1, $pagParams, $pagBase))?>">Siguiente &raquo;</a></li>
    <?php else: ?>
    <li class="page-item disabled"><span class="page-link">Siguiente &raquo;</span></li>
    <?php endif; ?>
  </ul>
</nav>
