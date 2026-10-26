"use strict";
// Paso 1 del carrito sin recargar: cambiar la cantidad con − / +, quitar una línea y deshacerlo.
// Cada botón es un formulario normal (funciona sin JavaScript). Aquí se envía con fetch y se escribe en pantalla lo que el
// servidor devuelve ya calculado y formateado: este archivo NUNCA suma ni calcula dinero (solo reparte textos y el estado de
// los botones). Las peticiones van de una en una; si falla la red, se envía el formulario de la forma normal.
(function () {
  if (!window.fetch || !window.FormData) return;
  var cola = Promise.resolve();

  function una(sel, raiz) { return (raiz || document).querySelector(sel); }
  function todas(sel, raiz) { return Array.prototype.slice.call((raiz || document).querySelectorAll(sel)); }

  function ponerToken(token) {
    if (!token) return;
    todas('input[name="csrf_token"]').forEach(function (campo) { campo.value = token; });
  }

  function texto(nodo, valor) { if (nodo) nodo.textContent = valor; }

  function actualizarContadorEncabezado(n) {
    var enlace = una("a.nav-carrito");
    if (!enlace) return;
    enlace.setAttribute("aria-label", "Carrito, " + n + " pieza" + (n === 1 ? "" : "s"));
    var cuenta = una(".cart-count", enlace);
    if (n < 1) { if (cuenta && cuenta.parentNode) cuenta.parentNode.removeChild(cuenta); return; }
    if (!cuenta) {
      cuenta = document.createElement("span");
      cuenta.className = "cart-count";
      cuenta.setAttribute("aria-hidden", "true");
      enlace.appendChild(cuenta);
    }
    cuenta.textContent = String(n);
  }

  function avisoNuevo(clase, mensaje) {
    var caja = document.createElement("div");
    caja.className = clase;
    caja.setAttribute("role", "alert");
    caja.setAttribute("data-js", "");
    caja.textContent = mensaje;
    return caja;
  }

  function limpiarAvisosJs(zona) {
    todas("[data-js]", zona).forEach(function (n) { if (n.parentNode) n.parentNode.removeChild(n); });
    todas("[data-deshacer]", zona).forEach(function (n) { if (n.parentNode) n.parentNode.removeChild(n); });
  }

  function avisoDeshacer(zona, deshacer, urlRestaurar, token) {
    var caja = document.createElement("div");
    caja.className = "carrito-deshacer";
    caja.setAttribute("role", "status");
    caja.setAttribute("data-deshacer", "");
    var span = document.createElement("span");
    span.textContent = "Quitaste «" + deshacer.nombre + "» de tu carrito.";
    caja.appendChild(span);
    var boton = document.createElement("button");
    boton.type = "button";
    boton.className = "btn btn-sm btn-outline-secondary";
    boton.textContent = "Deshacer";
    boton.addEventListener("click", function () {
      boton.disabled = true;
      cola = cola.then(function () {
        var datos = new FormData();
        datos.append("csrf_token", token);
        return pedir(urlRestaurar + "&id=" + deshacer.id, datos).then(function (r) {
          if (r.datos && r.datos.ok) window.location.reload(); else { boton.disabled = false; aplicar(r.datos || {}); }
        }).catch(function () { window.location.reload(); });
      });
    });
    caja.appendChild(boton);
    zona.appendChild(caja);
    return boton;
  }

  function pedir(url, datos) {
    return fetch(url, {
      method: "POST", body: datos, credentials: "same-origin",
      headers: { "X-Requested-With": "fetch", "Accept": "application/json" }
    }).then(function (r) { return r.json().then(function (d) { return { estado: r.status, datos: d }; }); });
  }

  // Escribe en la pantalla el estado completo que devolvió el servidor
  function aplicar(d, enfocarDeshacer) {
    var raiz = una("[data-carrito]");
    if (!raiz) return;
    ponerToken(d.csrf);
    if (d.lineas) {
      if (d.count === 0) { window.location.reload(); return; } // el servidor pinta el carrito vacío (y el "Deshacer")
      var presentes = {};
      d.lineas.forEach(function (l) { presentes[String(l.id)] = l; });
      todas("[data-linea]", raiz).forEach(function (nodo) {
        var l = presentes[nodo.getAttribute("data-linea")];
        if (!l) { if (nodo.parentNode) nodo.parentNode.removeChild(nodo); return; }
        texto(una("[data-linea-cantidad]", nodo), String(l.cantidad));
        texto(una("[data-linea-subtotal]", nodo), "$" + l.subtotal);
        var menos = una('[data-accion="menos"]', nodo), mas = una('[data-accion="mas"]', nodo);
        if (menos) { menos.value = String(Math.max(1, l.cantidad - 1)); menos.disabled = l.cantidad <= 1; }
        if (mas) { mas.value = String(Math.min(l.max, l.cantidad + 1)); mas.disabled = l.cantidad >= l.max; }
        nodo.removeAttribute("aria-busy");
      });
      if (d.resumen) {
        todas("[data-resumen]", raiz).forEach(function (n) { n.textContent = "$" + d.resumen[n.getAttribute("data-resumen")]; });
      }
      texto(una("[data-carrito-conteo]", raiz), d.count + " pieza" + (d.count === 1 ? "" : "s"));
      actualizarContadorEncabezado(d.count);
    }
    var zona = una("#carrito-avisos", raiz);
    if (zona) {
      limpiarAvisosJs(zona);
      (d.avisos || []).forEach(function (a) { zona.appendChild(avisoNuevo("alert alert-warning mb-3", a)); });
      if (d.ok === false && d.msg) zona.appendChild(avisoNuevo("alert alert-warning mb-3", d.msg));
      if (d.deshacer) {
        var b = avisoDeshacer(zona, d.deshacer, raiz.getAttribute("data-url-restaurar"), d.csrf || "");
        if (enfocarDeshacer && b.focus) b.focus(); // la línea quitada se llevó el foco: pasa a "Deshacer"
      }
    }
    texto(una("#carrito-estado"), d.msg || "");
  }

  // Sin red o con una respuesta rara: el envío normal (con el botón que se pulsó) lleva a la misma pantalla
  function enviarNormal(form, boton) {
    if (boton && boton.name) {
      var oculto = document.createElement("input");
      oculto.type = "hidden"; oculto.name = boton.name; oculto.value = boton.value;
      form.appendChild(oculto);
    }
    form.submit();
  }

  document.addEventListener("submit", function (e) {
    var form = e.target;
    if (!form || !form.hasAttribute || !form.hasAttribute("data-carrito-form") || !una("[data-carrito]")) return;
    e.preventDefault();
    var boton = e.submitter || null;
    var linea = form.closest ? form.closest("[data-linea]") : null;
    var esRestaurar = form.hasAttribute("data-restaurar");
    cola = cola.then(function () {
      var datos = new FormData(form);
      if (boton && boton.name && typeof datos.set === "function") datos.set(boton.name, boton.value);
      if (linea) linea.setAttribute("aria-busy", "true");
      return pedir(form.action, datos).then(function (r) {
        if (esRestaurar && r.datos && r.datos.ok) { window.location.reload(); return; }
        aplicar(r.datos || {}, form.hasAttribute("data-quitar"));
        if (linea && linea.parentNode) {
          linea.removeAttribute("aria-busy");
          // un botón que se desactiva (− en 1, + en el tope) pierde el foco: pasa al otro botón de la línea
          if (boton && boton.disabled) {
            var libre = todas("[data-accion]", linea).filter(function (b) { return !b.disabled; })[0];
            if (libre && libre.focus) libre.focus();
          }
        }
      }).catch(function () { enviarNormal(form, boton); });
    });
  });
})();
