"use strict";
// Carrusel de opiniones de la portada. Sin JavaScript es una fila con scroll táctil y encaje por tarjeta (CSS).
// Aquí solo se añaden las flechas (una tarjeta por clic) y las teclas ← y →; no se mueve solo.
(function () {
  /** Tarjetas que hay que avanzar con un clic: las que caben a la vez, como mínimo una */
  function pasoEn(ventana, primera, separacion) {
    var ancho = primera.getBoundingClientRect().width + separacion;
    return ancho > 0 ? Math.max(ancho, Math.round(ventana.clientWidth / ancho) * ancho) : ventana.clientWidth;
  }
  window.OpinionesCarrusel = { pasoEn: pasoEn };

  var raiz = document.querySelector("[data-opiniones-carrusel]");
  if (!raiz) return;
  var ventana = raiz.querySelector("[data-opiniones-ventana]");
  var nav = raiz.querySelector("[data-opiniones-nav]");
  var btnAnt = raiz.querySelector("[data-opiniones-anterior]");
  var btnSig = raiz.querySelector("[data-opiniones-siguiente]");
  var pista = ventana && ventana.querySelector("ul");
  if (!ventana || !nav || !btnAnt || !btnSig || !pista || !pista.firstElementChild) return;
  var mq = window.matchMedia ? window.matchMedia("(prefers-reduced-motion: reduce)") : null;

  function estado() {
    var cabe = ventana.scrollWidth <= ventana.clientWidth + 1;
    nav.hidden = cabe;
    btnAnt.disabled = ventana.scrollLeft <= 1;
    btnSig.disabled = ventana.scrollLeft + ventana.clientWidth >= ventana.scrollWidth - 1;
  }
  function mover(signo) {
    var gap = parseFloat(window.getComputedStyle(pista).columnGap) || 0;
    ventana.scrollBy({ left: signo * pasoEn(ventana, pista.firstElementChild, gap), behavior: mq && mq.matches ? "auto" : "smooth" });
  }
  btnAnt.addEventListener("click", function () { mover(-1); });
  btnSig.addEventListener("click", function () { mover(1); });
  // Con el foco en la ventana, las flechas del teclado la desplazan; en los enlaces de las tarjetas se deja el comportamiento normal
  ventana.addEventListener("keydown", function (e) {
    if (e.target !== ventana) return;
    if (e.key === "ArrowLeft") { e.preventDefault(); mover(-1); }
    else if (e.key === "ArrowRight") { e.preventDefault(); mover(1); }
  });
  ventana.addEventListener("scroll", estado, { passive: true });
  window.addEventListener("resize", estado);
  estado();
})();
