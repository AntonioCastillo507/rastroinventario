// Desplegables dependientes de la dirección de Panamá: provincia → distrito → corregimiento.
// Los datos salen de ?action=geo (solo lectura, sin datos personales). Sin JavaScript el servidor pinta las listas y valida igual.
(function () {
  "use strict";
  var caja = document.querySelector(".direccion-campos[data-geo-url]");
  if (!caja || !window.fetch) return;

  var url = caja.getAttribute("data-geo-url");
  var prov = caja.querySelector("#f-dir-provincia");
  var dist = caja.querySelector("#f-dir-distrito");
  var corr = caja.querySelector("#f-dir-corregimiento");
  var gDist = caja.querySelector("#dir-grupo-distrito");
  var gCorr = caja.querySelector("#dir-grupo-corregimiento");
  var pedido = 0; // descarta respuestas que llegan tarde si la persona ya eligió otra cosa

  function pedir(nivel, padre) {
    return fetch(url + "&nivel=" + encodeURIComponent(nivel) + "&padre=" + encodeURIComponent(padre), { headers: { Accept: "application/json" } })
      .then(function (r) { if (!r.ok) throw new Error("geo"); return r.json(); });
  }

  // Rellena un desplegable y muestra u oculta su grupo: sin elementos no se muestra ni se envía (el servidor lo completa)
  function llenar(select, grupo, items) {
    while (select.options.length > 1) select.remove(1);
    items.forEach(function (it) { select.add(new Option(it.nombre, it.codigo)); });
    select.value = "";
    var hay = items.length > 0;
    grupo.hidden = !hay;
    select.disabled = !hay;
    select.required = hay;
  }

  function vaciar(select, grupo) { llenar(select, grupo, []); }

  prov.addEventListener("change", function () {
    var mio = ++pedido;
    vaciar(dist, gDist); vaciar(corr, gCorr);
    if (!prov.value) return;
    pedir("distritos", prov.value).then(function (d) {
      if (mio !== pedido) return;
      llenar(dist, gDist, d.items);
      // Sin distritos que mostrar (Guna Yala): los corregimientos se piden con el distrito interno
      if (!d.items.length && d.implicito) return pedir("corregimientos", d.implicito).then(function (c) { if (mio === pedido) llenar(corr, gCorr, c.items); });
    }).catch(function () { /* sin red: queda el formulario, el servidor valida igual */ });
  });

  dist.addEventListener("change", function () {
    var mio = ++pedido;
    vaciar(corr, gCorr);
    if (!dist.value) return;
    pedir("corregimientos", dist.value).then(function (c) { if (mio === pedido) llenar(corr, gCorr, c.items); }).catch(function () {});
  });
})();
