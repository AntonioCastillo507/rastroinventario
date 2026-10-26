// Prueba de assets/js/detalle.js con un DOM mínimo falso. Uso: node tests/Support/detalle_js_prueba.js
"use strict";
const fs = require("fs");
const path = require("path");
const vm = require("vm");
const fuente = fs.readFileSync(path.join(__dirname, "..", "..", "assets", "js", "detalle.js"), "utf8");

function escenario(max, inicial) {
  const attrs = { href: "/r/?action=comprar&id=5" };
  const comprar = { getAttribute: (k) => attrs[k], setAttribute: (k, v) => { attrs[k] = v; } };
  const escuchas = { campo: {}, menos: {}, mas: {} };
  const campo = { value: String(inicial), addEventListener: (t, f) => { escuchas.campo[t] = f; } };
  const menos = { disabled: false, addEventListener: (t, f) => { escuchas.menos[t] = f; } };
  const mas = { disabled: false, addEventListener: (t, f) => { escuchas.mas[t] = f; } };
  const fila = { getAttribute: (k) => (k === "data-max" ? String(max) : null), querySelector: (s) => ({ "[data-cantidad-campo]": campo, "[data-cantidad-menos]": menos, "[data-cantidad-mas]": mas })[s] };
  const documento = { querySelector: (s) => (s === "[data-cantidad]" ? fila : s === "[data-comprar-ahora]" ? comprar : null) };
  vm.runInContext(fuente, vm.createContext({ document: documento }));
  return { campo, menos, mas, attrs, escuchas };
}

const r = {};
let e = escenario(5, 1);
r.inicio = { valor: e.campo.value, menosOff: e.menos.disabled, masOff: e.mas.disabled, href: e.attrs.href };
e.escuchas.mas.click(); e.escuchas.mas.click();
r.sube = { valor: e.campo.value, href: e.attrs.href, menosOff: e.menos.disabled };
for (let i = 0; i < 10; i++) e.escuchas.mas.click();
r.tope = { valor: e.campo.value, masOff: e.mas.disabled, href: e.attrs.href };
for (let i = 0; i < 10; i++) e.escuchas.menos.click();
r.piso = { valor: e.campo.value, menosOff: e.menos.disabled, href: e.attrs.href };
e.campo.value = "99"; e.escuchas.campo.change();
r.escribeEnorme = { valor: e.campo.value, masOff: e.mas.disabled };
e.campo.value = "abc"; e.escuchas.campo.change();
r.escribeLetras = { valor: e.campo.value };
e.campo.value = "-4"; e.escuchas.campo.change();
r.escribeNegativo = { valor: e.campo.value };
e.campo.value = ""; e.escuchas.campo.input();
r.vacioMientrasEscribe = { valor: e.campo.value };
e = escenario(1, 1);
r.unaUnidad = { menosOff: e.menos.disabled, masOff: e.mas.disabled };
console.log(JSON.stringify(r));
