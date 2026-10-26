"use strict";
// "Añadir al carrito" sin recargar la página ni mover al usuario (N6): se manda el formulario con fetch, el contador del
// carrito del encabezado cambia y sale un aviso con "Ver carrito" en una región aria-live. El foco se queda en el botón.
// Sin JavaScript o si la petición falla, el formulario se envía normal y vuelve a la misma página (campo "volver").
// El token CSRF es por sesión (no se gasta); la respuesta aun así trae el vigente y se pone en todos los formularios de la
// página (también si se renovó al iniciar sesión en otra pestaña). Las peticiones van de una en una.
(function () {
  var cola = Promise.resolve();
  var region = null;

  function regionAvisos() {
    if (region) return region;
    region = document.getElementById("avisos-carrito");
    return region;
  }

  function actualizarContador(n) {
    var enlace = document.querySelector("a.nav-carrito");
    if (!enlace) return;
    enlace.setAttribute("aria-label", "Carrito, " + n + " pieza" + (n === 1 ? "" : "s"));
    var cuenta = enlace.querySelector(".cart-count");
    if (!cuenta) {
      cuenta = document.createElement("span");
      cuenta.className = "cart-count";
      cuenta.setAttribute("aria-hidden", "true");
      enlace.appendChild(cuenta);
    }
    cuenta.textContent = String(n);
  }

  function ponerToken(token) {
    if (!token) return;
    document.querySelectorAll('input[name="csrf_token"]').forEach(function (campo) { campo.value = token; });
  }

  function aviso(texto, esError, urlCarrito) {
    var zona = regionAvisos();
    if (!zona) return;
    while (zona.firstChild) zona.removeChild(zona.firstChild); // un solo aviso a la vez
    var caja = document.createElement("div");
    caja.className = "aviso-carrito" + (esError ? " es-error" : "");
    var span = document.createElement("span");
    span.className = "aviso-texto";
    span.textContent = texto;
    caja.appendChild(span);
    if (!esError && urlCarrito) {
      var ver = document.createElement("a");
      ver.href = urlCarrito;
      ver.textContent = "Ver carrito";
      caja.appendChild(ver);
    }
    var cerrar = document.createElement("button");
    cerrar.type = "button";
    cerrar.setAttribute("aria-label", "Cerrar aviso");
    cerrar.textContent = "×";
    cerrar.addEventListener("click", function () { if (caja.parentNode) caja.parentNode.removeChild(caja); });
    caja.appendChild(cerrar);
    zona.appendChild(caja);
    if (!esError) setTimeout(function () { if (caja.parentNode) caja.parentNode.removeChild(caja); }, 6000); // los errores no desaparecen solos
  }

  function enviar(form, boton) {
    var textoOriginal = boton.innerHTML;
    boton.disabled = true;
    boton.setAttribute("aria-busy", "true");
    return fetch(form.action, {
      method: "POST",
      body: new FormData(form),
      credentials: "same-origin",
      headers: { "X-Requested-With": "fetch", "Accept": "application/json" }
    }).then(function (r) {
      return r.json().then(function (datos) { return { estado: r.status, datos: datos }; });
    }).then(function (r) {
      ponerToken(r.datos.csrf);
      if (r.datos.ok) {
        if (typeof r.datos.count === "number") actualizarContador(r.datos.count);
        aviso(r.datos.msg, !!r.datos.alerta, r.datos.carrito);
      } else {
        aviso(r.datos.msg || "No se pudo añadir la pieza.", true);
      }
    }).catch(function () {
      form.submit(); // sin red o respuesta rara: el envío normal vuelve a esta página
    }).then(function () {
      boton.disabled = false;
      boton.removeAttribute("aria-busy");
      boton.innerHTML = textoOriginal;
    });
  }

  document.addEventListener("submit", function (e) {
    var form = e.target;
    if (!form || !form.hasAttribute || !form.hasAttribute("data-agregar-carrito") || !window.fetch || !window.FormData) return;
    e.preventDefault();
    var boton = form.querySelector("button[type=submit]");
    if (!boton || boton.disabled) return;
    cola = cola.then(function () { return enviar(form, boton); });
  });
})();
