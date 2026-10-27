"use strict";
// Menú lateral del panel. Se carga en el <head> sin defer para fijar el estado antes de pintar (sin salto).
// En escritorio se contrae a una columna de iconos y la elección se recuerda; en móvil es un cajón (Bootstrap offcanvas).
(function () {
  var CLAVE = "rastro-panel-menu";
  var raiz = document.documentElement;

  function guardado() {
    try { return localStorage.getItem(CLAVE) === "colapsado" ? "colapsado" : "abierto"; } catch (e) { return "abierto"; }
  }

  function aplicar(estado) {
    raiz.setAttribute("data-panel-menu", estado);
    var abierto = estado === "abierto";
    document.querySelectorAll("[data-panel-menu-toggle]").forEach(function (b) {
      b.setAttribute("aria-expanded", abierto ? "true" : "false");
      var texto = b.querySelector(".admin-enlace-texto");
      if (texto) texto.textContent = abierto ? "Contraer menú" : "Expandir menú";
    });
    // Con la columna de iconos, el nombre de cada enlace sale como ayuda al pasar el cursor
    document.querySelectorAll(".admin-sidebar .admin-enlace").forEach(function (a) {
      var t = a.querySelector(".admin-enlace-texto");
      if (!t) return;
      if (abierto) a.removeAttribute("title"); else a.setAttribute("title", t.textContent);
    });
  }

  aplicar(guardado());

  document.addEventListener("DOMContentLoaded", function () { aplicar(raiz.getAttribute("data-panel-menu") === "colapsado" ? "colapsado" : "abierto"); });

  document.addEventListener("click", function (e) {
    var b = e.target.closest ? e.target.closest("[data-panel-menu-toggle]") : null;
    if (!b) return;
    var nuevo = raiz.getAttribute("data-panel-menu") === "colapsado" ? "abierto" : "colapsado";
    aplicar(nuevo);
    try { localStorage.setItem(CLAVE, nuevo); } catch (err) { /* navegación privada: vale solo en esta página */ }
  });
})();
