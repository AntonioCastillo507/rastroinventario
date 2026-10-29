"use strict";
// Formulario de marcas: vista previa del logo elegido (en una sola tinta, como se verá en la tienda) y aviso si el
// archivo no cumple tipo o tamaño. Los límites salen del servidor (data-*); el servidor vuelve a validar todo.
(function () {
  function aviso(tipo, bytes, maxRaster, maxSvg) {
    if (tipo === "image/svg+xml") return bytes > maxSvg ? "El SVG pesa más de " + Math.round(maxSvg / 1024) + " KB." : "";
    if (tipo === "image/png" || tipo === "image/webp") return bytes > maxRaster ? "El logo pesa más de " + Math.round(maxRaster / 1024) + " KB." : "";
    return "El logo debe ser PNG, WebP o SVG.";
  }
  window.MarcaLogo = { aviso: aviso };

  var zona = document.querySelector("[data-logo-previa]");
  var campo = document.getElementById("f-logo");
  if (!zona || !campo) return;
  var casilla = zona.querySelector("[data-logo-casilla]");
  var titulo = zona.querySelector("[data-logo-titulo]");
  var info = zona.querySelector("[data-logo-info]");
  var caja = zona.querySelector("[data-logo-avisos]");
  var maxRaster = parseInt(zona.dataset.maxRaster, 10), maxSvg = parseInt(zona.dataset.maxSvg, 10);
  var url = null;

  campo.addEventListener("change", function () {
    caja.textContent = "";
    if (url) { URL.revokeObjectURL(url); url = null; }
    var archivo = campo.files && campo.files[0];
    if (!archivo) return;
    var texto = aviso(archivo.type, archivo.size, maxRaster, maxSvg);
    info.textContent = archivo.name + " · " + Math.max(1, Math.round(archivo.size / 1024)) + " KB";
    if (texto) { caja.textContent = texto; return; }
    url = URL.createObjectURL(archivo);
    var logo = casilla.querySelector(".marca-logo");
    if (!logo) {
      var caja2 = casilla.querySelector(".marca-casilla");
      caja2.textContent = "";
      logo = document.createElement("span");
      logo.className = "marca-logo";
      caja2.appendChild(logo);
    }
    logo.style.setProperty("--logo-url", "url('" + url + "')");
    titulo.textContent = "Vista previa";
  });
})();
