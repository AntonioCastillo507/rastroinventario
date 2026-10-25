"use strict";
// Tema claro/oscuro. Se carga en el <head> sin defer para fijar el tema antes de pintar (sin parpadeo).
// Oscuro por defecto; la elección se recuerda en el navegador. Los botones [data-tema-toggle] lo alternan.
(function () {
  var CLAVE = "rastro-tema";
  var raiz = document.documentElement;

  function guardado() {
    try {
      var v = localStorage.getItem(CLAVE);
      return v === "light" || v === "dark" ? v : null;
    } catch (e) { return null; }
  }

  function aplicar(tema) {
    raiz.setAttribute("data-theme", tema);
    raiz.setAttribute("data-bs-theme", tema);
    var botones = document.querySelectorAll("[data-tema-toggle]");
    var texto = tema === "dark" ? "Cambiar a modo claro" : "Cambiar a modo oscuro";
    for (var i = 0; i < botones.length; i++) {
      botones[i].setAttribute("aria-label", texto);
      botones[i].setAttribute("title", texto);
      var icono = botones[i].querySelector("i");
      if (icono) icono.className = tema === "dark" ? "bi bi-sun" : "bi bi-moon-stars";
    }
  }

  aplicar(guardado() || "dark");

  document.addEventListener("DOMContentLoaded", function () {
    aplicar(raiz.getAttribute("data-theme") === "light" ? "light" : "dark");
  });

  document.addEventListener("click", function (e) {
    var boton = e.target.closest ? e.target.closest("[data-tema-toggle]") : null;
    if (!boton) return;
    var nuevo = raiz.getAttribute("data-theme") === "light" ? "dark" : "light";
    aplicar(nuevo);
    try { localStorage.setItem(CLAVE, nuevo); } catch (err) { /* navegación privada: el tema vale solo en esta página */ }
  });

  // Si se cambia en otra pestaña, esta se pone igual
  window.addEventListener("storage", function (e) {
    if (e.key === CLAVE && (e.newValue === "light" || e.newValue === "dark")) aplicar(e.newValue);
  });
})();
