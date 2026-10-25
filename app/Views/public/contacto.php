<?php
$title=Marca::get("contacto.titulo"); $navActivo="contacto";
include BASE_PATH."/app/Views/layouts/public_header.php";
$telefono = (string)Marca::get("telefono");
$correo   = (string)Marca::get("correo");
$horario  = Marca::get("horario", []);
$redes    = Marca::get("redes", []);
?>
<div class="container-xl py-5">
  <h1 class="h2 fw-bold text-white mb-2"><?=Marca::h("contacto.titulo")?></h1>
  <?php if(Marca::get("contacto.intro")!==""): ?><p class="text-secondary mb-4"><?=Marca::h("contacto.intro")?></p><?php endif; ?>

  <div class="row g-4">
    <div class="col-md-6">
      <section class="info-card h-100" aria-labelledby="c-datos">
        <h2 id="c-datos" class="h5 fw-semibold text-white mb-3">Cómo encontrarnos</h2>
        <ul class="list-unstyled mb-0 contacto-lista">
          <?php if(Marca::get("direccion")!==""): ?>
          <li><i aria-hidden="true" class="bi bi-geo-alt"></i><div><div class="contacto-etiqueta">Dirección</div><?=Marca::h("direccion")?></div></li>
          <?php endif; ?>
          <?php if($telefono!==""): ?>
          <li><i aria-hidden="true" class="bi bi-telephone"></i><div><div class="contacto-etiqueta">Teléfono</div><a class="contacto-enlace" href="tel:<?=htmlspecialchars(preg_replace('/[^\d+]/','',$telefono))?>"><?=Marca::h("telefono")?></a></div></li>
          <?php endif; ?>
          <?php if($correo!==""): ?>
          <li><i aria-hidden="true" class="bi bi-envelope"></i><div><div class="contacto-etiqueta">Correo</div><a class="contacto-enlace" href="mailto:<?=htmlspecialchars($correo)?>"><?=Marca::h("correo")?></a></div></li>
          <?php endif; ?>
        </ul>
      </section>
    </div>
    <div class="col-md-6">
      <section class="info-card h-100" aria-labelledby="c-horario">
        <h2 id="c-horario" class="h5 fw-semibold text-white mb-3">Horario</h2>
        <?php if($horario): ?>
        <ul class="list-unstyled mb-0 contacto-lista">
          <?php foreach($horario as $h): ?>
          <li><i aria-hidden="true" class="bi <?=!empty($h["abierto"])?"bi-clock":"bi-x-circle"?>"></i><div><div class="contacto-etiqueta"><?=htmlspecialchars($h["dia"]??"")?></div><?=htmlspecialchars($h["horas"]??"")?></div></li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="text-secondary mb-0">Consulta el horario por teléfono o por correo.</p>
        <?php endif; ?>
      </section>
    </div>
  </div>

  <?php if($redes): ?>
  <section class="info-card mt-4" aria-labelledby="c-redes">
    <h2 id="c-redes" class="h5 fw-semibold text-white mb-3">Síguenos</h2>
    <ul class="list-inline mb-0">
      <?php foreach($redes as $r): if(empty($r["url"])||!preg_match('~^https?://~i',$r["url"])) continue; ?>
      <li class="list-inline-item me-3"><a class="contacto-enlace" href="<?=htmlspecialchars($r["url"])?>" target="_blank" rel="noopener noreferrer"><i aria-hidden="true" class="bi bi-<?=htmlspecialchars($r["icono"]??"link-45deg")?> me-2"></i><?=htmlspecialchars($r["nombre"]??"")?><span class="visually-hidden"> (se abre en otra pestaña)</span></a></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>
</div>

<?php include BASE_PATH."/app/Views/layouts/public_footer.php"; ?>
