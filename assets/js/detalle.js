"use strict";
// Detalle de pieza: cantidad con botones − y + (el campo manda su valor al formulario "Añadir al carrito" por su atributo
// form) y "Comprar ahora" que lleva la cantidad elegida. Sin JavaScript la cantidad es 1 y el campo se puede escribir.
(function () {
  var fila = document.querySelector("[data-cantidad]");
  if (!fila) return;
  var campo = fila.querySelector("[data-cantidad-campo]");
  var menos = fila.querySelector("[data-cantidad-menos]");
  var mas = fila.querySelector("[data-cantidad-mas]");
  var comprar = document.querySelector("[data-comprar-ahora]");
  var max = parseInt(fila.getAttribute("data-max"), 10) || 1;
  var urlBase = comprar ? comprar.getAttribute("href") : "";

  function valor() {
    var n = parseInt(campo.value, 10);
    if (isNaN(n) || n < 1) n = 1;
    return Math.min(n, max);
  }
  function aplicar(n) {
    n = Math.max(1, Math.min(n, max));
    campo.value = String(n);
    menos.disabled = n <= 1;
    mas.disabled = n >= max;
    if (comprar) comprar.setAttribute("href", urlBase + (urlBase.indexOf("?") === -1 ? "?" : "&") + "cantidad=" + n);
  }
  menos.addEventListener("click", function () { aplicar(valor() - 1); });
  mas.addEventListener("click", function () { aplicar(valor() + 1); });
  campo.addEventListener("change", function () { aplicar(valor()); });
  campo.addEventListener("input", function () { if (campo.value !== "") aplicar(valor()); });
  aplicar(valor());
})();
