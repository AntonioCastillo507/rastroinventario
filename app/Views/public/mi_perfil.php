<?php
$title="Mi perfil"; $cuentaActiva="perfil";
include BASE_PATH."/app/Views/layouts/cuenta_abrir.php";
$errores=$errores??[];
?>
<h1 class="cuenta-titulo">Mi perfil</h1>
<?php if($error??""): ?><div role="alert" class="alert-dark-danger d-flex gap-2 mb-3"><i aria-hidden="true" class="bi bi-exclamation-triangle-fill text-danger"></i><?=htmlspecialchars($error)?></div><?php endif; ?>
<form action="<?=APP_URL?>/?action=mi_perfil" method="POST" class="cuenta-tarjeta" novalidate>
  <?=CsrfToken::field()?>
  <div class="mb-3">
    <label for="f-nombre" class="form-label-dark">Nombre completo *</label>
    <input id="f-nombre" type="text" name="nombre" class="form-control dark-input" required maxlength="100" autocomplete="name" value="<?=htmlspecialchars($user["nombre"]??"")?>"<?=isset($errores["nombre"])?' aria-invalid="true" aria-describedby="e-nombre"':""?>>
    <?php if(isset($errores["nombre"])): ?><p class="campo-error" id="e-nombre" role="alert"><i aria-hidden="true" class="bi bi-exclamation-circle"></i><span><?=htmlspecialchars($errores["nombre"])?></span></p><?php endif; ?>
  </div>
  <div class="mb-3">
    <label for="f-campo" class="form-label-dark">Correo electrónico</label>
    <input id="f-campo" type="email" class="form-control dark-input" disabled autocomplete="email" value="<?=htmlspecialchars($user["email"]??"")?>">
    <div class="form-text">El correo no se puede cambiar.</div>
  </div>
  <div class="mb-4">
    <label for="f-telefono" class="form-label-dark">Teléfono de contacto</label>
    <input id="f-telefono" type="tel" name="telefono" class="form-control dark-input" maxlength="20" autocomplete="tel" inputmode="tel" placeholder="6000-0000" value="<?=htmlspecialchars($user["telefono"]??"")?>"<?=isset($errores["telefono"])?' aria-invalid="true" aria-describedby="e-telefono"':' aria-describedby="a-telefono"'?>>
    <?php if(isset($errores["telefono"])): ?><p class="campo-error" id="e-telefono" role="alert"><i aria-hidden="true" class="bi bi-exclamation-circle"></i><span><?=htmlspecialchars($errores["telefono"])?></span></p>
    <?php else: ?><div class="form-text" id="a-telefono">Celular de 8 dígitos (empieza por 6) o fijo de 7 dígitos. Puedes escribir +507.</div><?php endif; ?>
  </div>
  <button type="submit" class="btn btn-gold fw-bold">Guardar cambios</button>
</form>
<?php include BASE_PATH."/app/Views/layouts/cuenta_cerrar.php"; ?>
