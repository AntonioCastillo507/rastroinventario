"use strict";
// Carrusel de marcas de la portada. La ventana es una fila con scroll horizontal (sin JavaScript se desliza a mano);
// aquí se mueve sola de forma continua y sin saltos: el contenido se repite en tres bloques iguales y la posición
// salta un bloque entero cuando se acerca a un borde, con lo que se ve lo mismo antes y después del salto.
// Se pausa con el cursor encima, con el foco del teclado dentro, con el botón de pausa, mientras se toca o se usan las
// flechas, con la pestaña oculta o fuera de pantalla. Tras 30 s en movimiento se queda quieto y se maneja con las flechas. Con prefers-reduced-motion no se mueve sola: queda la fila con scroll.
(function () {
  var VELOCIDAD = 30;       // px por segundo (unos 40 s por vuelta a 1200 px)
  var TIEMPO_MAX = 30000;   // ms en movimiento; después se queda quieto y se maneja con las flechas
  var MIN_MARCAS = 4;       // con menos no se anima: caben quietas
  var PAUSA_USUARIO = 4000; // ms sin moverse tras tocar, usar la rueda o las flechas

  /** Cuántas veces hay que repetir las marcas para que un bloque llene la ventana */
  function repeticiones(anchoVentana, anchoMarcas) {
    return anchoMarcas > 0 ? Math.max(1, Math.ceil(anchoVentana / anchoMarcas)) : 1;
  }
  /** ¿Se anima? Hace falta un mínimo de marcas y que la persona no pida menos movimiento */
  function debeAnimar(marcas, reducido) {
    return marcas >= MIN_MARCAS && !reducido;
  }
  /** Mantiene la posición entre medio bloque y dos bloques saltando un bloque entero (el contenido es idéntico) */
  function normalizar(pos, bloque) {
    if (bloque <= 0) return pos;
    if (pos > bloque * 2) return pos - bloque;
    if (pos < bloque * 0.5) return pos + bloque;
    return pos;
  }
  /** ¿Ya se movió el tiempo máximo? */
  function agotado(ms) { return ms >= TIEMPO_MAX; }
  window.MarcasCarrusel = { VELOCIDAD: VELOCIDAD, TIEMPO_MAX: TIEMPO_MAX, agotado: agotado, MIN_MARCAS: MIN_MARCAS, repeticiones: repeticiones, debeAnimar: debeAnimar, normalizar: normalizar };

  var raiz = document.querySelector("[data-marcas-carrusel]");
  if (!raiz) return;
  var ventana = raiz.querySelector("[data-marcas-ventana]");
  var pista = raiz.querySelector("[data-marcas-pista]");
  var controles = document.querySelector("[data-marcas-controles]");
  var btnAnt = document.querySelector("[data-marcas-anterior]");
  var btnSig = document.querySelector("[data-marcas-siguiente]");
  if (!ventana || !pista || !controles || !btnAnt || !btnSig) return;

  var originales = Array.prototype.slice.call(pista.children);
  var mq = window.matchMedia ? window.matchMedia("(prefers-reduced-motion: reduce)") : null;
  var animado = false, bloque = 0;
  var movido = 0, encima = false, conFoco = false, oculta = false, fuera = false, hastaUsuario = 0;
  var rafId = 0, ultimo = 0, resto = 0;

  function reducido() { return !!(mq && mq.matches); }
  function pausado() { return encima || conFoco || oculta || fuera || Date.now() < hastaUsuario; }

  function quitarClones() {
    Array.prototype.slice.call(pista.querySelectorAll("[data-clon]")).forEach(function (n) { pista.removeChild(n); });
  }
  function clonarConjunto() {
    return originales.map(function (li) {
      var c = li.cloneNode(true);
      c.setAttribute("data-clon", "");
      c.setAttribute("aria-hidden", "true");
      Array.prototype.forEach.call(c.querySelectorAll("a"), function (a) { a.setAttribute("tabindex", "-1"); });
      return c;
    });
  }

  /** Arma el carrusel según el ancho actual: tres bloques con las marcas originales en el del medio */
  function armar() {
    quitarClones();
    detener();
    raiz.classList.remove("es-animado", "es-fija");
    ventana.scrollLeft = 0;
    // Ancho real de un conjunto de marcas, con la separación que lo sigue (la pista mide al menos el ancho de la ventana)
    var gap = parseFloat(window.getComputedStyle(pista).columnGap) || 0;
    var primero = originales[0].getBoundingClientRect(), ultimoLi = originales[originales.length - 1].getBoundingClientRect();
    var conjunto = ultimoLi.right - primero.left + gap;
    animado = debeAnimar(originales.length, reducido()) && conjunto > 0;
    if (!animado) {
      var cabe = pista.scrollWidth <= ventana.clientWidth + 1;
      raiz.classList.toggle("es-fija", cabe);
      controles.hidden = cabe;
      actualizarFlechas();
      return;
    }
    var r = repeticiones(ventana.clientWidth, conjunto);
    var antes = document.createDocumentFragment(), despues = document.createDocumentFragment();
    var i, k;
    for (k = 0; k < r; k++) clonarConjunto().forEach(function (c) { antes.appendChild(c); });
    for (k = 0; k < r - 1; k++) clonarConjunto().forEach(function (c) { despues.appendChild(c); });
    for (k = 0; k < r; k++) clonarConjunto().forEach(function (c) { despues.appendChild(c); });
    pista.insertBefore(antes, pista.firstChild);
    pista.appendChild(despues);
    raiz.classList.add("es-animado");
    controles.hidden = false;
    btnAnt.disabled = btnSig.disabled = false;
    // Un bloque = r repeticiones del conjunto (con la separación entre ellos); el del medio empieza en las marcas originales
    bloque = originales[0].getBoundingClientRect().left - pista.getBoundingClientRect().left;
    ventana.scrollLeft = bloque;
    iniciar();
  }

  function actualizarFlechas() {
    if (animado) return;
    btnAnt.disabled = ventana.scrollLeft <= 1;
    btnSig.disabled = ventana.scrollLeft + ventana.clientWidth >= ventana.scrollWidth - 1;
  }

  function paso(t) {
    rafId = window.requestAnimationFrame(paso);
    var dt = ultimo ? Math.min((t - ultimo) / 1000, 0.1) : 0;
    ultimo = t;
    if (pausado()) return;
    resto += VELOCIDAD * dt;
    movido += dt * 1000;
    if (agotado(movido)) { detener(); return; }
    var px = Math.floor(resto);
    if (px >= 1) { resto -= px; ventana.scrollLeft += px; }
  }
  function iniciar() { if (!rafId && animado) { ultimo = 0; rafId = window.requestAnimationFrame(paso); } }
  function detener() { if (rafId) { window.cancelAnimationFrame(rafId); rafId = 0; } }

  ventana.addEventListener("scroll", function () {
    if (animado) {
      var n = normalizar(ventana.scrollLeft, bloque);
      if (n !== ventana.scrollLeft) ventana.scrollLeft = n;
    } else {
      actualizarFlechas();
    }
  }, { passive: true });

  function usuario() { hastaUsuario = Date.now() + PAUSA_USUARIO; }
  ["pointerdown", "touchstart", "wheel"].forEach(function (e) { ventana.addEventListener(e, usuario, { passive: true }); });
  ventana.addEventListener("mouseenter", function () { encima = true; });
  ventana.addEventListener("mouseleave", function () { encima = false; });
  raiz.addEventListener("focusin", function () { conFoco = true; });
  raiz.addEventListener("focusout", function (e) { if (!raiz.contains(e.relatedTarget)) conFoco = false; });
  document.addEventListener("visibilitychange", function () { oculta = document.hidden; });
  if ("IntersectionObserver" in window) {
    new IntersectionObserver(function (es) { fuera = !es[0].isIntersecting; }).observe(raiz);
  }

  function mover(signo) {
    usuario();
    var paso2 = Math.max(200, Math.round(ventana.clientWidth * 0.8));
    ventana.scrollBy({ left: signo * paso2, behavior: reducido() ? "auto" : "smooth" });
  }
  btnAnt.addEventListener("click", function () { mover(-1); });
  btnSig.addEventListener("click", function () { mover(1); });
  var anchoPrevio = window.innerWidth, temporizador = 0;
  window.addEventListener("resize", function () {
    window.clearTimeout(temporizador);
    temporizador = window.setTimeout(function () {
      if (window.innerWidth !== anchoPrevio) { anchoPrevio = window.innerWidth; armar(); }
    }, 150);
  });
  if (mq && mq.addEventListener) mq.addEventListener("change", armar);

  armar();
})();
