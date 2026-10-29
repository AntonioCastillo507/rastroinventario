"use strict";
// Casilla «Mostrar en la portada»: al marcarla se abre un aviso con la vista previa real del recorte 16:9 (la misma caja
// .hero-foto de la tarjeta de la portada, con object-fit: cover). «Cancelar», Esc o el fondo la dejan desmarcada.
// Sin foto no se abre: avisa y la desmarca (el servidor también lo valida). Sin JavaScript la casilla funciona igual.
(function () {
  var casilla = document.querySelector("[data-destacar]");
  var dialogo = document.getElementById("modalDestacar");
  if (!casilla || !dialogo || typeof dialogo.showModal !== "function") return;
  var img = dialogo.querySelector("[data-destacar-img]");
  var aviso = document.querySelector("[data-destacar-aviso]");
  var campoFoto = document.getElementById("f-imagen");
  var url = null, acepto = false;

  function fuente() {
    var f = campoFoto && campoFoto.files && campoFoto.files[0];
    if (f && /^image\//.test(f.type)) {
      if (url) URL.revokeObjectURL(url);
      url = URL.createObjectURL(f);
      return url;
    }
    return casilla.getAttribute("data-foto-actual") || "";
  }

  // Sin foto el interruptor queda desactivado; se activa al elegir una (o si la pieza ya tiene una)
  function sinFoto() {
    var f = campoFoto && campoFoto.files && campoFoto.files[0];
    return !f && !casilla.getAttribute("data-foto-actual");
  }
  function revisar() {
    var no = sinFoto();
    if (no) casilla.checked = false;
    casilla.disabled = no;
    aviso.textContent = no ? "Primero elige una foto" : "";
  }
  if (campoFoto) campoFoto.addEventListener("change", revisar);
  revisar();

  casilla.addEventListener("change", function () {
    aviso.textContent = "";
    if (!casilla.checked) return;
    var src = fuente();
    if (!src) {
      casilla.checked = false;
      aviso.textContent = "Una pieza sin foto no puede ser destacada: sube una foto primero.";
      return;
    }
    img.src = src;
    acepto = false;
    dialogo.showModal();
    dialogo.querySelector("[data-destacar-cancelar]").focus();
  });
  dialogo.querySelector("[data-destacar-ok]").addEventListener("click", function () { acepto = true; dialogo.close(); });
  dialogo.querySelector("[data-destacar-cancelar]").addEventListener("click", function () { dialogo.close(); });
  dialogo.addEventListener("click", function (e) { if (e.target === dialogo) dialogo.close(); });
  dialogo.addEventListener("close", function () {
    if (!acepto) casilla.checked = false;
    casilla.focus();
  });
})();
