"use strict";
// Modal de confirmación del panel (reemplaza a confirm()). Un <form> con data-confirmar='{"titulo","texto","boton","cancelar","peligro"}'
// pregunta antes de enviarse; con data-confirmar-campo="nombre" pregunta solo según el valor de ese campo (data-confirmar es
// un mapa valor => datos). Al aceptar se reenvía el mismo formulario (POST con su token). Usa <dialog>: showModal() deja el
// resto de la página inerte, Esc cierra y el foco vuelve a lo que abrió el modal. Foco inicial en el botón que NO actúa.
(function () {
  var dialogo = null, formulario = null, enviador = null, abridor = null, aceptado = false;

  function datos(form) {
    var crudo = form.getAttribute("data-confirmar");
    if (!crudo) return null;
    var d;
    try { d = JSON.parse(crudo); } catch (e) { return null; }
    var campo = form.getAttribute("data-confirmar-campo");
    if (campo) {
      var el = form.elements[campo];
      d = el && d && Object.prototype.hasOwnProperty.call(d, el.value) ? d[el.value] : null;
    }
    return d && typeof d.titulo === "string" ? d : null;
  }

  function parte(nombre) { return dialogo.querySelector("[data-mc-" + nombre + "]"); }

  function abrir(form, d, quien) {
    formulario = form; enviador = quien || null; abridor = document.activeElement; aceptado = false;
    parte("titulo").textContent = d.titulo;
    parte("texto").textContent = d.texto || "";
    var ok = parte("confirmar");
    ok.textContent = d.boton || "Aceptar";
    ok.className = "btn " + (d.peligro ? "btn-danger" : "btn-gold");
    parte("cancelar").textContent = d.cancelar || "Cancelar";
    dialogo.showModal();
    parte("cancelar").focus();
  }

  function alCerrar() {
    var form = formulario, quien = enviador, vuelve = abridor, ok = aceptado;
    formulario = enviador = abridor = null; aceptado = false;
    if (ok && form) {
      form.__confirmado = true;
      var s = quien && quien.form === form ? quien : undefined;
      if (form.requestSubmit) form.requestSubmit(s); else form.submit();
    } else if (vuelve && vuelve.focus && document.contains(vuelve)) {
      vuelve.focus();
    }
  }

  function preparar() {
    var el = document.getElementById("modalConfirmar");
    if (!el || typeof el.showModal !== "function") return false;
    dialogo = el;
    dialogo.addEventListener("close", alCerrar);
    parte("cancelar").addEventListener("click", function () { dialogo.close(); });
    parte("confirmar").addEventListener("click", function () { aceptado = true; dialogo.close(); });
    // Un clic en el fondo (fuera de la caja) equivale a cancelar
    dialogo.addEventListener("click", function (e) { if (e.target === dialogo) dialogo.close(); });
    // Foco atrapado entre los dos botones
    dialogo.addEventListener("keydown", function (e) {
      if (e.key !== "Tab") return;
      var primero = parte("cancelar"), ultimo = parte("confirmar");
      if (e.shiftKey && document.activeElement === primero) { e.preventDefault(); ultimo.focus(); }
      else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primero.focus(); }
    });
    return true;
  }

  document.addEventListener("DOMContentLoaded", function () { preparar(); });

  // En captura: corre antes que envio.js (que se salta los envíos ya cancelados)
  document.addEventListener("submit", function (e) {
    var form = e.target;
    if (!form || !form.getAttribute) return;
    if (form.__confirmado) { form.__confirmado = false; return; }
    var d = datos(form);
    if (!d) return;
    e.preventDefault();
    if (dialogo) { abrir(form, d, e.submitter); return; }
    // Sin <dialog> (navegador muy viejo): el aviso nativo, para no enviar sin preguntar
    if (window.confirm(d.titulo + ". " + (d.texto || ""))) { form.__confirmado = true; form.submit(); }
  }, true);
})();
