"use strict";
// Carrusel de piezas del hero de la portada. Sin JavaScript queda la primera pieza visible y enlazada.
// Cada ~5 s pasa a la siguiente con un fundido (opacidad). Se detiene solo tras dar una vuelta completa por las piezas
// y desde entonces se maneja con las flechas y los puntos. Se pausa con el cursor encima, con el foco del teclado y al tocar.
// Con prefers-reduced-motion no cambia solo.
(function () {
  var INTERVALO = 5000;     // ms por pieza
  var PAUSA_TOQUE = 8000;   // ms sin avanzar tras tocar la tarjeta
  var TICK = 250;

  /** Posición siguiente con vuelta al inicio */
  function siguiente(i, total) { return total > 0 ? (i + 1) % total : 0; }
  /** Posición anterior con vuelta al final */
  function anterior(i, total) { return total > 0 ? (i - 1 + total) % total : 0; }
  /** ¿Cambia solo? Hace falta más de una pieza y que la persona no pida menos movimiento */
  function debeAnimar(total, reducido) { return total > 1 && !reducido; }
  /** ¿Ya dio la vuelta completa? Tras tantos avances automáticos como piezas, se queda en manual */
  function vueltaCompleta(avances, total) { return avances >= total; }
  window.HeroCarrusel = { INTERVALO: INTERVALO, siguiente: siguiente, anterior: anterior, debeAnimar: debeAnimar, vueltaCompleta: vueltaCompleta };

  var raiz = document.querySelector("[data-hero-carrusel]");
  if (!raiz) return;
  var diapos = Array.prototype.slice.call(raiz.querySelectorAll("[data-hero-diapo]"));
  var puntos = Array.prototype.slice.call(raiz.querySelectorAll("[data-hero-punto]"));
  var contPuntos = raiz.querySelector("[data-hero-puntos]");
  var lista = raiz.querySelector("[data-hero-diapos]");
  var btnAnt = raiz.querySelector("[data-hero-anterior]");
  var btnSig = raiz.querySelector("[data-hero-siguiente]");
  if (diapos.length < 2 || puntos.length !== diapos.length || !contPuntos || !lista || !btnAnt || !btnSig) return;

  var mq = window.matchMedia ? window.matchMedia("(prefers-reduced-motion: reduce)") : null;
  var actual = 0, avances = 0, transcurrido = 0, hastaToque = 0;
  var encima = false, foco = false, timer = 0;

  function mostrar(i) {
    actual = i;
    transcurrido = 0;
    diapos.forEach(function (d, k) {
      d.classList.toggle("is-activa", k === i);
      if (k === i) d.removeAttribute("aria-hidden"); else d.setAttribute("aria-hidden", "true");
      d.tabIndex = k === i ? 0 : -1;
    });
    puntos.forEach(function (p, k) {
      p.classList.toggle("is-activo", k === i);
      if (k === i) p.setAttribute("aria-current", "true"); else p.removeAttribute("aria-current");
    });
  }

  function parar() { if (timer) { window.clearInterval(timer); timer = 0; } }
  function pausado() { return encima || foco || document.hidden || Date.now() < hastaToque; }
  function tic() {
    if (pausado()) return;
    transcurrido += TICK;
    if (transcurrido < INTERVALO) return;
    mostrar(siguiente(actual, diapos.length));
    avances++;
    if (vueltaCompleta(avances, diapos.length)) parar();
  }
  function iniciar() {
    parar();
    if (debeAnimar(diapos.length, mq && mq.matches) && !vueltaCompleta(avances, diapos.length)) timer = window.setInterval(tic, TICK);
  }

  raiz.classList.add("hero-js");
  contPuntos.hidden = false;
  btnAnt.hidden = false;
  btnSig.hidden = false;
  mostrar(0);

  puntos.forEach(function (p, k) { p.addEventListener("click", function () { mostrar(k); }); });
  btnAnt.addEventListener("click", function () { mostrar(anterior(actual, diapos.length)); });
  btnSig.addEventListener("click", function () { mostrar(siguiente(actual, diapos.length)); });

  raiz.addEventListener("mouseenter", function () { encima = true; });
  raiz.addEventListener("mouseleave", function () { encima = false; });
  raiz.addEventListener("touchstart", function () { hastaToque = Date.now() + PAUSA_TOQUE; }, { passive: true });
  raiz.addEventListener("focusin", function (e) {
    var porTeclado = true;
    try { porTeclado = e.target.matches(":focus-visible"); } catch (x) {}
    if (porTeclado) foco = true;
  });
  raiz.addEventListener("focusout", function (e) { if (!raiz.contains(e.relatedTarget)) foco = false; });

  iniciar();
  if (mq && mq.addEventListener) mq.addEventListener("change", iniciar);
})();
