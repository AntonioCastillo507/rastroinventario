"use strict";
// Al enviar un formulario POST, el botón de envío se desactiva y dice "Enviando…" para que un doble clic
// no mande dos veces el mismo pedido. Se vuelve a activar si la persona regresa con "Atrás" (bfcache).
// Un botón con data-no-bloquear (p. ej. "Salir") o un formulario con data-no-bloquear queda fuera.
(function () {
  function restaurar(boton) {
    if (boton.dataset.htmlOriginal === undefined) return;
    boton.innerHTML = boton.dataset.htmlOriginal;
    delete boton.dataset.htmlOriginal;
    boton.disabled = false;
    boton.removeAttribute("aria-busy");
  }
  document.addEventListener("submit", function (e) {
    var form = e.target;
    if (!form || form.method.toLowerCase() !== "post" || form.hasAttribute("data-no-bloquear") || e.defaultPrevented) return;
    var enviador = e.submitter && e.submitter.form === form ? e.submitter : null;
    var botones = enviador ? [enviador] : Array.prototype.slice.call(form.querySelectorAll("button[type=submit], button:not([type])"));
    botones = botones.filter(function (b) { return !b.hasAttribute("data-no-bloquear"); });
    // Se desactiva después del envío: un botón desactivado antes no manda su nombre ni su formaction
    setTimeout(function () {
      botones.forEach(function (b) {
        if (b.dataset.htmlOriginal !== undefined) return;
        b.dataset.htmlOriginal = b.innerHTML;
        b.disabled = true;
        b.setAttribute("aria-busy", "true");
        b.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Enviando…';
      });
    }, 0);
  });
  window.addEventListener("pageshow", function (e) {
    if (e.persisted) document.querySelectorAll("button[data-html-original]").forEach(restaurar);
  });
})();
