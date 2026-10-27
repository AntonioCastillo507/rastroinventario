</main>
<footer class="admin-footer">
  <?=htmlspecialchars(Marca::pie())?>
</footer>
  </div>
</div>
<dialog class="modal-confirmar" id="modalConfirmar" role="alertdialog" aria-labelledby="mcTitulo" aria-describedby="mcTexto">
  <h2 class="modal-confirmar-titulo" id="mcTitulo" data-mc-titulo></h2>
  <p class="modal-confirmar-texto" id="mcTexto" data-mc-texto></p>
  <div class="modal-confirmar-acciones">
    <button type="button" class="btn btn-outline-secondary" data-mc-cancelar>Cancelar</button>
    <button type="button" class="btn btn-danger" data-mc-confirmar>Aceptar</button>
  </div>
</dialog>
<script src="<?=APP_URL?>/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?=Assets::url("js/main.js")?>"></script>
<script src="<?=Assets::url("js/confirmar.js")?>"></script>
<script src="<?=Assets::url("js/envio.js")?>"></script>
</body></html>
