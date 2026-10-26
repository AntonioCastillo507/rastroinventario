"use strict";
// Formulario de partes: vista previa exacta de la baldosa 1:1 con la foto elegida y avisos (no bloqueos) sobre su proporción
// y su resolución. Los umbrales salen del servidor (data-*), los mismos que usa ImageHelper::avisos().
(function () {
  function avisos(w, h, propMin, propMax, ladoBajo) {
    var lista = [];
    var prop = w / Math.max(1, h);
    if (prop < propMin || prop > propMax) lista.push("Proporción muy alargada: se mostrará con bordes.");
    if (Math.max(w, h) < ladoBajo) lista.push("Resolución baja: se verá borrosa al ampliar.");
    return lista;
  }
  window.ParteImagen = { avisos: avisos };

  var zona = document.querySelector("[data-foto-previa]");
  var campo = document.getElementById("f-imagen");
  if (!zona || !campo) return;
  var baldosa = zona.querySelector(".foto-baldosa");
  var titulo = zona.querySelector("[data-foto-titulo]");
  var info = zona.querySelector("[data-foto-info]");
  var caja = zona.querySelector("[data-foto-avisos]");
  var propMin = parseFloat(zona.dataset.propMin), propMax = parseFloat(zona.dataset.propMax), ladoBajo = parseInt(zona.dataset.ladoBajo, 10);
  var url = null;

  function limpiarAvisos() { while (caja.firstChild) caja.removeChild(caja.firstChild); }

  campo.addEventListener("change", function () {
    limpiarAvisos();
    if (url) { URL.revokeObjectURL(url); url = null; }
    var archivo = campo.files && campo.files[0];
    if (!archivo) return;
    if (!/^image\//.test(archivo.type)) { info.textContent = "Ese archivo no parece una imagen."; return; }
    url = URL.createObjectURL(archivo);
    var img = new Image();
    img.onload = function () {
      var vista = baldosa.querySelector("img");
      if (!vista) { baldosa.innerHTML = ""; vista = document.createElement("img"); baldosa.appendChild(vista); }
      vista.src = url;
      vista.alt = "Vista previa de la foto elegida";
      titulo.textContent = "Vista previa";
      info.textContent = img.naturalWidth + " × " + img.naturalHeight + " px, " + (archivo.size / 1048576).toFixed(1) + " MB.";
      avisos(img.naturalWidth, img.naturalHeight, propMin, propMax, ladoBajo).forEach(function (texto) {
        var p = document.createElement("div");
        p.className = "foto-aviso";
        var icono = document.createElement("i");
        icono.className = "bi bi-exclamation-triangle me-1";
        icono.setAttribute("aria-hidden", "true");
        p.appendChild(icono);
        p.appendChild(document.createTextNode(texto));
        caja.appendChild(p);
      });
    };
    img.onerror = function () { info.textContent = "No se pudo leer la imagen."; };
    img.src = url;
  });
})();
