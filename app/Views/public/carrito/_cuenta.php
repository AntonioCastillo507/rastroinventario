<?php
// Bloque "Tu cuenta": hoy el invitado crea una cuenta con contraseña (obligatoria). Es el ÚNICO fragmento del paso 2 que cambia
// cuando llegue la compra como invitado (cuenta opcional): los demás campos y el diseño del paso no se tocan.
// Solo se incluye para quien no ha iniciado sesión.
?>
<fieldset class="carrito-bloque">
  <legend class="carrito-bloque-titulo">Tu cuenta</legend>
  <p class="carrito-ayuda">Con tu correo y esta contraseña podrás ver tus pedidos.</p>
  <label for="f-nueva-pass" class="form-label-dark">Contraseña * <span class="text-secondary fw-normal">(mínimo <?=Validator::PASS_MIN?> caracteres, máximo <?=Validator::PASS_MAX?> bytes)</span></label>
  <input id="f-nueva-pass" type="password" name="nueva_pass" class="form-control dark-input" minlength="<?=Validator::PASS_MIN?>" maxlength="<?=Validator::PASS_MAX?>" required autocomplete="new-password">
  <p class="carrito-ayuda mb-0 mt-2">¿Ya tienes cuenta? <a href="<?=APP_URL?>/login.php">Inicia sesión</a> para comprar más rápido.</p>
</fieldset>
