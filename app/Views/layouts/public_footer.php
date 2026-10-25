</main>
<footer class="public-footer pt-5 pb-4 mt-5" id="contacto">
  <div class="container-xl">
    <div class="row g-4">
      <div class="col-md-4">
        <div class="fw-bold text-white mb-2 fs-5"><?=Marca::h("nombre")?></div>
        <p class="text-secondary small"><?=Marca::h("descripcion")?></p>
      </div>
      <div class="col-md-4">
        <h2 class="h6 text-warning fw-bold mb-3">Contáctanos</h2>
        <div class="contact-item"><div class="contact-icon"><i aria-hidden="true" class="bi bi-geo-alt-fill"></i></div><span class="text-secondary small"><?=Marca::h("direccion")?></span></div>
        <div class="contact-item"><div class="contact-icon"><i aria-hidden="true" class="bi bi-telephone-fill"></i></div><span class="text-secondary small"><?=Marca::h("telefono")?></span></div>
        <div class="contact-item"><div class="contact-icon"><i aria-hidden="true" class="bi bi-envelope-fill"></i></div><span class="text-secondary small"><?=Marca::h("correo")?></span></div>
      </div>
      <div class="col-md-4">
        <h2 class="h6 text-warning fw-bold mb-3">Horario</h2>
        <?php foreach(Marca::get("horario",[]) as $h): ?>
        <p class="text-secondary small mb-1"><i aria-hidden="true" class="bi <?=!empty($h["abierto"])?"bi-clock text-warning":"bi-x-circle text-danger"?> me-2"></i><?=htmlspecialchars($h["dia"]??"")?>: <?=htmlspecialchars($h["horas"]??"")?></p>
        <?php endforeach; ?>
      </div>
    </div>
    <hr style="border-color:var(--border);margin-top:2rem">
    <div class="d-flex justify-content-between flex-wrap gap-2">
      <p class="text-secondary small mb-0"><?=htmlspecialchars(Marca::pie())?></p>
    </div>
  </div>
</footer>
<script src="<?=APP_URL?>/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?=Assets::url("js/main.js")?>"></script>
</body></html>
