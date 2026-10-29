"use strict";
// Carrusel de piezas del hero de la portada. Sin JavaScript queda la primera pieza visible y enlazada.
// Cada ~5 s pasa a la siguiente con un fundido (opacidad); el avance lo marca el fin de la animación de la barrita activa.
// Se pausa con el cursor encima, con el foco del teclado dentro y con el botón de pausa/reanudar (WCAG 2.2.2).
// Con prefers-reduced-motion no cambia solo: se maneja con las barritas.
(function () {
  /** Siguiente posición con vuelta al inicio */
  function siguiente(i, total) {
    return total > 0 ? (i + 1) % total : 0;
  }
  /** ¿Cambia solo? Hace falta más de una pieza y que la persona no pida menos movimiento */
  function debeAnimar(total, reducido) {
    return total > 1 && !reducido;
  }
  window.HeroCarrusel = { siguiente: siguiente, debeAnimar: debeAnimar };

  var raiz = document.querySelector("[data-hero-carrusel]");
  if (!raiz) return;
  var diapos = Array.prototype.slice.call(raiz.querySelectorAll("[data-hero-diapo]"));
  var barras = Array.prototype.slice.call(raiz.querySelectorAll("[data-hero-barra]"));
  var contBarras = raiz.querySelector("[data-hero-barras]");
  var lista = raiz.querySelector("[data-hero-diapos]");
  var btnPausa = raiz.querySelector("[data-hero-pausa]");
  if (diapos.length < 2 || barras.length !== diapos.length || !contBarras || !lista || !btnPausa) return;

  var mq = window.matchMedia ? window.matchMedia("(prefers-reduced-motion: reduce)") : null;
  var actual = 0;
  var manual = false, hover = false, foco = false;

  function pausado() { return manual || hover || foco; }

  function mostrar(i, reiniciar) {
    actual = i;
    diapos.forEach(function (d, k) {
      d.classList.toggle("is-activa", k === i);
      if (k === i) d.removeAttribute("aria-hidden"); else d.setAttribute("aria-hidden", "true");
      d.tabIndex = k === i ? 0 : -1;
    });
    barras.forEach(function (b, k) {
      b.classList.toggle("is-activa", k === i);
      if (k === i) b.setAttribute("aria-current", "true"); else b.removeAttribute("aria-current");
    });
    if (reiniciar) { // la misma barrita vuelve a llenarse desde cero
      var b = barras[i];
      b.classList.remove("is-activa"); void b.offsetWidth; b.classList.add("is-activa");
    }
  }

  function aplicarPausa() {
    var p = pausado();
    raiz.classList.toggle("hero-pausado", p);
    lista.setAttribute("aria-live", p ? "polite" : "off");
  }

  raiz.classList.add("hero-js");
  contBarras.hidden = false;
  mostrar(0, false);

  barras.forEach(function (b, k) {
    b.addEventListener("click", function () { mostrar(k, k === actual); });
  });

  btnPausa.addEventListener("click", function () {
    manual = !manual;
    btnPausa.setAttribute("aria-pressed", manual ? "true" : "false");
    btnPausa.setAttribute("aria-label", btnPausa.getAttribute(manual ? "data-texto-reanudar" : "data-texto-pausar"));
    var icono = btnPausa.querySelector("i");
    if (icono) icono.className = "bi " + (manual ? "bi-play-fill" : "bi-pause-fill");
    aplicarPausa();
  });

  raiz.addEventListener("mouseenter", function () { hover = true; aplicarPausa(); });
  raiz.addEventListener("mouseleave", function () { hover = false; aplicarPausa(); });
  raiz.addEventListener("focusin", function (e) {
    var porTeclado = true;
    try { porTeclado = e.target.matches(":focus-visible"); } catch (x) {}
    if (porTeclado) { foco = true; aplicarPausa(); }
  });
  raiz.addEventListener("focusout", function (e) {
    if (!raiz.contains(e.relatedTarget)) { foco = false; aplicarPausa(); }
  });

  function configurar() {
    var anima = debeAnimar(diapos.length, mq && mq.matches);
    raiz.classList.toggle("hero-quieto", !anima);
    btnPausa.hidden = !anima; // sin cambio automático no hay nada que pausar
  }
  configurar();
  if (mq && mq.addEventListener) mq.addEventListener("change", configurar);

  // El fin del llenado de la barrita activa es el aviso de pasar a la siguiente
  contBarras.addEventListener("animationend", function (e) {
    if (e.animationName !== "hero-llena" || raiz.classList.contains("hero-quieto")) return;
    mostrar(siguiente(actual, diapos.length), false);
  });
  // Con la pestaña oculta el llenado se detiene para no acumular cambios
  document.addEventListener("visibilitychange", function () { raiz.classList.toggle("hero-oculto", document.hidden); });
})();
